<?php

namespace App\Infra\Carriers\InPost;

use App\Domain\Carriers\Contracts\CarrierInterface;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InPostCarrier implements CarrierInterface
{
    /**
     * Tworzy draft przesyłki w ShipX, pobiera oferty, wybiera właściwą
     * i aktualizuje model Shipment (carrier_shipment_id, tracking_number, status).
     */
    public function createShipment(Shipment $shipment): void
    {
        $base  = rtrim((string) config('carriers.inpost.api_url'), '/');
        $token = (string) config('carriers.inpost.token');
        $orgId = (string) config('carriers.inpost.organization_id');

        if (!$base || !$token) {
            throw new \RuntimeException('Brak konfiguracji InPost (api_url / token).');
        }

        $service     = $this->mapService($shipment->service_code);
        $senderEmail = (string) (config('carriers.inpost.sender_email') ?? 'noreply@example.com');
        $targetPoint = (string) (config('carriers.inpost.default_target_point') ?? '');

        // minimalne wymiary (mm), aby przejść walidację ShipX (A,B,C — tu przyjmujemy „C”)
        $dimensionsMm = [
            'length' => 80,
            'width'  => 380,
            'height' => 640,
            'unit'   => 'mm',
        ];

        $payload = [
            'service'           => $service,
            'reference'         => $shipment->id,
            'sending_method'    => 'parcel_locker',
            'custom_attributes' => array_filter([
                'target_point'   => $targetPoint ?: null,
                'sending_method' => 'parcel_locker',
            ]),
            'sender' => [
                'company_name' => $shipment->sender_name,
                'first_name'   => $shipment->sender_name,
                'last_name'    => $shipment->sender_name,
                'email'        => $senderEmail,
                'phone'        => $shipment->sender_phone,
                'address'      => [
                    'street'          => $shipment->sender_street,
                    'building_number' => '1',
                    'city'            => $shipment->sender_city,
                    'post_code'       => $shipment->sender_postal_code,
                    'country_code'    => strtoupper($shipment->sender_country_code),
                ],
            ],
            'receiver' => [
                'company_name' => $shipment->receiver_name,
                'first_name'   => $shipment->receiver_name,
                'last_name'    => $shipment->receiver_name,
                'email'        => $senderEmail, // techniczne – nie mamy maila odbiorcy
                'phone'        => $shipment->receiver_phone,
            ],
            'parcels' => [[
                'dimensions' => $dimensionsMm,
                'weight'     => [
                    'amount' => (float) $shipment->parcel_weight_kg,
                    'unit'   => 'kg',
                ],
            ]],
        ];

        // 1) CREATE DRAFT
        $create = Http::withToken($token)
            ->baseUrl($base)
            ->withHeaders($orgId ? ['Organization-Id' => $orgId] : [])
            ->acceptJson()
            ->post('/v1/shipments', $payload);

        if ($create->failed()) {
            Log::error('InPost create error', ['status' => $create->status(), 'body' => $create->body()]);
            throw new \RuntimeException("InPost create error: {$create->status()}");
        }

        $shipxId = data_get($create->json(), 'id');
        if (!$shipxId) {
            throw new \RuntimeException('Brak id w odpowiedzi /v1/shipments (create).');
        }

        // 2) GET OFFERS
        $offers = Http::withToken($token)
            ->baseUrl($base)
            ->withHeaders($orgId ? ['Organization-Id' => $orgId] : [])
            ->acceptJson()
            ->get("/v1/shipments/{$shipxId}/offers");

        if ($offers->failed()) {
            Log::error('InPost offers error', [
                'status'   => $offers->status(),
                'body'     => $offers->body(),
                'shipx_id' => $shipxId,
            ]);
            throw new \RuntimeException("InPost offers error: {$offers->status()}");
        }

        $offersArr = $offers->json() ?? [];
        if (!is_array($offersArr) || empty($offersArr)) {
            Log::error('InPost offers: pusta lista ofert', ['shipx_id' => $shipxId, 'offers' => $offersArr]);
            throw new \RuntimeException('Brak ofert dla utworzonej przesyłki.');
        }

        // wybierz ofertę dopasowaną do service; jeśli brak – bierz pierwszą
        $offer = collect($offersArr)->first(fn ($o) => (data_get($o, 'service') === $service)) ?? $offersArr[0];
        $offerId = data_get($offer, 'id');
        if (!$offerId) {
            Log::error('InPost offers: brak offer_id w wybranej ofercie', [
                'shipx_id' => $shipxId,
                'offer'    => $offer,
            ]);
            throw new \RuntimeException('Brak offer_id w ofercie.');
        }

        // 3) SELECT OFFER
        $select = Http::withToken($token)
            ->baseUrl($base)
            ->withHeaders($orgId ? ['Organization-Id' => $orgId] : [])
            ->acceptJson()
            ->post("/v1/shipments/{$shipxId}/select_offer", ['offer_id' => $offerId]);

        if ($select->failed()) {
            Log::error('InPost select_offer error', [
                'status'   => $select->status(),
                'body'     => $select->body(),
                'shipx_id' => $shipxId,
                'offer_id' => $offerId,
            ]);
            throw new \RuntimeException("InPost select_offer error: {$select->status()}");
        }

        $after = $select->json() ?? [];
        $tracking = data_get($after, 'tracking_number');

        // aktualizacja naszego modelu
        $shipment->carrier             = 'INPOST';
        $shipment->carrier_shipment_id = $shipxId;
        $shipment->tracking_number     = $tracking ?: $shipment->tracking_number;
        $shipment->status              = ShipmentStatus::CREATED;
        $shipment->save();
    }

    /**
     * Pobiera etykietę PDF (A6/A4) lub ZPL.
     */
    public function getLabel(Shipment $shipment, string $format = 'A6'): string
    {
        $format = strtoupper($format);
        if (!in_array($format, ['A6', 'A4', 'ZPL'], true)) {
            $format = 'A6';
        }

        $carrierId = $shipment->carrier_shipment_id ?? $shipment->tracking_number;
        if (!$carrierId) {
            throw new \RuntimeException('Brak carrier_shipment_id / tracking_number dla przesyłki.');
        }

        $base  = rtrim((string) config('carriers.inpost.api_url'), '/');
        $token = (string) config('carriers.inpost.token');
        $orgId = (string) config('carriers.inpost.organization_id');

        if (!$base || !$token) {
            throw new \RuntimeException('Brak konfiguracji InPost (api_url / token).');
        }

        $accept = $format === 'ZPL' ? 'text/plain' : 'application/pdf';

        $resp = Http::withToken($token)
            ->withHeaders($orgId ? ['Organization-Id' => $orgId] : [])
            ->accept($accept)
            ->get("{$base}/v1/shipments/{$carrierId}/label", ['format' => $format]);

        if ($resp->failed()) {
            Log::error('InPost label error', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);
            throw new \RuntimeException("InPost label error: {$resp->status()}");
        }

        return $resp->body();
    }

    public function getName(): string
    {
        return 'INPOST';
    }

    private function mapService(string $ourCode): string
    {
        return match (strtoupper(trim($ourCode))) {
            'INPOST_LOCKER_STANDARD'   => 'inpost_locker_standard',
            'INPOST_LOCKER_ECONOMY'    => 'inpost_locker_economy',
            'INPOST_COURIER_STANDARD'  => 'inpost_courier_standard',
            default => throw new \InvalidArgumentException("Nieznany service_code: {$ourCode}"),
        };
    }
}

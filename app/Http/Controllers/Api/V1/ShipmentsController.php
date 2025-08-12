<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShipmentsController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'service_code'          => ['required', 'string'],
            'receiver.name'         => ['required', 'string'],
            'receiver.phone'        => ['required', 'string'],
            'receiver.street'       => ['required', 'string'],
            'receiver.city'         => ['required', 'string'],
            'receiver.postal_code'  => ['required', 'string'],
            'receiver.country_code' => ['required', 'string', 'size:2'],
            'sender.name'           => ['required', 'string'],
            'sender.phone'          => ['required', 'string'],
            'sender.street'         => ['required', 'string'],
            'sender.city'           => ['required', 'string'],
            'sender.postal_code'    => ['required', 'string'],
            'sender.country_code'   => ['required', 'string', 'size:2'],
            'parcel.weight_kg'      => ['required', 'numeric', 'min:0.01'],
        ]);

        $shipment = Shipment::create([
            'id'                    => Str::ulid()->toBase32(),
            'status'                => ShipmentStatus::DRAFT,
            'service_code'          => $data['service_code'],
            'receiver_name'         => $data['receiver']['name'],
            'receiver_phone'        => $data['receiver']['phone'],
            'receiver_street'       => $data['receiver']['street'],
            'receiver_city'         => $data['receiver']['city'],
            'receiver_postal_code'  => $data['receiver']['postal_code'],
            'receiver_country_code' => strtoupper($data['receiver']['country_code']),
            'sender_name'           => $data['sender']['name'],
            'sender_phone'          => $data['sender']['phone'],
            'sender_street'         => $data['sender']['street'],
            'sender_city'           => $data['sender']['city'],
            'sender_postal_code'    => $data['sender']['postal_code'],
            'sender_country_code'   => strtoupper($data['sender']['country_code']),
            'parcel_weight_kg'      => $data['parcel']['weight_kg'],
        ]);

        // Wyznacz i zapisz przewoźnika (na razie wszystko INPOST_* => INPOST)
        $carrierCode = str_starts_with(strtoupper($data['service_code']), 'INPOST') ? 'INPOST' : null;
        if ($carrierCode) {
            $shipment->carrier = $carrierCode;
            $shipment->save();
        }

        // Utworzenie przesyłki u przewoźnika (zapisze carrier_shipment_id / tracking_number).
        if ($carrierCode === 'INPOST') {
            try {
                $resolver = new \App\Domain\Carriers\CarrierResolver();
                /** @var \App\Domain\Carriers\Contracts\CarrierInterface $carrier */
                $carrier = $resolver->resolve($carrierCode);

                $carrier->createShipment($shipment); // nic nie zwraca, zapis w środku
                $shipment->refresh();

                // jeśli się udało – podnieś status
                if ($shipment->carrier_shipment_id || $shipment->tracking_number) {
                    $shipment->status = ShipmentStatus::CREATED;
                    $shipment->save();
                }
            } catch (\Throwable $e) {
                \Log::warning('ShipX create failed, label będzie niedostępny do czasu naprawy', [
                    'shipment_id' => $shipment->id,
                    'error'       => $e->getMessage(),
                ]);
                // świadomie nie przerywamy: zwracamy 201 z DRAFT, UI może spróbować później
            }
        }

        return response()->json([
            'id'         => $shipment->id,
            'status'     => $shipment->status->value,
            'created_at' => $shipment->created_at?->toISOString(),
        ], 201);
    }

    public function label(string $id, Request $request)
    {
        $shipment = Shipment::findOrFail($id);

        // jeśli nie mamy identyfikatorów z przewoźnika – zwróć 409 zamiast 500
        if (empty($shipment->carrier_shipment_id) && empty($shipment->tracking_number)) {
            return response()->json([
                'message' => 'Etykieta niedostępna: przesyłka nie została jeszcze utworzona u przewoźnika.',
            ], 409);
        }

        $format = strtoupper($request->query('format', 'A6')); // A6|A4|ZPL
        $carrierCode = $shipment->carrier ?: 'INPOST';

        $resolver = new \App\Domain\Carriers\CarrierResolver();
        /** @var \App\Domain\Carriers\Contracts\CarrierInterface $client */
        $client   = $resolver->resolve($carrierCode);

        $content = $client->getLabel($shipment, $format);

        $isZpl = ($format === 'ZPL');
        $mime  = $isZpl ? 'text/plain' : 'application/pdf';
        $ext   = $isZpl ? 'zpl' : 'pdf';

        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', "inline; filename=\"label_{$shipment->id}.{$ext}\"");
    }

    public function tracking(string $id)
    {
        $shipment = Shipment::findOrFail($id);

        return response()->json([
            'shipment_id'     => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'events'          => [],
        ]);
    }
}

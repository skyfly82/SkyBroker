<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\FetchLabelJob;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhooksController extends Controller
{
    public function payments(Request $request)
    {
        $apiKey = $request->header('X-Api-Key');
        if ($apiKey !== env('PAYMENTS_WEBHOOK_KEY')) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->validate([
            'event' => 'required|string',
            'data.payment_id' => 'nullable|string',
            'data.shipment_id' => 'required|string|exists:shipments,id',
            'data.status' => 'required|string',
        ]);

        if ($payload['event'] === 'payment.paid' && strtoupper($payload['data']['status']) === 'PAID') {
            $shipment = Shipment::findOrFail($payload['data']['shipment_id']);
            $payment = $shipment->payments()->where('id', $payload['data']['payment_id'] ?? null)->first();

            if (!$payment) {
                $payment = $shipment->payments()->create([
                    'provider' => 'provider',
                    'status' => PaymentStatus::PAID,
                    'amount_pln' => 0,
                    'currency' => 'PLN',
                    'initiated_at' => now(),
                    'paid_at' => now(),
                ]);
            } else {
                $payment->status = PaymentStatus::PAID;
                $payment->paid_at = now();
                $payment->save();
            }

            if ($shipment->status->canTransitionTo(ShipmentStatus::PAID)) {
                $shipment->status = ShipmentStatus::PAID;
                $shipment->save();
            }

            FetchLabelJob::dispatchSync($shipment->id, 'A6');
        }

        return response()->noContent();
    }
}

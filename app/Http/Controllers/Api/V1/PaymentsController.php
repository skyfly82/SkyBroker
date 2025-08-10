<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\FetchLabelJob;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function start(string $shipmentId)
    {
        $shipment = Shipment::findOrFail($shipmentId);

        $payment = Payment::create([
            'shipment_id' => $shipment->id,
            'provider' => 'simulator',
            'status' => PaymentStatus::PENDING,
            'amount_pln' => 0,
            'currency' => 'PLN',
            'initiated_at' => now(),
        ]);

        if ($shipment->status->canTransitionTo(ShipmentStatus::PENDING_PAYMENT)) {
            $shipment->status = ShipmentStatus::PENDING_PAYMENT;
            $shipment->save();
        }

        return response()->json([
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'redirect_url' => null,
        ]);
    }

    public function simulate(Request $request)
    {
        $data = $request->validate([
            'shipment_id' => 'required|string|exists:shipments,id',
        ]);

        $shipment = Shipment::findOrFail($data['shipment_id']);
        $payment = $shipment->payments()->latest()->first();

        if (!$payment) {
            $payment = Payment::create([
                'shipment_id' => $shipment->id,
                'provider' => 'simulator',
                'status' => PaymentStatus::PENDING,
                'amount_pln' => 0,
                'currency' => 'PLN',
                'initiated_at' => now(),
            ]);
        }

        $payment->status = PaymentStatus::PAID;
        $payment->paid_at = now();
        $payment->save();

        if ($shipment->status->canTransitionTo(ShipmentStatus::PAID)) {
            $shipment->status = ShipmentStatus::PAID;
            $shipment->save();
        }

        // Dev: natychmiastowy placeholder PDF (queue=sync w .env.example)
        FetchLabelJob::dispatchSync($shipment->id, 'A6');

        return response()->json(['ok' => true]);
    }
}

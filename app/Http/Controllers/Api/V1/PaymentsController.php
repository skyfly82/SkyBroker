<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
    public function start(Request $request, string $shipmentId)
    {
        $shipment = Shipment::findOrFail($shipmentId);

        $payment = Payment::create([
            'id'           => Str::ulid()->toBase32(),
            'shipment_id'  => $shipment->id,
            'provider'     => 'simulator',
            'status'       => 'PENDING',
            'amount_pln'   => 0,
            'currency'     => 'PLN',
            'initiated_at' => now(),
        ]);

        if ($shipment->status->canTransitionTo(ShipmentStatus::PENDING_PAYMENT)) {
            $shipment->status = ShipmentStatus::PENDING_PAYMENT;
            $shipment->save();
        }

        return response()->json([
            'payment_id'   => $payment->id,
            'provider'     => $payment->provider,
            'redirect_url' => null,
        ]);
    }

    public function simulate(Request $request)
    {
        $data = $request->validate([
            'payment_id' => ['required', 'string'],
            'status'     => ['required', 'in:PAID,FAILED,CANCELLED'],
        ]);

        $payment = Payment::findOrFail($data['payment_id']);
        $shipment = $payment->shipment;

        $payment->status = $data['status'];
        $payment->completed_at = now();
        $payment->save();

        if ($data['status'] === 'PAID' && $shipment->status->canTransitionTo(ShipmentStatus::PAID)) {
            $shipment->status = ShipmentStatus::PAID;
            $shipment->save();
        }

        if ($data['status'] === 'CANCELLED' && $shipment->status->canTransitionTo(ShipmentStatus::CANCELLED)) {
            $shipment->status = ShipmentStatus::CANCELLED;
            $shipment->save();
        }

        return response()->json([
            'ok'          => true,
            'payment_id'  => $payment->id,
            'status'      => $payment->status,
            'shipment_id' => $shipment->id,
        ]);
    }
}

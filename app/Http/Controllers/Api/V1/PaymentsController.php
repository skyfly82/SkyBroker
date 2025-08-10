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
    /**
     * POST /api/v1/payments/{shipmentId}/start
     */
    public function start(Request $request, string $shipmentId)
    {
        $shipment = Shipment::query()->findOrFail($shipmentId);

        // Tworzymy "symulator" płatności (bez zewn. providera)
        $payment = Payment::query()->create([
            'id'           => Str::ulid()->toBase32(),
            'shipment_id'  => $shipment->id,
            'provider'     => 'simulator',
            'status'       => 'PENDING',
            'amount_pln'   => 0,
            'currency'     => 'PLN',
            'initiated_at' => now(),
        ]);

        // Bezpieczna zmiana statusu przesyłki
        if ($shipment->status instanceof ShipmentStatus
            && $shipment->status->canTransitionTo(ShipmentStatus::PENDING_PAYMENT)) {
            $shipment->status = ShipmentStatus::PENDING_PAYMENT;
            $shipment->save();
        }

        return response()->json([
            'payment_id'  => $payment->id,
            'provider'    => $payment->provider,
            'redirect_url'=> null,
        ]);
    }

    /**
     * POST /api/v1/payments/simulate
     * body: { payment_id: string, status: "PAID"|"FAILED"|"CANCELLED" }
     *
     * Uwaga: nie wymagamy shipment_id – bierzemy go z relacji Payment->Shipment.
     */
    public function simulate(Request $request)
    {
        $data = $request->validate([
            'payment_id' => ['required', 'string'],
            'status'     => ['required', 'in:PAID,FAILED,CANCELLED'],
        ]);

        $payment = Payment::query()->findOrFail($data['payment_id']);
        $shipment = $payment->shipment;

        $payment->status = $data['status'];
        $payment->completed_at = now();
        $payment->save();

        // Aktualizacja statusu przesyłki po wynikach płatności
        if ($shipment && $shipment->status instanceof ShipmentStatus) {
            if ($data['status'] === 'PAID'
                && $shipment->status->canTransitionTo(ShipmentStatus::PAID)) {
                $shipment->status = ShipmentStatus::PAID;
                $shipment->save();
            }

            if ($data['status'] === 'CANCELLED'
                && $shipment->status->canTransitionTo(ShipmentStatus::CANCELLED)) {
                $shipment->status = ShipmentStatus::CANCELLED;
                $shipment->save();
            }
            // FAILED pozostawia przesyłkę w obecnym stanie (PENDING_PAYMENT)
        }

        return response()->json([
            'ok'          => true,
            'payment_id'  => $payment->id,
            'status'      => $payment->status,
            'shipment_id' => $shipment?->id,
        ]);
    }
}

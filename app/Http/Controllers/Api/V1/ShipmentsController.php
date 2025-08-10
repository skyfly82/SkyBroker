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
            'id'                 => Str::ulid()->toBase32(),
            'status'             => ShipmentStatus::DRAFT,
            'service_code'       => $data['service_code'],
            'receiver_name'      => $data['receiver']['name'],
            'receiver_phone'     => $data['receiver']['phone'],
            'receiver_street'    => $data['receiver']['street'],
            'receiver_city'      => $data['receiver']['city'],
            'receiver_postal_code'  => $data['receiver']['postal_code'],
            'receiver_country_code' => strtoupper($data['receiver']['country_code']),
            'sender_name'        => $data['sender']['name'],
            'sender_phone'       => $data['sender']['phone'],
            'sender_street'      => $data['sender']['street'],
            'sender_city'        => $data['sender']['city'],
            'sender_postal_code' => $data['sender']['postal_code'],
            'sender_country_code'=> strtoupper($data['sender']['country_code']),
            'parcel_weight_kg'   => $data['parcel']['weight_kg'],
        ]);

        return response()->json([
            'id'              => $shipment->id,
            'status'          => $shipment->status->value,
            'created_at'      => $shipment->created_at?->toISOString(),
        ], 201);
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

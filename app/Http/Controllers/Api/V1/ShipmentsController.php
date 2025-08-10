<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentLabel;
use App\Models\TrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ShipmentsController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'service_code' => 'required|string',
            'reference' => 'nullable|string',
            'receiver.name' => 'required|string',
            'receiver.phone' => 'required|string',
            'receiver.email' => 'nullable|email',
            'receiver.street' => 'required|string',
            'receiver.building_number' => 'nullable|string',
            'receiver.apartment_number' => 'nullable|string',
            'receiver.city' => 'required|string',
            'receiver.postal_code' => 'required|string',
            'receiver.country_code' => 'required|string|size:2',
            'sender.name' => 'required|string',
            'sender.phone' => 'required|string',
            'sender.email' => 'nullable|email',
            'sender.street' => 'required|string',
            'sender.building_number' => 'nullable|string',
            'sender.apartment_number' => 'nullable|string',
            'sender.city' => 'required|string',
            'sender.postal_code' => 'required|string',
            'sender.country_code' => 'required|string|size:2',
            'parcel.length_cm' => 'nullable|numeric',
            'parcel.width_cm' => 'nullable|numeric',
            'parcel.height_cm' => 'nullable|numeric',
            'parcel.weight_kg' => 'required|numeric|min:0.01',
            'pickup_point_id' => 'nullable|string',
            'cod_amount_pln' => 'nullable|numeric|min:0',
            'insurance_amount_pln' => 'nullable|numeric|min:0',
        ]);

        $s = new Shipment();
        $s->status = ShipmentStatus::DRAFT;
        $s->service_code = $data['service_code'];
        $s->reference = $data['reference'] ?? null;

        $s->receiver_name = $data['receiver']['name'];
        $s->receiver_phone = $data['receiver']['phone'];
        $s->receiver_email = $data['receiver']['email'] ?? null;
        $s->receiver_street = $data['receiver']['street'];
        $s->receiver_building_number = $data['receiver']['building_number'] ?? null;
        $s->receiver_apartment_number = $data['receiver']['apartment_number'] ?? null;
        $s->receiver_city = $data['receiver']['city'];
        $s->receiver_postal_code = $data['receiver']['postal_code'];
        $s->receiver_country_code = strtoupper($data['receiver']['country_code']);

        $s->sender_name = $data['sender']['name'];
        $s->sender_phone = $data['sender']['phone'];
        $s->sender_email = $data['sender']['email'] ?? null;
        $s->sender_street = $data['sender']['street'];
        $s->sender_building_number = $data['sender']['building_number'] ?? null;
        $s->sender_apartment_number = $data['sender']['apartment_number'] ?? null;
        $s->sender_city = $data['sender']['city'];
        $s->sender_postal_code = $data['sender']['postal_code'];
        $s->sender_country_code = strtoupper($data['sender']['country_code']);

        $s->parcel_length_cm = $data['parcel']['length_cm'] ?? null;
        $s->parcel_width_cm = $data['parcel']['width_cm'] ?? null;
        $s->parcel_height_cm = $data['parcel']['height_cm'] ?? null;
        $s->parcel_weight_kg = $data['parcel']['weight_kg'];

        $s->pickup_point_id = $data['pickup_point_id'] ?? null;
        $s->metadata = [
            'cod_amount_pln' => $data['cod_amount_pln'] ?? null,
            'insurance_amount_pln' => $data['insurance_amount_pln'] ?? null,
        ];

        $s->save();

        return response()->json([
            'id' => $s->id,
            'status' => $s->status->value,
            'carrier' => $s->carrier_code,
            'tracking_number' => $s->tracking_number,
            'price_pln' => $s->price_pln,
            'created_at' => $s->created_at,
        ], Response::HTTP_CREATED);
    }

    public function label(Request $request, string $id)
    {
        $format = strtoupper($request->query('format', 'A6'));
        if (!in_array($format, ['A6','A4','ZPL'], true)) {
            return response()->json(['message' => 'Invalid format'], 422);
        }

        $shipment = Shipment::findOrFail($id);
        $label = ShipmentLabel::where('shipment_id', $shipment->id)->latest()->first();

        if (!$label || !Storage::disk('local')->exists($label->storage_path)) {
            return response()->json(['message' => 'Label not ready'], 404);
        }

        $content = Storage::disk('local')->get($label->storage_path);
        return response($content, 200, [
            'Content-Type' => $label->mime_type ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="label_'.$shipment->id.'.pdf"'
        ]);
    }

    public function tracking(string $id)
    {
        $shipment = Shipment::findOrFail($id);
        $events = TrackingEvent::where('shipment_id', $shipment->id)->orderBy('occurred_at')->get()->map(function ($e) {
            return [
                'code' => $e->code,
                'description' => $e->description,
                'occurred_at' => $e->occurred_at?->toISOString(),
                'location' => $e->location,
            ];
        });

        return response()->json([
            'shipment_id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'events' => $events,
        ]);
    }
}

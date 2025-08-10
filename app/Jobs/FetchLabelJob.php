<?php

namespace App\Jobs;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class FetchLabelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $shipmentId, public string $format = 'A6')
    {
    }

    public function handle(): void
    {
        $shipment = Shipment::findOrFail($this->shipmentId);

        // Dev: zapisujemy placeholder PDF (TODO: podłączyć realne API kuriera)
        $content = "%PDF-1.4\n% SkyBroker Placeholder Label\n1 0 obj <<>> endobj\ntrailer<<>>\n%%EOF\n";
        $path = 'labels/' . $shipment->id . '-' . now()->timestamp . '.pdf';
        Storage::disk('local')->put($path, $content);

        ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => $this->format,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
        ]);

        if ($shipment->status->canTransitionTo(ShipmentStatus::LABEL_READY)) {
            $shipment->status = ShipmentStatus::LABEL_READY;
            $shipment->save();
        }
    }
}

<?php

namespace App\Domain\Carriers;

use App\Models\Shipment;

interface CarrierInterface
{
    /**
     * Pobiera etykietę dla przesyłki.
     *
     * @param  Shipment $shipment
     * @param  string   $format   np. "A4", "A6", "ZPL"
     * @return string   binarka PDF/ZPL
     */
    public function getLabel(Shipment $shipment, string $format): string;
}

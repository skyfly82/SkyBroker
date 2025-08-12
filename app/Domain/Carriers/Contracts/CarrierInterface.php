<?php

namespace App\Domain\Carriers\Contracts;

use App\Models\Shipment;

interface CarrierInterface
{
    /**
     * Tworzy przesyłkę u przewoźnika i aktualizuje Shipment
     * (ustawia carrier, tracking_number itd.).
     */
    public function createShipment(Shipment $shipment): void;

    /**
     * Zwraca treść etykiety (PDF/ZPL) dla już utworzonej przesyłki.
     */
    public function getLabel(Shipment $shipment, string $format = 'A6'): string;
}

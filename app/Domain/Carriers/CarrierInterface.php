<?php

namespace App\Domain\Carriers;

interface CarrierInterface
{
    public function createShipment(array $payload): array;
    public function getLabel(string $shipmentId, string $format = 'A6'): string;
    public function getPickupPoints(array $filters = []): array;
    public function manifest(array $shipmentIds): array;
    public function track(string $trackingNumber): array;
}

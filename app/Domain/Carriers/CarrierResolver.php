<?php

namespace App\Domain\Carriers;

use App\Domain\Carriers\Contracts\CarrierInterface;
use App\Infra\Carriers\InPost\InPostCarrier;

class CarrierResolver
{
    public function resolve(string $code): CarrierInterface
    {
        $code = strtoupper($code);

        return match ($code) {
            'INPOST' => app(InPostCarrier::class),
            default  => throw new \InvalidArgumentException("Unknown carrier: {$code}"),
        };
    }
}

<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case DRAFT           = 'DRAFT';
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAID            = 'PAID';
    case LABEL_READY     = 'LABEL_READY';
    case MANIFESTED      = 'MANIFESTED';
    case SHIPPED         = 'SHIPPED';
    case DELIVERED       = 'DELIVERED';
    case CANCELLED       = 'CANCELLED';
    case RETURNED        = 'RETURNED';

    public function canTransitionTo(self $to): bool
    {
        $allowed = [
            self::DRAFT->value           => [self::PENDING_PAYMENT, self::CANCELLED],
            self::PENDING_PAYMENT->value => [self::PAID, self::CANCELLED],
            self::PAID->value            => [self::LABEL_READY, self::CANCELLED],
            self::LABEL_READY->value     => [self::MANIFESTED],
            self::MANIFESTED->value      => [self::SHIPPED],
            self::SHIPPED->value         => [self::DELIVERED, self::RETURNED],
            self::DELIVERED->value       => [],
            self::CANCELLED->value       => [],
            self::RETURNED->value        => [],
        ];

        return in_array($to, $allowed[$this->value] ?? [], true);
    }
}

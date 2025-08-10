<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAID = 'PAID';
    case LABEL_READY = 'LABEL_READY';
    case MANIFESTED = 'MANIFESTED';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';
    case RETURNED = 'RETURNED';

    public function canTransitionTo(self $to): bool
    {
        $allowed = [
            self::DRAFT => [self::PENDING_PAYMENT, self::CANCELLED],
            self::PENDING_PAYMENT => [self::PAID, self::CANCELLED],
            self::PAID => [self::LABEL_READY, self::CANCELLED],
            self::LABEL_READY => [self::MANIFESTED],
            self::MANIFESTED => [self::SHIPPED],
            self::SHIPPED => [self::DELIVERED, self::RETURNED],
            self::DELIVERED => [],
            self::CANCELLED => [],
            self::RETURNED => [],
        ];

        return in_array($to, $allowed[$this] ?? [], true);
    }
}

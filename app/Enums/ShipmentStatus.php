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

    /**
     * Czy bieżący status może przejść do $to
     *
     * Uwaga: używamy mapy indeksowanej po stringach (->value),
     * żeby uniknąć błędu "Cannot access offset of type ... on array".
     */
    public function canTransitionTo(self $to): bool
    {
        $allowed = self::allowedTransitions();

        return in_array($to, $allowed[$this->value] ?? [], true);
    }

    /**
     * Czy to poprawna sekwencja przejścia.
     */
    public static function assertCanTransition(self $from, self $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw new \LogicException(sprintf(
                'Invalid shipment status transition: %s -> %s',
                $from->value,
                $to->value
            ));
        }
    }

    /**
     * Mapa dozwolonych przejść statusów.
     * Klucze po stringach (case->value), wartości to tablice enumów.
     */
    private static function allowedTransitions(): array
    {
        return [
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
    }
}
<?php

namespace App\Support;

final class InventoryMovementTypes
{
    public const IN = [
        'OPENING_BALANCE',
        'OPENING_ADJUSTMENT_IN',
        'PURCHASE',
        'PURCHASE_IN',
        'ADJUSTMENT_IN',
        'CONSUMPTION_ADJUSTMENT_REVERSAL',
        'CONSUMPTION_REVERSAL',
        'RETURN_IN',
        'RECEIPT_IN',
    ];

    public const OUT = [
        'CONSUMPTION',
        'ADJUSTMENT_OUT',
        'OPENING_ADJUSTMENT_OUT',
        'CONSUMPTION_ADJUSTMENT',
        'RETURN_OUT',
    ];

    public static function direction(?string $type): ?string
    {
        $normalized = strtoupper(trim((string) $type));
        return in_array($normalized, self::IN, true) ? 'in' : (in_array($normalized, self::OUT, true) ? 'out' : null);
    }
}

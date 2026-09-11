<?php

namespace App\Services;

class UnitConversionService
{
    /**
     * Convert a quantity between compatible inventory units. Returns null when
     * the units belong to different measurement families.
     */
    public function convert(float $quantity, string $from, string $to): ?float
    {
        $from = $this->normalise($from);
        $to = $this->normalise($to);

        if ($from === $to) {
            return $quantity;
        }

        $weightToGram = [
            'mg' => 0.001,
            'g' => 1.0,
            'kg' => 1000.0,
            'oz' => 28.349523125,
            'lb' => 453.59237,
        ];
        $volumeToMl = [
            'ml' => 1.0,
            'cl' => 10.0,
            'l' => 1000.0,
            'fl_oz' => 29.5735295625,
            'gal' => 3785.411784,
        ];
        $countUnits = ['pcs', 'pc', 'piece', 'items', 'each'];

        if (isset($weightToGram[$from], $weightToGram[$to])) {
            return $quantity * $weightToGram[$from] / $weightToGram[$to];
        }

        if (isset($volumeToMl[$from], $volumeToMl[$to])) {
            return $quantity * $volumeToMl[$from] / $volumeToMl[$to];
        }

        if (in_array($from, $countUnits, true) && in_array($to, $countUnits, true)) {
            return $quantity;
        }

        return null;
    }

    public function normalise(string $unit): string
    {
        $unit = strtolower(trim($unit));

        return match ($unit) {
            'grams', 'gram', 'gm' => 'g',
            'kilograms', 'kilogram' => 'kg',
            'milligrams', 'milligram' => 'mg',
            'milliliters', 'milliliter', 'millilitre', 'millilitres' => 'ml',
            'liters', 'liter', 'litres', 'litre' => 'l',
            'pieces', 'piece', 'pc', 'item', 'each' => 'pcs',
            default => $unit,
        };
    }
}

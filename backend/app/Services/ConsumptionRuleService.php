<?php

namespace App\Services;

use App\Models\RecipeItem;

class ConsumptionRuleService
{
    /**
     * Calculate expected quantity based on consumption rule type.
     */
    public function calculateExpectedQty(RecipeItem $recipeItem, ?float $bottleSizeMl = null, ?float $oilGrams = null): float
    {
        switch ($recipeItem->consumption_rule_type) {
            case 'fixed':
                return $this->calculateFixed($recipeItem);
                
            case 'per_bottle':
                return $this->calculatePerBottle($recipeItem, $bottleSizeMl, $oilGrams);
                
            case 'percentage':
                return $this->calculatePercentage($recipeItem, $bottleSizeMl);
                
            default:
                return $recipeItem->expected_qty;
        }
    }


    /**
     * ROUH production rule: alcohol fills the remaining bottle volume after
     * the recorded oil quantity. The project deliberately treats the entered
     * oil grams as the numeric quantity subtracted from bottle millilitres.
     * This is a business rule, not a physical density conversion.
     */
    public function calculateResidualAlcoholMl(float $bottleSizeMl, float $oilGrams): float
    {
        return max(0.0, $bottleSizeMl - $oilGrams);
    }

    /**
     * Fixed quantity - no calculation needed
     */
    private function calculateFixed(RecipeItem $recipeItem): float
    {
        return $recipeItem->expected_qty;
    }

    /**
     * Per bottle calculation - e.g., alcohol = bottle_size_ml - oil_grams
     */
    private function calculatePerBottle(RecipeItem $recipeItem, ?float $bottleSizeMl, ?float $oilGrams): float
    {
        if ($bottleSizeMl === null) {
            return $recipeItem->expected_qty;
        }

        $ruleConfig = $recipeItem->rule_config ?? [];
        
        // Project rule: bottle size minus entered oil quantity.
        if (($ruleConfig['formula'] ?? null) === 'bottle_minus_oil') {
            $oilQuantity = $oilGrams ?? (float) $recipeItem->expected_qty;
            return $this->calculateResidualAlcoholMl($bottleSizeMl, $oilQuantity);
        }

        return $recipeItem->expected_qty;
    }

    /**
     * Percentage calculation - e.g., oil = 40% of bottle volume
     */
    private function calculatePercentage(RecipeItem $recipeItem, ?float $bottleSizeMl): float
    {
        if ($bottleSizeMl === null) {
            return $recipeItem->expected_qty;
        }

        $percentage = $recipeItem->expected_qty; // expected_qty stores the percentage
        return ($percentage / 100) * $bottleSizeMl;
    }

    /**
     * Validate unit compatibility between recipe item and material
     */
    public function validateUnitCompatibility(RecipeItem $recipeItem, string $materialBaseUnit): bool
    {
        $recipeUnit = $recipeItem->unit;
        
        // Define compatible unit groups
        $weightUnits = ['g', 'kg', 'mg', 'oz', 'lb'];
        $volumeUnits = ['ml', 'l', 'cl', 'fl_oz', 'gal'];
        $countUnits = ['pcs', 'pc', 'piece', 'items', 'each'];
        
        // Get unit group for recipe unit
        $recipeGroup = $this->getUnitGroup($recipeUnit);
        $materialGroup = $this->getUnitGroup($materialBaseUnit);
        
        return $recipeGroup !== 'unknown' && $recipeGroup === $materialGroup;
    }

    /**
     * Get unit group for compatibility checking
     */
    public function unitsCompatible(string $recipeUnit, string $materialUnit): bool
    {
        $a = $this->getUnitGroup($recipeUnit); $b = $this->getUnitGroup($materialUnit);
        return $a !== "unknown" && $a === $b;
    }

    private function getUnitGroup(string $unit): string
    {
        $weightUnits = ['g', 'kg', 'mg', 'oz', 'lb'];
        $volumeUnits = ['ml', 'l', 'cl', 'fl_oz', 'gal'];
        $countUnits = ['pcs', 'pc', 'piece', 'items', 'each'];
        
        if (in_array(strtolower($unit), $weightUnits)) {
            return 'weight';
        }
        
        if (in_array(strtolower($unit), $volumeUnits)) {
            return 'volume';
        }
        
        if (in_array(strtolower($unit), $countUnits)) {
            return 'count';
        }
        
        return 'unknown';
    }
}

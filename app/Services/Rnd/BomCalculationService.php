<?php

namespace App\Services\Rnd;

class BomCalculationService
{
    /** @param array<string, mixed> $component */
    public function componentRequirement(array $component, float $multiplier = 1.0, bool $applyTolerance = false): float
    {
        $quantity = max(0.0, (float) ($component['qty'] ?? 0)) * max(0.0, $multiplier);

        if ($applyTolerance) {
            $quantity *= 1 + (max(0.0, (float) ($component['tolerancePercent'] ?? 0)) / 100);
        }

        return $quantity;
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  array<string, mixed>  $childBom
     * @param  null|callable(array<string, mixed>): ?float  $outputConversionResolver
     * @return array{multiplier: float, is_proportional: bool}
     */
    public function childRecipeMultiplier(array $component, array $childBom, float $requiredQuantity, ?callable $outputConversionResolver = null): array
    {
        $outputConversion = $this->outputConversionFactor($childBom);

        if ($outputConversion === null && $outputConversionResolver !== null) {
            $outputConversion = $this->positiveNumber($outputConversionResolver($childBom));
        }

        if ($outputConversion === null) {
            return ['multiplier' => $requiredQuantity, 'is_proportional' => false];
        }

        return [
            'multiplier' => ($requiredQuantity * $this->componentConversionFactor($component)) / $outputConversion,
            'is_proportional' => true,
        ];
    }

    /** @param array<string, mixed> $component */
    public function lineCost(array $component, float $unitPrice, float $multiplier = 1.0, bool $applyTolerance = false): float
    {
        return $this->componentRequirement($component, $multiplier, $applyTolerance) * $unitPrice;
    }

    /** @param array<string, mixed> $component */
    public function componentConversionFactor(array $component): float
    {
        return $this->positiveNumber(
            $component['convertionQty'] ?? $component['conversionFactor'] ?? $component['uomQty'] ?? null,
        ) ?? 1.0;
    }

    /** @param array<string, mixed> $bom */
    public function outputConversionFactor(array $bom): ?float
    {
        return $this->positiveNumber(
            $bom['convertionQty'] ?? $bom['conversionFactor'] ?? $bom['uomQty'] ?? null,
        );
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }
}

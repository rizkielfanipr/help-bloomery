<?php

namespace App\Services\Rnd;

class MenuPricingCalculator
{
    /** @return array{hpp: float, raw_price: float, recommended_price: float, food_cost_percentage: float, gross_margin: float, gross_margin_percentage: float} */
    public function fromTargetFoodCost(float $hpp, float $targetFoodCostPercentage, int $roundingIncrement = 1): array
    {
        if ($hpp <= 0 || $targetFoodCostPercentage <= 0 || $targetFoodCostPercentage >= 100) {
            return $this->emptyResult($hpp);
        }

        $rawPrice = $hpp / ($targetFoodCostPercentage / 100);
        $recommendedPrice = $this->roundUp($rawPrice, $roundingIncrement);

        return $this->analysePrice($hpp, $recommendedPrice, $rawPrice);
    }

    /** @return array{hpp: float, raw_price: float, recommended_price: float, food_cost_percentage: float, gross_margin: float, gross_margin_percentage: float} */
    public function withAdjustment(float $hpp, float $basePrice, float $adjustmentPercentage, int $roundingIncrement = 1): array
    {
        if ($hpp <= 0 || $basePrice <= 0 || $adjustmentPercentage < 0) {
            return $this->emptyResult($hpp);
        }

        $rawPrice = $basePrice * (1 + ($adjustmentPercentage / 100));
        $recommendedPrice = $this->roundUp($rawPrice, $roundingIncrement);

        return $this->analysePrice($hpp, $recommendedPrice, $rawPrice);
    }

    public function foodCostPercentage(float $hpp, float $sellingPrice): ?float
    {
        if ($hpp <= 0 || $sellingPrice <= 0) {
            return null;
        }

        return round(($hpp / $sellingPrice) * 100, 2);
    }

    /** @return array{hpp: float, raw_price: float, recommended_price: float, food_cost_percentage: float, gross_margin: float, gross_margin_percentage: float} */
    private function analysePrice(float $hpp, float $recommendedPrice, float $rawPrice): array
    {
        $grossMargin = max(0, $recommendedPrice - $hpp);

        return [
            'hpp' => round($hpp, 2),
            'raw_price' => round($rawPrice, 2),
            'recommended_price' => round($recommendedPrice, 2),
            'food_cost_percentage' => $this->foodCostPercentage($hpp, $recommendedPrice) ?? 0,
            'gross_margin' => round($grossMargin, 2),
            'gross_margin_percentage' => $recommendedPrice > 0 ? round(($grossMargin / $recommendedPrice) * 100, 2) : 0,
        ];
    }

    private function roundUp(float $price, int $increment): float
    {
        $increment = max(1, $increment);

        return ceil($price / $increment) * $increment;
    }

    /** @return array{hpp: float, raw_price: float, recommended_price: float, food_cost_percentage: float, gross_margin: float, gross_margin_percentage: float} */
    private function emptyResult(float $hpp): array
    {
        return [
            'hpp' => (float) max(0, $hpp),
            'raw_price' => 0.0,
            'recommended_price' => 0.0,
            'food_cost_percentage' => 0.0,
            'gross_margin' => 0.0,
            'gross_margin_percentage' => 0.0,
        ];
    }
}

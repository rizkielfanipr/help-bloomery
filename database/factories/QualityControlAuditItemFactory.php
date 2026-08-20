<?php

namespace Database\Factories;

use App\Models\QualityControlAudit;
use App\Models\QualityControlAuditItem;
use App\Models\QualityControlChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityControlAuditItem>
 */
class QualityControlAuditItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quality_control_audit_id' => QualityControlAudit::factory(),
            'quality_control_checklist_item_id' => QualityControlChecklistItem::factory(),
            'section_code' => 'A',
            'section_name' => 'Hygiene & Food Safety',
            'question' => fake()->sentence(),
            'check_procedure' => fake()->sentence(),
            'maximum_points' => fake()->numberBetween(1, 15),
            'is_critical' => false,
            'requires_photo' => false,
            'sort_order' => fake()->unique()->numberBetween(1, 5000),
            'action_status' => 'open',
        ];
    }
}

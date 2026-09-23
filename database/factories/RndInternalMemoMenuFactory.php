<?php

namespace Database\Factories;

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndInternalMemoMenu>
 */
class RndInternalMemoMenuFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $menuId = $this->faker->unique()->numberBetween(1, 100000);

        return [
            'rnd_internal_memo_id' => RndInternalMemo::factory(),
            'esb_menu_id' => $menuId,
            'menu_code' => 'MENU-'.$menuId,
            'menu_name' => $this->faker->words(3, true),
            'esb_bom_id' => $this->faker->numberBetween(1, 100000),
            'release_date' => now()->addWeek()->toDateString(),
            'forecast_quantity' => $this->faker->numberBetween(10, 500),
            'sync_status' => RndInternalMemoMenuSyncStatus::Pending,
            'menu_snapshot' => ['menuID' => $menuId, 'menuName' => $this->faker->words(3, true)],
            'sort_order' => 1,
        ];
    }
}

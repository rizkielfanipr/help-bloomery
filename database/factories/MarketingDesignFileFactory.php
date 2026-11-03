<?php

namespace Database\Factories;

use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketingDesignFile>
 */
class MarketingDesignFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'marketing_design_item_id' => MarketingDesignItem::factory(),
            'disk' => 'b2',
            'path' => 'brand-marketing/design-master/test/'.Str::uuid().'.pdf',
            'original_name' => 'desain.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'uploaded_at' => now(),
        ];
    }

    public function image(): static
    {
        return $this->state(fn (): array => [
            'path' => 'brand-marketing/design-master/test/'.Str::uuid().'.jpg',
            'original_name' => 'desain.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
        ]);
    }
}

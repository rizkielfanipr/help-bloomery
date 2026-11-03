<?php

namespace Database\Factories;

use App\Enums\MarketingDesignFileRole;
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
            'role' => MarketingDesignFileRole::Preview,
            'disk' => 'b2',
            'path' => 'brand-marketing/design-master/test/'.Str::uuid().'.jpg',
            'original_name' => 'preview.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'uploaded_at' => now(),
        ];
    }

    public function role(MarketingDesignFileRole $role): static
    {
        return $this->state(fn (): array => match ($role) {
            MarketingDesignFileRole::Preview => ['role' => $role],
            MarketingDesignFileRole::Production => ['role' => $role, 'original_name' => 'production.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'path' => 'brand-marketing/design-master/test/'.Str::uuid().'.pdf'],
            default => ['role' => $role, 'original_name' => $role->value.'.zip', 'mime_type' => 'application/zip', 'extension' => 'zip', 'path' => 'brand-marketing/design-master/test/'.Str::uuid().'.zip'],
        });
    }
}

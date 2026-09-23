<?php

namespace App\Models;

use Database\Factories\StockCardSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCardSetting extends Model
{
    public const GLOBAL_COMPANY = '*';

    /** @use HasFactory<StockCardSettingFactory> */
    use HasFactory;

    protected $fillable = ['company_code', 'all_categories', 'categories', 'show_uncategorized', 'category_sources', 'category_rules'];

    protected $attributes = ['all_categories' => true, 'show_uncategorized' => true];

    protected function casts(): array
    {
        return [
            'all_categories' => 'boolean',
            'categories' => 'array',
            'show_uncategorized' => 'boolean',
            'category_sources' => 'array',
            'category_rules' => 'array',
        ];
    }
}

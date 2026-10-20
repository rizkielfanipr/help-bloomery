<?php

namespace App\Models;

use Database\Factories\RndProjectPartnershipMenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndProjectPartnershipMenu extends Model
{
    /** @use HasFactory<RndProjectPartnershipMenuFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_project_id', 'company_code', 'branch_code', 'esb_menu_id', 'menu_code',
        'menu_name', 'category_name', 'menu_snapshot', 'sort_order', 'created_by',
    ];

    protected function casts(): array
    {
        return ['esb_menu_id' => 'integer', 'menu_snapshot' => 'array', 'sort_order' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'rnd_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

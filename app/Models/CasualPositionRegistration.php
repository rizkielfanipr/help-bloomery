<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasualPositionRegistration extends Model
{
    protected $fillable = [
        'casual_position_opening_id',
        'user_id',
    ];

    public function opening(): BelongsTo
    {
        return $this->belongsTo(CasualPositionOpening::class, 'casual_position_opening_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

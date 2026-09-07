<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicianMonthlyMaintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'year',
        'month',
        'points',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'points' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

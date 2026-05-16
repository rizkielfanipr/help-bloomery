<?php

namespace App\Models;

use App\Enums\FormFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskFormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'helpdesk_form_template_id',
        'label',
        'type',
        'is_required',
        'hint',
        'placeholder',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'is_required' => 'boolean',
            'options' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HelpdeskFormTemplate::class, 'helpdesk_form_template_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    protected $fillable = [
        'reference', 'label', 'journal_entry_line_id', 'in_service_on', 'useful_months', 'residual_amount',
        'depreciation_account', 'charge_account', 'disposed_on', 'disposal_entry_id', 'registered_by',
    ];

    protected $casts = [
        'in_service_on' => 'date', 'disposed_on' => 'date', 'useful_months' => 'integer', 'residual_amount' => 'integer',
    ];

    public function acquisitionLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'source_id')->where('source_type', static::class);
    }
}

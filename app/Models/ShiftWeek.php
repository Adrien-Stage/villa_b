<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La semaine d'un service : envoyée ou non au personnel, et ce que chacun
 * a reçu au dernier envoi.
 */
class ShiftWeek extends Model
{
    protected $fillable = ['department_id', 'week_start', 'published_at', 'published_by', 'sent'];

    // « week_start » reste une chaîne AAAA-MM-JJ (lundi), comme ShiftAssignment::date.
    protected $casts = [
        'published_at' => 'datetime',
        'sent' => 'array',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}

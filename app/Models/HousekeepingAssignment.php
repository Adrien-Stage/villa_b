<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HousekeepingAssignment extends Model
{
    use HasFactory;

    /** Ménage de départ : la chambre suit le cycle jusqu'au contrôle. */
    public const KIND_DEPART = 'depart';

    /** Recouche : le client garde la chambre, elle reste occupée. */
    public const KIND_RECOUCHE = 'recouche';

    /** Types de problèmes signalés : technique (mise en maintenance) ou interne (géré par housekeeping). */
    public const ISSUE_TECHNIQUE = 'technique';
    public const ISSUE_INTERNE   = 'interne';

    protected $fillable = [
        'housekeeping_team_id',
        'room_id',
        'kind',
        'assigned_by',
        'reported_by',
        'status',
        'notes',
        'issue_notes',
        'issue_type',
        'assigned_at',
        'started_at',
        'completed_at',
        'reported_at',
        'started_by',
        'completed_by',
        'inspected_by',
        'inspected_at',
        'released_by',
        'released_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reported_at' => 'datetime',
        'inspected_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected $attributes = [
        'kind' => self::KIND_DEPART,
    ];

    public function isRecouche(): bool
    {
        return $this->kind === self::KIND_RECOUCHE;
    }

    public function isIssueTechnique(): bool
    {
        return $this->issue_type === self::ISSUE_TECHNIQUE;
    }

    public function isIssueInterne(): bool
    {
        return $this->issue_type === self::ISSUE_INTERNE;
    }

    /** Durée du nettoyage en minutes, une fois terminé. */
    public function dureeEnMinutes(): ?int
    {
        return $this->started_at && $this->completed_at
            ? (int) $this->started_at->diffInMinutes($this->completed_at)
            : null;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(HousekeepingTeam::class, 'housekeeping_team_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /** Relevés du mini-bar faits pendant ce passage. */
    public function minibarChecks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MinibarCheck::class);
    }
}

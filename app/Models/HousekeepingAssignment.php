<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HousekeepingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'housekeeping_team_id',
        'room_id',
        'assigned_by',
        'reported_by',
        'status',
        'notes',
        'issue_notes',
        'assigned_at',
        'started_at',
        'completed_at',
        'reported_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

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
}

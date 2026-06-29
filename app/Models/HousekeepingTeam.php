<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HousekeepingTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'leader_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'housekeeping_team_user')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(HousekeepingAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereIn('status', ['pending', 'in_progress']);
    }
}

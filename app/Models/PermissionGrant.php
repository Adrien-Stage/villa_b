<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un écart au catalogue des droits : ce que l'établissement ajoute ou retire
 * à un rôle, ou à une personne en particulier.
 */
class PermissionGrant extends Model
{
    public const SUJET_ROLE = 'role';
    public const SUJET_USER = 'user';

    public const EFFET_ALLOW = 'allow';
    public const EFFET_DENY  = 'deny';

    protected $fillable = [
        'subject_type', 'subject_id', 'permission', 'effect', 'granted_by', 'reason',
    ];

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeForRole(Builder $query, string $slug): Builder
    {
        return $query->where('subject_type', self::SUJET_ROLE)->where('subject_id', $slug);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('subject_type', self::SUJET_USER)->where('subject_id', (string) $userId);
    }
}

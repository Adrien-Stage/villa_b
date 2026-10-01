<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un écart au catalogue des droits : ce qu'on ajoute ou retire à un rôle, ou
 * à une personne en particulier.
 *
 * Deux autorités en posent, chacune dans sa couche : la console
 * d'orchestration (« erp ») et l'établissement (« etablissement »). Un refus
 * l'emporte toujours, quelle que soit sa couche. Une échéance borne un écart
 * dans le temps ; passé ce moment, il ne s'applique plus.
 */
class PermissionGrant extends Model
{
    public const SUJET_ROLE = 'role';
    public const SUJET_USER = 'user';

    public const EFFET_ALLOW = 'allow';
    public const EFFET_DENY  = 'deny';

    public const ORIGINE_ERP           = 'erp';
    public const ORIGINE_ETABLISSEMENT = 'etablissement';

    protected $fillable = [
        'subject_type', 'subject_id', 'permission', 'effect', 'scope', 'origin',
        'granted_by', 'reason', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** Écarts qui s'appliquent encore : sans échéance, ou échéance à venir. */
    public function scopeEnVigueur(Builder $query): Builder
    {
        return $query->where(static function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

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

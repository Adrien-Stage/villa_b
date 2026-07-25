<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /** Modules métier → libellé affiché dans la rubrique Utilisateurs. */
    public const MODULES = [
        'hebergement'  => 'Hébergement',
        'housekeeping' => 'Housekeeping',
        'restaurant'   => 'Restaurant',
        'boutique'     => 'Boutique',
        'economat'     => 'Économat',
        'comptabilite' => 'Comptabilité',
        'direction'    => 'Direction',
        'portail'      => 'Portail client',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'module',
        'icon',
        'sort_order',
        'is_assignable',
    ];

    protected $casts = [
        'sort_order'    => 'integer',
        'is_assignable' => 'boolean',
    ];

    /**
     * Rôles qu'un manager peut attribuer à son personnel. Lu depuis la table :
     * tout rôle ajouté au référentiel devient disponible sans toucher au code.
     */
    public function scopeAssignable($query)
    {
        return $query->where('is_assignable', true);
    }

    public function moduleLabel(): string
    {
        return self::MODULES[$this->module] ?? 'Autre';
    }

    /** Icône Lucide, avec un repli neutre pour un rôle créé sans icône. */
    public function iconName(): string
    {
        return $this->icon ?: 'user-round';
    }

    /**
     * Relation avec Tenant (multi-tenant)
     */
    /**
     * Relation avec Users (many-to-many pour multi-role support)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Vérifier si le rôle est global (non lié à un tenant)
     */
    public function isGlobal(): bool
    {
        return is_null($this->tenant_id);
    }

    /**
     * Vérifier si le rôle appartient à un tenant spécifique
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId || $this->isGlobal();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        ];

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

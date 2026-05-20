<?php
// app/Models/User.php (modifications à apporter au modèle existant)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User étendu pour le système hôtelier
 * 
 * ARCHITECTURE :
 * - Chaque user appartient à un tenant (établissement)
 * - Rôle stocké en enum string (plus lisible qu'integer)
 * - Utilise Sanctum pour API tokens (PWA offline sync)
 * 
 * SÉCURITÉ :
 * - Voir section 6.3 du CDC pour le RBAC
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Constantes pour les rôles (évite les magic strings)
    public const ROLE_ADMIN = 'admin';           // Directeur ONG / IT
    public const ROLE_MANAGER = 'manager';       // Directeur d'établissement
    public const ROLE_RECEPTION = 'reception';   // Agent de réception
    public const ROLE_HOUSEKEEPING = 'housekeeping'; // Femme/Valet de chambre

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',      // ← AJOUTÉ : Liaison à l'établissement
        'role',           // ← AJOUTÉ : RBAC
        'phone',
        'is_active',      // ← AJOUTÉ : Désactivation sans suppression
        'last_login_at',  // ← AJOUTÉ : Audit (section 4.1.2)
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Relation : L'utilisateur appartient à un établissement
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relation : L'utilisateur peut avoir plusieurs rôles (RBAC étendu)
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function housekeepingTeams(): BelongsToMany
    {
        return $this->belongsToMany(HousekeepingTeam::class, 'housekeeping_team_user')->withTimestamps();
    }

    public function ledHousekeepingTeams(): HasMany
    {
        return $this->hasMany(HousekeepingTeam::class, 'leader_id');
    }

    public function discussionConversations(): BelongsToMany
    {
        return $this->belongsToMany(DiscussionConversation::class, 'discussion_conversation_user')
            ->withPivot('last_read_at', 'archived_at', 'deleted_at', 'is_admin')
            ->withTimestamps();
    }

    public function discussionMessages(): HasMany
    {
        return $this->hasMany(DiscussionMessage::class);
    }

    /**
     * Helper : Vérifie si l'utilisateur a un rôle spécifique
     * Compatible avec l'ancien système (colonne role) et le nouveau (relation roles)
     */
    public function hasRole(string $role): bool
    {
        // Vérifier d'abord la nouvelle relation roles
        if ($this->roles()->where('slug', $role)->exists()) {
            return true;
        }

        // Fallback vers l'ancienne colonne role pour compatibilité
        return $this->role === $role;
    }

    /**
     * Helper : Vérifie si l'utilisateur a un rôle parmi une liste
     */
    public function hasAnyRole(array $roles): bool
    {
        // Vérifier d'abord la nouvelle relation roles
        if ($this->roles()->whereIn('slug', $roles)->exists()) {
            return true;
        }

        // Fallback vers l'ancienne colonne role pour compatibilité
        return in_array($this->role, $roles);
    }

    /**
     * Vérifie les permissions de niveau admin (cross-tenants)
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Vérifie l'accès financier (section 3 : Housekeeping sans accès financier)
     * Étendu pour inclure le comptable
     */
    public function canAccessFinancialData(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_RECEPTION,
            'cashier',      // Nouveau rôle caissier
            'accountant',   // Nouveau rôle comptable
        ]);
    }

    /**
     * Vérifie si l'utilisateur peut gérer les chambres
     */
    public function canManageRooms(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            'reception',
            'housekeeping_leader',
        ]);
    }

    /**
     * Vérifie si l'utilisateur peut gérer les réservations
     */
    public function canManageBookings(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            'reception',
        ]);
    }

    /**
     * Vérifie si l'utilisateur peut voir un tenant spécifique (multi-tenant isolation)
     */
    public function canViewTenant(?int $tenantId): bool
    {
        // Admin global peut voir tous les tenants
        if ($this->isAdmin()) {
            return true;
        }

        // Utilisateur doit appartenir au même tenant
        return $this->tenant_id === $tenantId;
    }

    /**
     * Check if the user is currently online.
     */
    public function isOnline(): bool
    {
        return \Illuminate\Support\Facades\Cache::has('user-is-online-' . $this->id);
    }
}

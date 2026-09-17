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
    public const ROLE_ECONOME = 'econome';       // Gestionnaire de l'économat / magasin central
    public const ROLE_CONTROLLER = 'controller'; // Contrôleur de gestion / Auditeur GRC

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'phone',
        'is_active',
        'last_login_at',
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
     * Relation : Département de rattachement organisationnel de l'employé
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relation : Surcharges granulaires de permissions par module (write, read, none)
     */
    public function modulePermissions(): HasMany
    {
        return $this->hasMany(UserModulePermission::class);
    }

    /**
     * Relation : L'utilisateur peut avoir plusieurs rôles (RBAC étendu)
     */
    public function roles(): BelongsToMany
    {
        // Le pivot porte le niveau d'accès (read / write) par rôle, donc par module.
        return $this->belongsToMany(Role::class)->withPivot('level')->withTimestamps();
    }

    /**
     * Retourne la permission explicite surchargée pour ce module ('write', 'read', 'none', ou null).
     */
    public function explicitModulePermission(string $module): ?string
    {
        $aliases = [
            'boutique'   => 'shop',
            'accounting' => 'ledger',
        ];
        $canonical = $aliases[$module] ?? $module;

        $override = $this->modulePermissions
            ->first(fn ($p) => in_array($p->module_key, [$module, $canonical], true));

        if ($override && in_array($override->access_level, ['write', 'read', 'none'], true)) {
            return $override->access_level;
        }

        return null;
    }

    /**
     * Détermine si l'utilisateur a accès au module.
     * Ordre d'évaluation :
     * 1. Surcharge explicite dans user_module_permissions ('none' => false, 'write'/'read' => true).
     * 2. Héritage des modules par défaut du département de rattachement.
     * 3. Fallback direction : admin / manager ont accès à tout par défaut (sauf si 'none' explicite).
     * 4. Fallback rôles pivot (table pivot roles).
     */
    public function hasModuleAccess(string $module): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $explicit = $this->explicitModulePermission($module);
        if ($explicit === 'none') {
            return false;
        }
        if ($explicit === 'write' || $explicit === 'read') {
            return true;
        }

        // 2. Héritage département
        if ($this->department_id && $this->department) {
            $aliases = ['boutique' => 'shop', 'accounting' => 'ledger'];
            $canonical = $aliases[$module] ?? $module;

            if ($this->department->hasModule($module) || $this->department->hasModule($canonical)) {
                return true;
            }
        }

        // 3. Admin et Manager ont accès par défaut aux modules non explicitement refusés
        if ($this->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        // 4. Fallback vers rôles métier historiques
        return $this->canAccessModule($module);
    }

    /**
     * Niveau d'accès effectif de l'utilisateur sur un module métier :
     * 'write', 'read', 'none', ou null.
     */
    public function moduleLevel(string $module): ?string
    {
        $explicit = $this->explicitModulePermission($module);
        if ($explicit !== null) {
            return $explicit;
        }

        // Héritage département
        if ($this->department_id && $this->department) {
            $aliases = ['boutique' => 'shop', 'accounting' => 'ledger'];
            $canonical = $aliases[$module] ?? $module;
            $deptLevel = $this->department->moduleDefaultLevel($module) ?: $this->department->moduleDefaultLevel($canonical);
            if ($deptLevel) {
                return $deptLevel;
            }
        }

        // Fallback rôles pivot
        $levels = $this->roles->where('module', $module)
            ->map(fn ($role) => $role->pivot->level ?: 'write');

        if ($levels->isEmpty()) {
            return null; // aucun rôle pivot sur ce module
        }

        return $levels->contains('write') ? 'write' : 'read';
    }

    /**
     * L'utilisateur peut-il écrire (agir) dans ce module ?
     */
    public function canWrite(string $module): bool
    {
        $explicit = $this->explicitModulePermission($module);
        if ($explicit === 'write') {
            return true;
        }
        if ($explicit === 'read' || $explicit === 'none') {
            return false;
        }

        // Admin et Manager écrivent partout sauf restriction explicite posée
        if ($this->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        // Héritage département
        if ($this->department_id && $this->department) {
            $aliases = ['boutique' => 'shop', 'accounting' => 'ledger'];
            $canonical = $aliases[$module] ?? $module;
            $deptLevel = $this->department->moduleDefaultLevel($module) ?: $this->department->moduleDefaultLevel($canonical);
            if ($deptLevel) {
                return $deptLevel === 'write';
            }
        }

        return $this->moduleLevel($module) !== 'read';
    }

    /**
     * L'utilisateur a-t-il un accès (lecture ou écriture) à ce module ?
     */
    public function canAccessModule(string $module): bool
    {
        if ($this->explicitModulePermission($module) === 'none') {
            return false;
        }

        return $this->moduleLevel($module) !== null;
    }

    /**
     * Abonnements Web Push de l'utilisateur (un par navigateur/appareil).
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
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
     * Scope : les utilisateurs porteurs d'un des rôles donnés, quel que soit le
     * système utilisé (ancienne colonne role ou relation roles).
     */
    public function scopeHavingRole($query, array $slugs)
    {
        return $query->where(function ($q) use ($slugs) {
            $q->whereIn('role', $slugs)
                ->orWhereHas('roles', fn ($r) => $r->whereIn('slug', $slugs));
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Prises de service en salle de ce serveur.
     */
    public function restaurantShifts(): HasMany
    {
        return $this->hasMany(RestaurantShift::class);
    }

    /**
     * Le serveur est-il actuellement en service ? (prise de service ouverte)
     */
    public function isOnRestaurantDuty(): bool
    {
        return $this->restaurantShifts()->whereNull('closed_at')->exists();
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

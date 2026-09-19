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

    protected $attributes = [
        'is_active' => true,
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
     * Mappage des modules et de leurs alias dans l'application.
     */
    public static function moduleAliases(string $module): array
    {
        $map = [
            'boutique'     => ['boutique', 'shop'],
            'shop'         => ['shop', 'boutique'],
            'comptabilite' => ['comptabilite', 'accounting', 'ledger'],
            'accounting'   => ['accounting', 'comptabilite', 'ledger'],
            'ledger'       => ['ledger', 'comptabilite', 'accounting'],
            'hebergement'  => ['hebergement', 'reservations', 'clients'],
            'reservations' => ['reservations', 'hebergement', 'clients'],
            'clients'      => ['clients', 'hebergement', 'reservations'],
            'rh'           => ['rh', 'utilisateurs'],
            'utilisateurs' => ['utilisateurs', 'rh'],
            'it'           => ['it', 'parametres', 'api', 'pwa'],
            'parametres'   => ['parametres', 'it'],
            'qualite'      => ['qualite', 'grc'],
            'grc'          => ['grc', 'qualite'],
        ];

        return $map[$module] ?? [$module];
    }

    /**
     * Matrice des modules par défaut pour les rôles métiers historiques.
     */
    protected static array $legacyRoleModules = [
        'admin'               => ['*'],
        'manager'             => ['*'],
        'reception'           => ['hebergement', 'reservations', 'clients', 'website', 'discussions', 'ai', 'comptabilite'],
        'cashier'             => ['hebergement', 'reservations', 'restaurant', 'boutique', 'shop', 'comptabilite', 'accounting', 'ledger', 'discussions'],
        'housekeeping'        => ['housekeeping', 'hebergement', 'economat', 'discussions'],
        'housekeeping_leader' => ['housekeeping', 'hebergement', 'economat', 'discussions'],
        'housekeeping_staff'  => ['housekeeping', 'hebergement', 'discussions'],
        'restaurant_chief'    => ['restaurant', 'portail', 'economat', 'discussions'],
        'restaurant_staff'    => ['restaurant', 'portail', 'discussions'],
        'restaurant_cook'     => ['restaurant', 'discussions'],
        'shop_manager'        => ['boutique', 'shop', 'economat', 'discussions'],
        'shop_cashier'        => ['boutique', 'shop', 'discussions'],
        'econome'             => ['economat', 'comptabilite', 'discussions'],
        'accountant'          => ['comptabilite', 'ledger', 'accounting', 'economat', 'analytics', 'grc', 'discussions'],
        'controller'          => ['comptabilite', 'ledger', 'accounting', 'analytics', 'grc', 'discussions'],
        'rh_manager'          => ['utilisateurs', 'grc', 'discussions'],
        'it_support'          => ['parametres', 'api', 'pwa', 'ai', 'website', 'discussions'],
        'quality_auditor'     => ['grc', 'clients', 'housekeeping', 'restaurant', 'discussions'],
        'customer_guest'      => ['portail'],
    ];

    /**
     * Retourne les modules autorisés pour un slug de rôle donné.
     */
    public static function defaultModulesForRole(string $role): array
    {
        if (isset(self::$legacyRoleModules[$role])) {
            return self::$legacyRoleModules[$role];
        }

        try {
            $roleRecord = Role::where('slug', $role)->first();
            if ($roleRecord && $roleRecord->module) {
                return self::moduleAliases($roleRecord->module);
            }
        } catch (\Throwable) {
            // Ignorer si la table n'est pas encore migrée
        }

        return [];
    }

    /**
     * Retourne la permission explicite surchargée pour ce module ('write', 'read', 'none', ou null).
     */
    public function explicitModulePermission(string $module): ?string
    {
        $aliases = self::moduleAliases($module);

        $override = $this->modulePermissions
            ->first(fn ($p) => in_array($p->module_key, $aliases, true));

        if ($override && in_array($override->access_level, ['write', 'read', 'none'], true)) {
            return $override->access_level;
        }

        return null;
    }

    /**
     * Détermine si l'utilisateur a accès au module.
     */
    public function hasModuleAccess(string $module): bool
    {
        return $this->canAccessModule($module);
    }

    /**
     * Niveau d'accès effectif de l'utilisateur sur un module métier :
     * 'write', 'read', 'none', ou null.
     */
    public function moduleLevel(string $module): ?string
    {
        if ($this->is_active === false) {
            return 'none';
        }

        // 1. Surcharge explicite utilisateur
        $explicit = $this->explicitModulePermission($module);
        if ($explicit !== null) {
            return $explicit;
        }

        $aliases = self::moduleAliases($module);

        // 2. Rôles pivot assignés (table pivot role_user)
        $hasPivotRoles = $this->relationLoaded('roles') ? $this->roles->isNotEmpty() : ($this->exists && $this->roles()->exists());
        if ($hasPivotRoles) {
            $matchingPivot = $this->roles->filter(function ($role) use ($aliases) {
                return in_array($role->module, $aliases, true)
                    || !empty(array_intersect(self::defaultModulesForRole($role->slug), $aliases));
            });

            if ($matchingPivot->isNotEmpty()) {
                $levels = $matchingPivot->map(fn ($r) => $r->pivot->level ?: 'write');
                return $levels->contains('write') ? 'write' : 'read';
            }
        }

        // 3. Direction (admin / manager ont accès à tout en écriture par défaut)
        if ($this->hasAnyRole(['admin', 'manager']) || in_array($this->role, ['admin', 'manager'], true)) {
            return 'write';
        }

        // 4. Héritage département
        if ($this->department_id && $this->department) {
            foreach ($aliases as $alias) {
                $deptLevel = $this->department->moduleDefaultLevel($alias);
                if ($deptLevel) {
                    return $deptLevel;
                }
            }
        }

        // 5. Rôle historique (colonne users.role)
        if ($this->role) {
            $allowed = self::defaultModulesForRole($this->role);
            if (in_array('*', $allowed, true) || !empty(array_intersect($allowed, $aliases))) {
                return 'write';
            }
        }

        return null;
    }

    /**
     * L'utilisateur peut-il écrire (agir) dans ce module ?
     */
    public function canWrite(string $module): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        return $this->moduleLevel($module) === 'write';
    }

    /**
     * L'utilisateur a-t-il un accès (lecture ou écriture) à ce module ?
     */
    public function canAccessModule(string $module): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        $level = $this->moduleLevel($module);

        return $level !== null && $level !== 'none';
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
        // 1. Vérifier si la relation est déjà chargée
        if ($this->relationLoaded('roles')) {
            if ($this->roles->contains('slug', $role)) {
                return true;
            }
        } elseif ($this->exists && $this->roles()->where('slug', $role)->exists()) {
            return true;
        }

        // 2. Fallback vers l'ancienne colonne role pour compatibilité
        return $this->role === $role;
    }

    /**
     * Helper : Vérifie si l'utilisateur a un rôle parmi une liste
     */
    public function hasAnyRole(array $roles): bool
    {
        // 1. Vérifier si la relation est déjà chargée
        if ($this->relationLoaded('roles')) {
            if ($this->roles->whereIn('slug', $roles)->isNotEmpty()) {
                return true;
            }
        } elseif ($this->exists && $this->roles()->whereIn('slug', $roles)->exists()) {
            return true;
        }

        // 2. Fallback vers l'ancienne colonne role pour compatibilité
        return in_array($this->role, $roles, true);
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

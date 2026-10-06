<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Un membre du personnel de l'établissement.
 *
 * Ses droits viennent de ses affectations de rôles (role_user, chacune en
 * écriture ou en lecture seule), des exceptions posées sur un rôle ou sur lui
 * (PermissionGrant), et, pour l'administrateur, d'une intervention déclarée.
 * PermissionResolver décide ; ce modèle ne fait que dire quels rôles la
 * personne détient.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';           // Service informatique de l'hôtel
    public const ROLE_MANAGER = 'manager';       // Directeur d'établissement
    public const ROLE_RECEPTION = 'reception';   // Réceptionniste
    public const ROLE_ECONOME = 'econome';       // Chef économe
    public const ROLE_CONTROLLER = 'controller'; // Contrôleur de gestion

    /** Rôle à affecter à l'enregistrement, posé par l'attribut « role ». */
    private ?string $roleAAffecter = null;

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
        'must_change_password' => 'boolean',
    ];

    /**
     * Relation : Département de rattachement organisationnel de l'employé
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Restaurants auxquels cette personne est affectée. Le personnel d'un
     * restaurant ne voit que les siens ; la direction et le contrôle les
     * voient tous (RestaurantContext).
     */
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(PointOfSale::class)->withTimestamps();
    }

    /**
     * Affectations de rôles. Le pivot porte le niveau : null ou « write »
     * pour le rôle entier, « read » pour le rôle tenu en lecture seule.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('level')->withTimestamps();
    }

    /**
     * « role » : le rôle principal, c'est-à-dire la première affectation.
     *
     * La colonne users.role a disparu ; l'attribut reste pour l'affichage et
     * pour créer un compte en une ligne : écrire « role » affecte ce rôle à
     * l'enregistrement, sans retirer les autres.
     */
    public function getRoleAttribute(): ?string
    {
        return $this->roleAAffecter ?? ($this->rolesDetenus()[0] ?? null);
    }

    public function setRoleAttribute(?string $slug): void
    {
        $this->roleAAffecter = $slug === '' ? null : $slug;
    }

    protected static function booted(): void
    {
        static::saved(function (self $user): void {
            if ($user->roleAAffecter === null) {
                return;
            }

            $slug = $user->roleAAffecter;
            $user->roleAAffecter = null;

            $role = Role::where('slug', $slug)->first();
            if ($role === null && ($enregistrement = \App\Support\RoleCatalog::enregistrement($slug)) !== null) {
                $role = Role::create($enregistrement);
            }

            if ($role !== null) {
                $user->roles()->syncWithoutDetaching([$role->id]);
                $user->unsetRelation('roles');
            }
        });
    }

    /**
     * La personne détient-elle au moins un droit dans ce module ? C'est ce
     * qui décide qu'une rubrique du menu lui apparaît. Les droits seuls en
     * décident (PermissionCatalog::droitsDuModule).
     */
    public function hasModuleAccess(string $module): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        $droits = \App\Support\PermissionCatalog::droitsDuModule($module);

        // Un module sans droit catalogué (discussions, assistant) est ouvert à
        // tout le personnel.
        if ($droits === []) {
            return true;
        }

        $resolveur = app(\App\Services\PermissionResolver::class);
        $this->loadMissing('roles');

        foreach ($droits as $droit) {
            if ($resolveur->allows($this, $droit)) {
                return true;
            }
        }

        return false;
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
     * Rôles que la personne détient : ses affectations, dans l'ordre où elles
     * ont été faites.
     *
     * @return list<string>
     */
    public function rolesDetenus(): array
    {
        $affectations = $this->relationLoaded('roles')
            ? $this->roles->pluck('slug')->all()
            : ($this->exists ? $this->roles()->orderBy('role_user.id')->pluck('slug')->all() : []);

        return array_values(array_unique($affectations));
    }

    /** Détient-il ce rôle ? Voir rolesDetenus(). */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->rolesDetenus(), true);
    }

    /**
     * Helper : Vérifie si l'utilisateur a un rôle parmi une liste
     */
    public function hasAnyRole(array $roles): bool
    {
        return array_intersect($this->rolesDetenus(), $roles) !== [];
    }

    /**
     * Exerce-t-il l'un de ces rôles, directement ou par un rôle qui l'inclut ?
     *
     * Un chef fait le travail de ses membres (RoleCatalog) : le chef de
     * réception exerce la réception, le responsable de restaurant le service
     * en salle et la caisse. À employer pour les écrans et les règles qui
     * s'adressent à une fonction, là où hasAnyRole() ne voit que le rôle
     * attribué.
     */
    public function exerce(array $roles): bool
    {
        return array_intersect(\App\Support\RoleCatalog::developper($this->rolesDetenus()), $roles) !== [];
    }

    /** Scope : les personnes affectées à l'un de ces rôles. */
    public function scopeHavingRole($query, array $slugs)
    {
        return $query->whereHas('roles', fn ($r) => $r->whereIn('slug', $slugs));
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
     * L'administrateur de l'établissement — son service informatique.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /** Le compte technique du support de l'éditeur (mode assistance). */
    public function isSupport(): bool
    {
        return $this->hasRole(\App\Support\RoleCatalog::SUPPORT);
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

    /**
     * Nom utilisé pour la signature manuscrite stylisée.
     * Si l'utilisateur possède plusieurs noms (ex: "Boris Setate"), on extrait l'un de ses noms
     * (le prénom ou premier mot usuel) au format Titre pour un rendu manuscrit fluide.
     */
    public function signatureName(): string
    {
        return self::extractSignatureName($this->name);
    }

    /**
     * Extrait l'un des noms pour la signature automatique manuscrite.
     */
    public static function extractSignatureName(?string $fullName): string
    {
        $raw = trim($fullName ?? '');
        if ($raw === '') {
            return '';
        }

        // Nettoyage éventuel des civilités
        $cleaned = preg_replace('/^(m\.|mr\.|dr\.|mme\.|mlle\.)\s+/iu', '', $raw);
        $parts = array_values(array_filter(preg_split('/\s+/', trim($cleaned))));
        if (empty($parts)) {
            $parts = array_values(array_filter(preg_split('/\s+/', $raw)));
        }

        if (empty($parts)) {
            return '';
        }

        // Prend un des noms de l'utilisateur s'il en a deux ou plus
        return mb_convert_case($parts[0], MB_CASE_TITLE, 'UTF-8');
    }
}

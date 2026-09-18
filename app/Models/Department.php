<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'icon',
        'accent',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    /**
     * Employés rattachés à ce département.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Clés des modules associés par défaut à ce département avec leur niveau ('write' ou 'read').
     */
    public function defaultModules(): array
    {
        return DB::table('department_module')
            ->where('department_id', $this->id)
            ->pluck('default_level', 'module_key')
            ->toArray();
    }

    /**
     * Vérifie si le département a accès par défaut à un module donné.
     */
    public function hasModule(string $module): bool
    {
        return DB::table('department_module')
            ->where('department_id', $this->id)
            ->where('module_key', $module)
            ->exists();
    }

    /**
     * Retourne le niveau d'accès par défaut ('write' ou 'read') ou null.
     */
    public function moduleDefaultLevel(string $module): ?string
    {
        return DB::table('department_module')
            ->where('department_id', $this->id)
            ->where('module_key', $module)
            ->value('default_level');
    }

    /**
     * Résout la liste des rôles assignables et leurs niveaux d'accès par défaut ('write' ou 'read')
     * pour la pré-sélection automatique et dynamique dans les formulaires de création / édition.
     *
     * @param \Illuminate\Support\Collection<int, Role> $assignableRoles
     * @return array{roles: string[], levels: array<string, string>}
     */
    public function resolveMatchingRoles($assignableRoles): array
    {
        $defaultMods = $this->defaultModules();
        $code = strtoupper((string) ($this->code ?? ''));
        $slug = (string) ($this->slug ?? '');

        // 1. Direction Générale : accès complet à l'ensemble des rôles & modules
        if ($code === 'DIR' || $slug === 'direction_generale' || isset($defaultMods['*'])) {
            $allSlugs = $assignableRoles->pluck('slug')->all();
            return [
                'roles'  => $allSlugs,
                'levels' => array_fill_keys($allSlugs, 'write'),
            ];
        }

        // 2. Périmètre métier canonique strict pour les 8 départements standards
        $canonicalRoles = [
            'REC' => ['reception', 'cashier'],
            'HSK' => ['housekeeping_leader', 'housekeeping_staff'],
            'FNB' => ['restaurant_chief', 'restaurant_staff', 'restaurant_cook'],
            'BTQ' => ['shop_manager', 'shop_cashier'],
            'FIN' => ['accountant', 'cashier'],
            'RH'  => ['rh_manager'],
            'IT'  => ['it_support'],
            'QLT' => ['quality_auditor'],
        ];

        if (isset($canonicalRoles[$code])) {
            $matching = [];
            $levels = [];
            foreach ($canonicalRoles[$code] as $rSlug) {
                if ($assignableRoles->contains('slug', $rSlug)) {
                    $matching[] = $rSlug;
                    $levels[$rSlug] = 'write';
                }
            }
            if (!empty($matching)) {
                return ['roles' => $matching, 'levels' => $levels];
            }
        }

        // 3. Fallback dynamique pour départements sur-mesure créés dans l'ERP
        $moduleToRoleSlugs = [
            'hebergement'  => ['reception', 'cashier'],
            'reservations' => ['reception', 'cashier'],
            'clients'      => ['reception', 'cashier'],
            'housekeeping' => ['housekeeping_leader', 'housekeeping_staff'],
            'restaurant'   => ['restaurant_chief', 'restaurant_staff', 'restaurant_cook'],
            'boutique'     => ['shop_manager', 'shop_cashier'],
            'shop'         => ['shop_manager', 'shop_cashier'],
            'economat'     => ['econome'],
            'comptabilite' => ['accountant'],
            'ledger'       => ['accountant'],
            'accounting'   => ['accountant'],
            'rh'           => ['rh_manager'],
            'utilisateurs' => ['rh_manager'],
            'it'           => ['it_support'],
            'parametres'   => ['it_support'],
            'qualite'      => ['quality_auditor'],
            'grc'          => ['quality_auditor'],
        ];

        $matchingRoles = [];
        $roleLevels = [];

        foreach ($defaultMods as $modKey => $level) {
            $lvl = in_array($level, ['write', 'read'], true) ? $level : 'write';
            if (isset($moduleToRoleSlugs[$modKey])) {
                foreach ($moduleToRoleSlugs[$modKey] as $rSlug) {
                    if ($assignableRoles->contains('slug', $rSlug) && !in_array($rSlug, $matchingRoles, true)) {
                        $matchingRoles[] = $rSlug;
                        $roleLevels[$rSlug] = $lvl;
                    }
                }
            }
        }

        return [
            'roles'  => array_values(array_unique($matchingRoles)),
            'levels' => $roleLevels,
        ];
    }
}

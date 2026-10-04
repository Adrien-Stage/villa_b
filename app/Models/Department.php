<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Rôles que le formulaire coche d'office quand on rattache une personne à
     * ce département, d'après son code. Une commodité de saisie : le
     * département ne donne aucun droit, et chaque case reste décochable.
     *
     * @param  \Illuminate\Support\Collection<int, Role>  $assignableRoles
     * @return array{roles: list<string>, levels: array<string, string>}
     */
    public function resolveMatchingRoles($assignableRoles): array
    {
        $parCode = [
            'DIR' => ['manager'],
            'REC' => ['reception'],
            'HSK' => ['housekeeping_staff'],
            'FNB' => ['restaurant_staff'],
            'BTQ' => ['shop_cashier'],
            'FIN' => ['accountant'],
            'QLT' => ['quality_auditor'],
        ];

        $roles = array_values(array_filter(
            $parCode[strtoupper((string) $this->code)] ?? [],
            static fn (string $slug): bool => $assignableRoles->contains('slug', $slug)
        ));

        return ['roles' => $roles, 'levels' => array_fill_keys($roles, 'write')];
    }
}

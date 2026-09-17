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
}

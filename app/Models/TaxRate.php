<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Taux de taxe applicable à une vente ou à un achat.
 *
 * Le taux est exprimé en points de base — 1 pdb = 0,01 %, donc 19,25 % => 1925
 * — pour qu'aucun flottant n'intervienne dans la chaîne de calcul comptable.
 */
class TaxRate extends Model
{
    /** Code du taux appliqué par défaut aux ventes. */
    public const CODE_STANDARD = 'STANDARD';

    /** Code du taux appliqué aux opérations exonérées (export, international). */
    public const CODE_EXEMPT = 'EXONERE';

    protected $fillable = [
        'code',
        'label',
        'rate_basis_points',
        'surtax_basis_points',
        'collected_account',
        'deductible_account',
        'surtax_account',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rate_basis_points'   => 'integer',
        'surtax_basis_points' => 'integer',
        'is_default'          => 'boolean',
        'is_active'           => 'boolean',
    ];

    /** Taux en pourcentage, pour l'affichage uniquement — jamais pour un calcul. */
    public function percentage(): float
    {
        return $this->rate_basis_points / 100;
    }

    public function isExempt(): bool
    {
        return $this->rate_basis_points === 0;
    }

    /** Le taux appliqué par défaut, ou l'exonération si rien n'est configuré. */
    public static function default(): ?self
    {
        return static::query()->where('is_active', true)->where('is_default', true)->first()
            ?? static::query()->where('is_active', true)->first();
    }
}

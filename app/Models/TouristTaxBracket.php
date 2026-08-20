<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ligne du barème de la taxe de séjour, pour un classement d'établissement.
 *
 * Montant par nuitée, en centimes FCFA. Cette taxe est collectée pour le
 * compte de la fiscalité locale : elle constitue une dette, jamais un produit.
 */
class TouristTaxBracket extends Model
{
    /** Classements retenus par le barème. */
    public const CLASSIFICATIONS = [
        'non_classe' => 'Non classé',
        '1'          => '1 étoile',
        '2'          => '2 étoiles',
        '3'          => '3 étoiles',
        '4'          => '4 étoiles',
        '5'          => '5 étoiles',
    ];

    protected $fillable = [
        'classification',
        'label',
        'amount_per_night',
        'is_active',
    ];

    protected $casts = [
        'amount_per_night' => 'integer',
        'is_active'        => 'boolean',
    ];

    public static function forClassification(?string $classification): ?self
    {
        if ($classification === null || $classification === '') {
            return null;
        }

        return static::query()
            ->where('is_active', true)
            ->where('classification', $classification)
            ->first();
    }
}

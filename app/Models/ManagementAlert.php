<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une dérive de gestion constatée (AlertesDeGestion) : un dépassement de
 * budget, des recettes en retard, un coût matière trop haut, une marge qui
 * baisse. Une par règle, centre et mois ; sa valeur suit le mois en cours.
 */
class ManagementAlert extends Model
{
    public const RULE_COST_OVERRUN = 'depassement_charges';
    public const RULE_REVENUE_SHORTFALL = 'recettes_en_retard';
    public const RULE_FOOD_COST = 'cout_matiere';
    public const RULE_MARGIN_DROP = 'marge_en_baisse';

    public const RULES = [
        self::RULE_COST_OVERRUN => 'Charges au-dessus du budget',
        self::RULE_REVENUE_SHORTFALL => 'Recettes en retard sur le budget',
        self::RULE_FOOD_COST => 'Coût matière au-dessus du seuil',
        self::RULE_MARGIN_DROP => 'Marge en baisse',
    ];

    protected $fillable = ['rule', 'center', 'period', 'value', 'reference', 'message'];

    protected $casts = ['value' => 'integer', 'reference' => 'integer'];

    public function ruleLabel(): string
    {
        return self::RULES[$this->rule] ?? $this->rule;
    }
}

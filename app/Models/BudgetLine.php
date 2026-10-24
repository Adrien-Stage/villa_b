<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du budget : un centre, une nature (produits ou charges), douze
 * montants mensuels en centimes FCFA. Le contrôleur de gestion et le
 * responsable financier l'établissent (Pilotage › Budget) ; le réalisé vient
 * de la comptabilité (RealiseDeGestion).
 */
class BudgetLine extends Model
{
    /** Charges de structure (loyer, assurances, administration) : le non-ventilé de l'analytique. */
    public const CENTER_STRUCTURE = 'structure';

    public const NATURE_REVENUE = 'revenue';
    public const NATURE_COST = 'cost';

    public const NATURES = [
        self::NATURE_REVENUE => 'Produits',
        self::NATURE_COST => 'Charges',
    ];

    protected $fillable = [
        'year', 'center', 'nature',
        'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'm1' => 'integer', 'm2' => 'integer', 'm3' => 'integer', 'm4' => 'integer',
        'm5' => 'integer', 'm6' => 'integer', 'm7' => 'integer', 'm8' => 'integer',
        'm9' => 'integer', 'm10' => 'integer', 'm11' => 'integer', 'm12' => 'integer',
    ];

    /** Les centres du budget : ceux de l'analytique, plus la structure. */
    public static function centres(): array
    {
        return JournalEntryLine::CENTERS + [self::CENTER_STRUCTURE => 'Structure (non ventilé)'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function mois(int $mois): int
    {
        return (int) $this->getAttribute('m' . $mois);
    }

    /** @return array<int, int> mois (1 à 12) => montant */
    public function montants(): array
    {
        $montants = [];
        foreach (range(1, 12) as $mois) {
            $montants[$mois] = $this->mois($mois);
        }

        return $montants;
    }
}

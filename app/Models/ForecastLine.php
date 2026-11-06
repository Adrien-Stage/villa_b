<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de prévision d'atterrissage (Forecast) : un centre, une nature, douze
 * montants mensuels en centimes FCFA.
 *
 * Distincte du Budget d'engagement initial :
 * - Le Budget est fixé en début d'exercice et scellé par le RAF.
 * - La Prévision est actualisée en cours d'année (atterrissage) par le contrôleur
 *   de gestion (mois réalisés + mois révisés) et validée par le RAF.
 */
class ForecastLine extends Model
{
    public const CENTER_STRUCTURE = 'structure';

    public const NATURE_REVENUE = 'revenue';
    public const NATURE_COST = 'cost';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';

    public const NATURES = [
        self::NATURE_REVENUE => 'Produits',
        self::NATURE_COST => 'Charges',
    ];

    public const STATUSES = [
        self::STATUS_DRAFT => 'Brouillon de prévision',
        self::STATUS_VALIDATED => 'Prévision validée par le RAF',
    ];

    protected $fillable = [
        'year', 'center', 'nature',
        'status', 'validated_by', 'validated_at',
        'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'validated_at' => 'datetime',
        'm1' => 'integer', 'm2' => 'integer', 'm3' => 'integer', 'm4' => 'integer',
        'm5' => 'integer', 'm6' => 'integer', 'm7' => 'integer', 'm8' => 'integer',
        'm9' => 'integer', 'm10' => 'integer', 'm11' => 'integer', 'm12' => 'integer',
    ];

    /** Les centres : ceux de l'analytique, plus la structure. */
    public static function centres(): array
    {
        return JournalEntryLine::CENTERS + [self::CENTER_STRUCTURE => 'Structure (non ventilé)'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
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

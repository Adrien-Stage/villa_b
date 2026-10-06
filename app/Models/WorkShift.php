<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un quart de travail de l'hôtel : « Jour, 07:00 – 19:00 ».
 *
 * Défini par la direction pour tout l'établissement. Un quart dont la fin
 * précède le début traverse minuit : il commence un jour et finit le
 * lendemain.
 */
class WorkShift extends Model
{
    protected $fillable = ['name', 'starts_at', 'ends_at', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDansLOrdre(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('starts_at');
    }

    /** « 07:00 » : l'heure sans les secondes que la base peut ajouter. */
    public function debut(): string
    {
        return substr((string) $this->starts_at, 0, 5);
    }

    public function fin(): string
    {
        return substr((string) $this->ends_at, 0, 5);
    }

    public function traverseMinuit(): bool
    {
        return $this->fin() <= $this->debut();
    }

    /** « 07:00 – 19:00 », « 19:00 – 07:00 (+1) ». */
    public function horaire(): string
    {
        return $this->debut() . ' – ' . $this->fin() . ($this->traverseMinuit() ? ' (+1)' : '');
    }

    /** Durée en heures. */
    public function duree(): float
    {
        [$hd, $md] = array_map('intval', explode(':', $this->debut()));
        [$hf, $mf] = array_map('intval', explode(':', $this->fin()));
        $minutes = ($hf * 60 + $mf) - ($hd * 60 + $md);

        return ($minutes <= 0 ? $minutes + 24 * 60 : $minutes) / 60;
    }

    /** Début du quart qui commence ce jour-là. */
    public function debutLe(CarbonInterface $jour): CarbonImmutable
    {
        return CarbonImmutable::parse($jour->format('Y-m-d') . ' ' . $this->debut());
    }

    /** Fin du quart qui commence ce jour-là : le lendemain s'il traverse minuit. */
    public function finLe(CarbonInterface $jour): CarbonImmutable
    {
        $fin = CarbonImmutable::parse($jour->format('Y-m-d') . ' ' . $this->fin());

        return $this->traverseMinuit() ? $fin->addDay() : $fin;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unité de mesure & de stockage des articles de l'établissement :
 * - Nom long (ex: Kilogramme, Litre, Pièce)
 * - Nom court / symbole (ex: kg, l, pcs)
 * - Valeur / facteur et unité de référence (ex: 1 kg = 1000 g, 1 pièce = 1 pcs)
 */
class StockUnit extends Model
{
    // Effacé par un bouton « Supprimer », il passe par la corbeille.
    use \App\Models\Concerns\VaALaCorbeille;

    protected $fillable = [
        'name',
        'short_name',
        'factor',
        'reference_unit_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'factor'     => 'float',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Cache mémoire des symboles pour les affichages rapides de tableaux. */
    protected static array $symbolCache = [];

    public function referenceUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference_unit_id');
    }

    public function subUnits(): HasMany
    {
        return $this->hasMany(self::class, 'reference_unit_id');
    }

    public function scopeDansLOrdre(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Unité de base absolue de la chaîne de référence (ex: Gramme pour Kilogramme).
     */
    public function baseUnit(): self
    {
        if (! $this->reference_unit_id || $this->reference_unit_id === $this->id) {
            return $this;
        }

        $visited = [$this->id];
        $current = $this;

        while ($current->reference_unit_id && $current->reference_unit_id !== $current->id) {
            $next = $current->referenceUnit;
            if (! $next || in_array($next->id, $visited, true)) {
                break;
            }
            $visited[] = $next->id;
            $current = $next;
        }

        return $current;
    }

    /**
     * Facteur cumulé pour convertir une quantité de cette unité vers son unité de base absolue.
     * Exemple : pour Kilogramme (ref: Gramme, facteur: 1000) -> 1000.0
     */
    public function factorToBase(): float
    {
        $factor = max(0.0000001, (float) ($this->factor ?: 1.0));

        if (! $this->reference_unit_id || $this->reference_unit_id === $this->id) {
            return 1.0;
        }

        $visited = [$this->id];
        $cumulative = $factor;
        $current = $this;

        while ($current->reference_unit_id && $current->reference_unit_id !== $current->id) {
            $next = $current->referenceUnit;
            if (! $next || in_array($next->id, $visited, true)) {
                break;
            }
            $visited[] = $next->id;
            $cumulative *= max(0.0000001, (float) ($next->factor ?: 1.0));
            $current = $next;
        }

        return $cumulative;
    }

    /**
     * Libellé d'équivalence clair (ex: « 1 kg = 1 000 g » ou « 1 pcs (Unité de base) »).
     */
    public function equivalenceLabel(): string
    {
        $court = $this->short_name ?: $this->name;

        if (! $this->reference_unit_id || $this->reference_unit_id === $this->id) {
            return "1 {$court} (Unité de base)";
        }

        $refCourt = $this->referenceUnit?->short_name ?: $this->referenceUnit?->name ?: 'unité';
        $facteur = (float) ($this->factor ?: 1.0);
        $facteurFormatte = rtrim(rtrim(number_format($facteur, 4, ',', ' '), '0'), ',');

        return "1 {$court} = {$facteurFormatte} {$refCourt}";
    }

    /**
     * Retrouve une unité par son nom, son nom court ou son identifiant.
     */
    public static function findUnit(int|string|null $valeur): ?self
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        if (is_numeric($valeur)) {
            $trouve = self::find((int) $valeur);
            if ($trouve) {
                return $trouve;
            }
        }

        $cle = mb_strtolower(trim((string) $valeur));

        return self::all()->first(function (self $u) use ($cle) {
            return mb_strtolower($u->short_name) === $cle
                || mb_strtolower($u->name) === $cle;
        });
    }

    /**
     * Retourne le nom court / symbole d'une unité à partir de n'importe quelle désignation.
     * Idéal pour les colonnes « Unité » des tableaux et des imprimables.
     */
    public static function symbolFor(?string $nomOuSymbole): string
    {
        $valeur = trim((string) $nomOuSymbole);
        if ($valeur === '') {
            return '—';
        }

        $cle = mb_strtolower($valeur);

        if (isset(self::$symbolCache[$cle])) {
            return self::$symbolCache[$cle];
        }

        // Recherche rapide
        $unite = self::findUnit($valeur);
        $symbole = $unite ? ($unite->short_name ?: $unite->name) : $valeur;

        self::$symbolCache[$cle] = $symbole;

        return $symbole;
    }

    /**
     * Taux de conversion de $uniteFrom vers $uniteTo.
     * Exemple : conversionRate('kg', 'g') = 1000.0 (1 kg = 1000 g).
     * Retourne null si les unités n'appartiennent pas à la même grandeur / base.
     */
    public static function conversionRate(string|self|null $from, string|self|null $to): ?float
    {
        if (! $from || ! $to) {
            return null;
        }

        $uA = $from instanceof self ? $from : self::findUnit($from);
        $uB = $to instanceof self ? $to : self::findUnit($to);

        if (! $uA || ! $uB) {
            // Mappage de secours sur les grandeurs physiques standards
            $secours = [
                'kg' => ['masse', 1000.0], 'g' => ['masse', 1.0],
                'l' => ['volume', 1000.0], 'litre' => ['volume', 1000.0],
                'cl' => ['volume', 10.0], 'ml' => ['volume', 1.0],
                'pcs' => ['piece', 1.0], 'pièce' => ['piece', 1.0],
            ];
            $cleA = mb_strtolower(trim(is_string($from) ? $from : $from->short_name));
            $cleB = mb_strtolower(trim(is_string($to) ? $to : $to->short_name));
            if ($cleA === $cleB) {
                return 1.0;
            }
            if (isset($secours[$cleA], $secours[$cleB]) && $secours[$cleA][0] === $secours[$cleB][0]) {
                return (float) $secours[$cleA][1] / (float) $secours[$cleB][1];
            }
            return null;
        }

        if ($uA->id === $uB->id) {
            return 1.0;
        }

        $baseA = $uA->baseUnit();
        $baseB = $uB->baseUnit();

        if ($baseA->id !== $baseB->id) {
            return null;
        }

        $facteurA = $uA->factorToBase();
        $facteurB = $uB->factorToBase();

        if ($facteurB <= 0) {
            return null;
        }

        return $facteurA / $facteurB;
    }

    /**
     * Recalcule le coût unitaire proportionnel selon les unités.
     * Exemple : 1 kg coûte 5 000 FCFA -> convertCost(5000, 'kg', 'g') = 5 FCFA.
     */
    public static function convertCost(int|float $cost, string|self|null $from, string|self|null $to): ?float
    {
        $rate = self::conversionRate($from, $to);
        if ($rate === null || $rate <= 0) {
            return null;
        }

        return (float) $cost / $rate;
    }

    /**
     * Options formatées pour les éléments HTML <select>.
     *
     * @return list<array{id: int, value: string, short_name: string, name: string, label: string, equivalence: string, factor: float, reference_id: ?int}>
     */
    public static function selectOptions(?string $actuelle = null): array
    {
        $unites = self::query()->where('is_active', true)->dansLOrdre()->get();

        $options = $unites->map(fn (self $u) => [
            'id'           => $u->id,
            'value'        => $u->short_name ?: $u->name,
            'short_name'   => $u->short_name ?: $u->name,
            'name'         => $u->name,
            'label'        => $u->short_name && $u->short_name !== $u->name ? "{$u->name} ({$u->short_name})" : $u->name,
            'equivalence'  => $u->equivalenceLabel(),
            'factor'       => (float) $u->factor,
            'reference_id' => $u->reference_unit_id,
        ])->values()->all();

        if ($actuelle !== null && $actuelle !== '') {
            $dejaPresent = collect($options)->contains(fn ($o) => mb_strtolower($o['value']) === mb_strtolower($actuelle) || mb_strtolower($o['name']) === mb_strtolower($actuelle));
            if (! $dejaPresent) {
                $options[] = [
                    'id'           => 0,
                    'value'        => $actuelle,
                    'short_name'   => $actuelle,
                    'name'         => $actuelle,
                    'label'        => $actuelle,
                    'equivalence'  => "1 {$actuelle} (Personnalisée)",
                    'factor'       => 1.0,
                    'reference_id' => null,
                ];
            }
        }

        return $options;
    }

    /**
     * Les valeurs proposées dans une fiche d'article ou formulaire.
     * Pour compatibilité descendante avec l'existant.
     *
     * @return list<string>
     */
    public static function choix(?string $actuelle = null): array
    {
        $options = self::selectOptions($actuelle);
        $valeurs = [];

        foreach ($options as $opt) {
            $valeurs[] = $opt['short_name'];
            if ($opt['name'] !== $opt['short_name']) {
                $valeurs[] = $opt['name'];
            }
        }

        return array_values(array_unique($valeurs));
    }

    /** L'unité de la liste qui correspond à une saisie, sans tenir compte de la casse. */
    public static function canonique(?string $saisie): ?string
    {
        $saisie = trim((string) $saisie);
        if ($saisie === '') {
            return null;
        }

        $cle = mb_strtolower($saisie);

        $unite = self::findUnit($cle);
        if ($unite) {
            return $unite->short_name ?: $unite->name;
        }

        return collect(self::choix())->first(fn (string $nom) => mb_strtolower($nom) === $cle);
    }

    /** Nombre d'articles qui emploient cette unité (par nom ou symbole court). */
    public function articlesCount(): int
    {
        return StockItem::query()
            ->where(function ($q) {
                $q->where('unit', $this->name);
                if ($this->short_name) {
                    $q->orWhere('unit', $this->short_name);
                }
            })
            ->count();
    }
}

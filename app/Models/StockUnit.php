<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Unité de stockage d'un article de l'économat : kg, litre, pièce, casier…
 *
 * L'économe tient la liste (Paramètres › Économat) ; la fiche d'un article
 * y choisit son unité. L'article garde le nom de l'unité en clair : renommer
 * une unité renomme celle des articles qui l'emploient.
 */
class StockUnit extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeDansLOrdre(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Les unités proposées dans une fiche d'article. Celle que l'article
     * porte déjà reste proposée même mise hors service : on ne force pas à
     * en changer pour modifier autre chose.
     *
     * @return list<string>
     */
    public static function choix(?string $actuelle = null): array
    {
        $noms = self::query()->where('is_active', true)->dansLOrdre()->pluck('name')->all();

        if ($actuelle !== null && $actuelle !== '' && !in_array($actuelle, $noms, true)) {
            $noms[] = $actuelle;
        }

        return $noms;
    }

    /** L'unité de la liste qui correspond à une saisie, sans tenir compte de la casse. */
    public static function canonique(?string $saisie): ?string
    {
        $saisie = trim((string) $saisie);
        if ($saisie === '') {
            return null;
        }

        $cle = mb_strtolower($saisie);

        return collect(self::choix())->first(fn (string $nom) => mb_strtolower($nom) === $cle);
    }

    /** Nombre d'articles qui emploient cette unité. */
    public function articlesCount(): int
    {
        return StockItem::query()->where('unit', $this->name)->count();
    }
}

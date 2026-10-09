<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Le stock du magasin central à cet instant, valorisé au coût moyen. */
class EtatStocks extends Edition
{
    public function cle(): string { return 'etat-stocks'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'État des stocks valorisé'; }

    public function description(): string
    {
        return "Chaque article du magasin central : stock, seuil d'alerte, coût moyen et valeur. Les articles sous le seuil sont signalés.";
    }

    public function droits(): array { return ['economat.items.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::choix('categorie', 'Catégorie', fn () => StockCategory::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(), 'Toutes les catégories'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        $lignes = StockItem::query()->active()->with('category:id,name')
            ->when($valeurs['categorie'] !== '', fn ($q) => $q->where('stock_category_id', (int) $valeurs['categorie']))
            ->orderBy('name')
            ->get()
            ->sortBy(fn (StockItem $i) => [$i->category?->name, $i->name])
            ->map(fn (StockItem $i) => [
                'reference' => $i->reference ?: '—',
                'article' => $i->name,
                'categorie' => $i->category?->name ?? '—',
                'unite' => $i->unit,
                'stock' => (float) $i->current_stock,
                'seuil' => (float) $i->min_stock,
                'cmup' => (int) $i->average_cost,
                'valeur' => (int) round(max(0, (float) $i->current_stock) * (int) $i->average_cost),
                'alerte' => (float) $i->current_stock <= (float) $i->min_stock && (float) $i->min_stock > 0 ? 'Sous le seuil' : '',
            ])
            ->values();

        return $this->base($valeurs, $user, 'Situation au ' . now()->format('d/m/Y à H:i'))
            ->colonnes([
                Colonne::texte('reference', 'Réf.'),
                Colonne::texte('article', 'Article'),
                Colonne::texte('categorie', 'Catégorie'),
                Colonne::texte('unite', 'Unité'),
                Colonne::nombre('stock', 'Stock'),
                Colonne::nombre('seuil', 'Seuil'),
                Colonne::montant('cmup', 'Coût moyen', false),
                Colonne::montant('valeur', 'Valeur'),
                Colonne::texte('alerte', 'Alerte'),
            ])
            ->lignes($lignes)
            ->totaux(['valeur' => (int) $lignes->sum('valeur')])
            ->note($lignes->where('alerte', '!=', '')->count() . ' article(s) sous le seuil.');
    }
}

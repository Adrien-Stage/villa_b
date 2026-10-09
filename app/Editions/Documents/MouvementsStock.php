<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les entrées, sorties et ajustements du magasin central, mouvement par mouvement. */
class MouvementsStock extends Edition
{
    public function cle(): string { return 'mouvements-stock'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'Mouvements de stock'; }

    public function description(): string
    {
        return "Chaque entrée, sortie ou ajustement du magasin central sur la période : article, quantité, stock après mouvement, coût, motif et auteur.";
    }

    public function droits(): array { return ['economat.items.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('jour'),
            Filtre::choix('type', 'Mouvement', fn () => StockMovement::TYPES, 'Tous les mouvements'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = StockMovement::query()
            ->with(['item:id,name,unit', 'user:id,name'])
            ->whereBetween('occurred_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['type'] !== '', fn ($q) => $q->where('type', $valeurs['type']))
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (StockMovement $m) => [
                'date' => $m->occurred_at,
                'article' => $m->item?->name ?? '—',
                'type' => StockMovement::TYPES[$m->type] ?? $m->type,
                'quantite' => (float) $m->quantity . ' ' . ($m->item?->unit ?? ''),
                'apres' => (float) $m->stock_after,
                'cout' => (int) $m->unit_cost,
                'motif' => $m->reason ?: '—',
                'par' => $m->user?->name ?? '—',
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::dateHeure('date', 'Date'),
                Colonne::texte('article', 'Article'),
                Colonne::texte('type', 'Mouvement'),
                Colonne::texte('quantite', 'Quantité'),
                Colonne::nombre('apres', 'Stock après'),
                Colonne::montant('cout', 'Coût unitaire', false),
                Colonne::texte('motif', 'Motif'),
                Colonne::texte('par', 'Par'),
            ])
            ->lignes($lignes);
    }
}

<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\MinibarMovement;
use App\Models\MinibarRestock;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Ce que la gouvernante a remis dans les mini-bars, et depuis quel dépôt. */
class RechargementsMinibar extends Edition
{
    public function cle(): string { return 'rechargements-minibar'; }

    public function famille(): string { return self::HOUSEKEEPING; }

    public function titre(): string { return 'Rechargements des mini-bars'; }

    public function description(): string
    {
        return "Chaque article remis dans un mini-bar depuis le dépôt : chambre, motif (après départ, mini-bar vidé, mise en place), qui l'a rechargé, quantité et valeur au coût.";
    }

    public function droits(): array { return ['housekeeping.minibar.voir']; }

    public function module(): ?string { return 'housekeeping'; }

    public function filtres(): array
    {
        return [Filtre::periode()];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $recharges = MinibarRestock::query()
            ->with(['room', 'user', 'store'])
            ->whereBetween('restocked_at', [$du->startOfDay(), $au->endOfDay()])
            ->orderBy('restocked_at')
            ->get();

        $mouvements = MinibarMovement::query()
            ->with('article')
            ->where('source_type', MinibarMovement::SOURCE_RESTOCK)
            ->whereIn('source_id', $recharges->pluck('id'))
            ->get()
            ->groupBy('source_id');

        $lignes = $recharges->flatMap(fn (MinibarRestock $r) => ($mouvements[$r->id] ?? collect())->map(fn (MinibarMovement $m) => [
            'quand' => $r->restocked_at,
            'chambre' => $r->room?->number,
            'motif' => $r->reasonLabel(),
            'qui' => $r->user?->name ?? '—',
            'depot' => $r->store?->name ?? '',
            'article' => $m->article?->name,
            'quantite' => $m->quantity,
            'valeur' => $m->quantity * $m->unit_cost,
        ]))->values();

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::dateHeure('quand', 'Rechargé le'),
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('motif', 'Motif'),
                Colonne::texte('qui', 'Par'),
                Colonne::texte('depot', 'Dépôt'),
                Colonne::texte('article', 'Article'),
                Colonne::nombre('quantite', 'Qté'),
                Colonne::montant('valeur', 'Valeur au coût'),
            ])
            ->lignes($lignes);
    }
}

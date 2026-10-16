<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Models\Room;
use App\Models\RoomMinibarLine;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Le contenu de chaque mini-bar face à sa dotation, à cet instant. */
class EtatMinibars extends Edition
{
    public function cle(): string { return 'etat-minibars'; }

    public function famille(): string { return self::HOUSEKEEPING; }

    public function titre(): string { return 'État des mini-bars'; }

    public function description(): string
    {
        return "Chambre par chambre et article par article : la dotation, ce que contient le mini-bar et ce qu'il faut remettre. La liste de la gouvernante pour recharger.";
    }

    public function droits(): array { return ['housekeeping.minibar.voir']; }

    public function module(): ?string { return 'housekeeping'; }

    public function document(array $valeurs, User $user): Document
    {
        $lignes = RoomMinibarLine::query()
            ->with(['room', 'article'])
            ->where(fn ($q) => $q->where('standard_quantity', '>', 0)->orWhere('quantity', '>', 0))
            ->get()
            ->sortBy(fn (RoomMinibarLine $l) => [$l->room?->floor, $l->room?->number, $l->article?->sort_order, $l->article?->name])
            ->map(fn (RoomMinibarLine $l) => [
                'chambre' => $l->room?->number,
                'statut' => $l->room?->status?->label() ?? '—',
                'article' => $l->article?->name,
                'dotation' => $l->standard_quantity,
                'present' => $l->quantity,
                'manque' => $l->manquant(),
            ])
            ->values();

        $aRemettre = $lignes->sum('manque');

        return $this->base($valeurs, $user, 'Situation au ' . now()->format('d/m/Y à H:i'))
            ->colonnes([
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('statut', 'Statut'),
                Colonne::texte('article', 'Article'),
                Colonne::nombre('dotation', 'Dotation'),
                Colonne::nombre('present', 'Présent'),
                Colonne::nombre('manque', 'À remettre'),
            ])
            ->lignes($lignes)
            ->note($aRemettre > 0 ? "{$aRemettre} article(s) à remettre en chambre." : 'Tous les mini-bars sont à leur dotation.');
    }
}

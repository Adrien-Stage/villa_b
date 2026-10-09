<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockMovementJournal;
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
        return "Chaque entrée, sortie ou ajustement du magasin central sur la période : article, stock avant et après, valeur, document d'origine, motif et auteur.";
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

        // Même lecture que l'écran « Mouvements de stock » : stock avant et après chaque mouvement.
        $journal = app(StockMovementJournal::class);
        $mouvements = $journal->query(['du' => $du, 'au' => $au, 'type' => $valeurs['type'] ?: null])
            ->orderBy('occurred_at')->orderBy('id')->get();
        $documents = $journal->documents($mouvements);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::dateHeure('date', 'Date'),
                Colonne::texte('article', 'Article'),
                Colonne::texte('nature', 'Mouvement'),
                Colonne::texte('origine', 'Document'),
                Colonne::nombre('avant', 'Stock avant'),
                Colonne::nombre('entree', 'Entrée'),
                Colonne::nombre('sortie', 'Sortie'),
                Colonne::nombre('apres', 'Stock après'),
                Colonne::montant('cout', 'Coût unitaire', false),
                Colonne::montant('valeur', 'Valeur'),
                Colonne::texte('motif', 'Motif'),
                Colonne::texte('par', 'Par'),
            ])
            ->lignes($mouvements->map(fn (StockMovement $m) => $journal->ligne($m, $documents)));
    }
}

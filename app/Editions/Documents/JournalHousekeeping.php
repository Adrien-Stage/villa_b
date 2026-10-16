<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\HousekeepingLog;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Chaque geste du housekeeping, dans l'ordre du temps. */
class JournalHousekeeping extends Edition
{
    public function cle(): string { return 'journal-housekeeping'; }

    public function famille(): string { return self::HOUSEKEEPING; }

    public function titre(): string { return 'Journal du housekeeping'; }

    public function description(): string
    {
        return "Chaque geste fait sur une chambre — affectation, nettoyage, contrôle, refus, remise en vente, relevé et rechargement du mini-bar, incident — avec son auteur et son heure.";
    }

    public function droits(): array { return ['housekeeping.journal.voir']; }

    public function module(): ?string { return 'housekeeping'; }

    public function filtres(): array
    {
        return [
            Filtre::periode(),
            Filtre::choix('geste', 'Geste', fn () => HousekeepingLog::ACTIONS, 'Tous les gestes'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = HousekeepingLog::query()
            ->with(['room', 'user', 'team'])
            ->whereBetween('occurred_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['geste'] !== Filtre::TOUS, fn ($q) => $q->where('action', $valeurs['geste']))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (HousekeepingLog $l) => [
                'quand' => $l->occurred_at,
                'chambre' => $l->room?->number,
                'geste' => $l->actionLabel(),
                'qui' => $l->user?->name ?? 'Système',
                'equipe' => $l->team?->name ?? '',
                'details' => $l->details ?? '',
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::dateHeure('quand', 'Date et heure'),
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('geste', 'Geste'),
                Colonne::texte('qui', 'Par'),
                Colonne::texte('equipe', 'Équipe'),
                Colonne::texte('details', 'Détail'),
            ])
            ->lignes($lignes);
    }
}

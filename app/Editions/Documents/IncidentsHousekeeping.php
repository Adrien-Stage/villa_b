<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingLog;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les problèmes signalés par les équipes, et ceux qui bloquent encore une chambre. */
class IncidentsHousekeeping extends Edition
{
    public function cle(): string { return 'incidents-housekeeping'; }

    public function famille(): string { return self::HOUSEKEEPING; }

    public function titre(): string { return 'Incidents signalés'; }

    public function description(): string
    {
        return "Les problèmes que les équipes ont signalés dans les chambres (fuite, panne, casse…), qui les a signalés et quand, et s'ils bloquent encore la chambre.";
    }

    public function droits(): array { return ['housekeeping.journal.voir']; }

    public function module(): ?string { return 'housekeeping'; }

    public function filtres(): array
    {
        return [Filtre::periode('mois')];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $bloques = HousekeepingAssignment::query()->where('status', 'blocked')->pluck('id')->all();

        $lignes = HousekeepingLog::query()
            ->with(['room', 'user', 'team'])
            ->where('action', HousekeepingLog::ISSUE)
            ->whereBetween('occurred_at', [$du->startOfDay(), $au->endOfDay()])
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (HousekeepingLog $l) => [
                'quand' => $l->occurred_at,
                'chambre' => $l->room?->number,
                'probleme' => $l->details ?? '',
                'qui' => $l->user?->name ?? '—',
                'equipe' => $l->team?->name ?? '',
                'etat' => in_array($l->housekeeping_assignment_id, $bloques, true) ? 'Bloque encore la chambre' : 'Levé',
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::dateHeure('quand', 'Signalé le'),
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('probleme', 'Problème'),
                Colonne::texte('qui', 'Par'),
                Colonne::texte('equipe', 'Équipe'),
                Colonne::texte('etat', 'État'),
            ])
            ->lignes($lignes);
    }
}

<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingTeam;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Qui a fait quelle chambre, à quelle heure, en combien de temps. */
class RapportHousekeeping extends Edition
{
    public function cle(): string { return 'rapport-housekeeping'; }

    public function famille(): string { return self::HOUSEKEEPING; }

    public function titre(): string { return "Rapport d'activité du housekeeping"; }

    public function description(): string
    {
        return "Chaque passage dans une chambre — ménage de départ ou recouche — avec son équipe, qui l'a commencé et terminé, à quelle heure, en combien de temps, qui l'a contrôlé et remis en vente.";
    }

    public function droits(): array { return ['housekeeping.journal.voir']; }

    public function module(): ?string { return 'housekeeping'; }

    public function filtres(): array
    {
        return [
            Filtre::periode(),
            Filtre::choix('equipe', 'Équipe', fn () => HousekeepingTeam::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn ($nom, $id) => [(string) $id => $nom])->all(), 'Toutes les équipes'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        [$du, $au] = [$du->startOfDay(), $au->endOfDay()];
        $heure = fn ($t, ?User $qui) => $t ? $t->format($du->isSameDay($au) ? 'H:i' : 'd/m H:i') . ($qui ? ' — ' . $qui->name : '') : '';

        $passages = HousekeepingAssignment::query()
            ->with(['room', 'team', 'startedBy', 'completedBy', 'inspectedBy', 'releasedBy'])
            ->where(fn ($q) => $q->whereBetween('started_at', [$du, $au])->orWhereBetween('completed_at', [$du, $au]))
            ->when($valeurs['equipe'] !== Filtre::TOUS, fn ($q) => $q->where('housekeeping_team_id', (int) $valeurs['equipe']))
            ->orderBy('started_at')
            ->get();

        $lignes = $passages->map(fn (HousekeepingAssignment $a) => [
            'chambre' => $a->room?->number,
            'nature' => $a->isRecouche() ? 'Recouche' : 'Départ',
            'equipe' => $a->team?->name ?? '—',
            'commencee' => $heure($a->started_at, $a->startedBy),
            'terminee' => $heure($a->completed_at, $a->completedBy),
            'duree' => $a->dureeEnMinutes(),
            'controlee' => $heure($a->inspected_at, $a->inspectedBy),
            'vente' => $heure($a->released_at, $a->releasedBy),
        ]);

        $faits = $passages->filter(fn ($a) => $a->dureeEnMinutes() !== null);
        $note = $faits->count() . ' passage(s) terminé(s)'
            . ($faits->isNotEmpty() ? ', durée moyenne ' . (int) round($faits->avg(fn ($a) => $a->dureeEnMinutes())) . ' min' : '') . '.';

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('nature', 'Nature'),
                Colonne::texte('equipe', 'Équipe'),
                Colonne::texte('commencee', 'Commencée'),
                Colonne::texte('terminee', 'Terminée'),
                Colonne::nombre('duree', 'Durée (min)'),
                Colonne::texte('controlee', 'Contrôlée'),
                Colonne::texte('vente', 'Remise en vente'),
            ])
            ->lignes($lignes)
            ->note($note);
    }
}

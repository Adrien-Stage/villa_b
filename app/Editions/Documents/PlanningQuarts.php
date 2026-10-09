<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\Department;
use App\Models\User;
use App\Services\PlanningService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Le planning des quarts d'une semaine, à afficher en salle de pause. */
class PlanningQuarts extends Edition
{
    public function __construct(private readonly PlanningService $planning) {}

    public function cle(): string { return 'planning-quarts'; }

    public function famille(): string { return self::PERSONNEL; }

    public function titre(): string { return 'Planning des quarts'; }

    public function description(): string
    {
        return "Qui travaille, quel jour et sur quel quart, pour une semaine et un service. Sans service à suivre, vos propres quarts.";
    }

    public function droits(): array { return ['planning.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::semaine(),
            Filtre::choix('service', 'Service', fn (User $u) => $this->planning->departementsVisibles($u)->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(), 'Tous mes services'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        $lundi = PlanningService::lundi($valeurs['semaine']);
        $visibles = $this->planning->departementsVisibles($user);
        $services = $valeurs['service'] !== '' ? $visibles->where('id', (int) $valeurs['service']) : $visibles;

        $affectations = $services->isEmpty()
            ? $this->planning->semaineDe($user, $lundi)
            : $services->flatMap(fn (Department $d) => $this->planning->semaine($d, $lundi)->each(fn ($a) => $a->setRelation('department', $d)));

        $lignes = $affectations
            ->sortBy(fn ($a) => [$a->jour()->toDateString(), $a->shift->sort_order, $a->department?->name, $a->user?->name])
            ->map(fn ($a) => [
                'jour' => ucfirst($a->jour()->locale('fr')->isoFormat('dddd D MMMM')),
                'quart' => $a->shift->name,
                'horaire' => $a->shift->horaire(),
                'service' => $a->department?->name ?? '—',
                'personne' => $a->user?->name ?? $user->name,
            ])
            ->values();

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('jour', 'Jour'),
                Colonne::texte('quart', 'Quart'),
                Colonne::texte('horaire', 'Horaire'),
                Colonne::texte('service', 'Service'),
                Colonne::texte('personne', 'Personne'),
            ])
            ->lignes($lignes);
    }
}

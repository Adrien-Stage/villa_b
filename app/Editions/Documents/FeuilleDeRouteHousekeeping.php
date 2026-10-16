<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingTeam;
use App\Models\User;
use App\Services\MinibarService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Support\Collection;

/**
 * La feuille de route des équipes : les chambres qu'elles ont à faire ou
 * qu'elles font, à emporter dans les étages. L'équipe ne voit que la sienne.
 */
class FeuilleDeRouteHousekeeping extends Edition
{
    public function cle(): string { return 'feuille-route-housekeeping'; }

    public function famille(): string { return self::HOUSEKEEPING; }

    public function titre(): string { return 'Feuille de route des équipes'; }

    public function description(): string
    {
        return "Les chambres confiées à chaque équipe et pas encore terminées : ménage de départ ou recouche, statut, consignes, relevé du mini-bar à faire. À imprimer le matin pour les étages.";
    }

    public function droits(): array { return ['housekeeping.voir']; }

    public function module(): ?string { return 'housekeeping'; }

    public function filtres(): array
    {
        return [
            Filtre::choix('equipe', 'Équipe', fn (User $u) => $this->equipes($u)->pluck('name', 'id')
                ->mapWithKeys(fn ($nom, $id) => [(string) $id => $nom])->all(), 'Toutes mes équipes'),
        ];
    }

    /** L'encadrement voit toutes les équipes ; un agent, les siennes. */
    private function equipes(User $user): Collection
    {
        $requete = HousekeepingTeam::query()->where('is_active', true)->orderBy('name');

        if (! $user->isAdmin() && ! $user->exerce(['manager', 'housekeeping_leader', 'housekeeping_supervisor', 'controller', 'quality_auditor'])) {
            $requete->whereHas('members', fn ($q) => $q->where('users.id', $user->id));
        }

        return $requete->get();
    }

    public function document(array $valeurs, User $user): Document
    {
        $equipes = $this->equipes($user);
        if ($valeurs['equipe'] !== Filtre::TOUS) {
            $equipes = $equipes->where('id', (int) $valeurs['equipe']);
        }

        $lignes = HousekeepingAssignment::query()
            ->with(['team', 'room.roomType', 'room.minibarLines', 'startedBy'])
            ->whereIn('housekeeping_team_id', $equipes->pluck('id'))
            ->whereIn('status', ['pending', 'in_progress', 'blocked'])
            ->get()
            ->sortBy(fn (HousekeepingAssignment $a) => [$a->team?->name, $a->room?->floor, $a->room?->number])
            ->map(fn (HousekeepingAssignment $a) => [
                'equipe' => $a->team?->name ?? '—',
                'chambre' => $a->room?->number,
                'type' => $a->room?->roomType?->name ?? '—',
                'nature' => $a->isRecouche() ? 'Recouche (client en séjour)' : 'Ménage de départ',
                'statut' => match ($a->status) {
                    'in_progress' => 'En cours' . ($a->startedBy ? ' — ' . $a->startedBy->name : ''),
                    'blocked' => 'Problème signalé',
                    default => 'À faire',
                },
                'minibar' => $a->room && MinibarService::releveDuAuMenage($a->room)
                    ? 'Relevé à faire (client parti)'
                    : ($a->isRecouche() && $a->room?->aUnMiniBar() ? 'Relevé à la recouche' : ''),
                'consignes' => $a->notes ?? '',
            ])
            ->values();

        return $this->base($valeurs, $user, 'Situation au ' . now()->format('d/m/Y à H:i'))
            ->colonnes([
                Colonne::texte('equipe', 'Équipe'),
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('type', 'Type'),
                Colonne::texte('nature', 'Nature'),
                Colonne::texte('statut', 'Statut'),
                Colonne::texte('minibar', 'Mini-bar'),
                Colonne::texte('consignes', 'Consignes'),
            ])
            ->lignes($lignes)
            ->note($lignes->count() . ' chambre' . ($lignes->count() > 1 ? 's' : '') . ' à traiter.');
    }
}

<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Registre des interventions du service technique : pannes, réparations, temps et pièces. */
class RegistreInterventionsTechniques extends Edition
{
    public function cle(): string { return 'registre-interventions-techniques'; }

    public function famille(): string { return self::TECHNIQUE; }

    public function titre(): string { return 'Registre des interventions techniques'; }

    public function description(): string
    {
        return "Historique des interventions et travaux de maintenance : localisation, corps de métier, technicien, pièces consommées et statut de validation.";
    }

    public function droits(): array { return ['technique.voir', 'technique.demandes.voir']; }

    public function module(): ?string { return 'technique'; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('statut', 'Statut', fn () => MaintenanceRequest::STATUSES, 'Tous les statuts'),
            Filtre::choix('specialite', 'Corps d\'état / Spécialité', fn () => MaintenanceRequest::SPECIALTIES, 'Toutes les spécialités'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $statut = $valeurs['statut'] ?? '';
        $specialite = $valeurs['specialite'] ?? '';

        $demandes = MaintenanceRequest::query()
            ->with(['room', 'technician', 'requester', 'parts'])
            ->whereBetween('created_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($statut !== '' && $statut !== 'tous', fn ($q) => $q->where('status', $statut))
            ->when($specialite !== '' && $specialite !== 'tous', fn ($q) => $q->where('specialty', $specialite))
            ->orderBy('created_at')
            ->get();

        $lignes = $demandes->map(fn (MaintenanceRequest $d) => [
            'reference'  => $d->reference,
            'date'       => $d->created_at,
            'lieu'       => $d->lieu(),
            'titre'      => $d->title,
            'specialite' => $d->specialtyLabel(),
            'priorite'   => $d->priorityLabel(),
            'technicien' => $d->technician?->name ?? 'Non affecté',
            'statut'     => $d->statusLabel(),
            'pieces'     => $d->parts->count(),
            'cout'       => (int) ($d->valeurDesPieces() / 100),
        ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('reference', 'Réf.'),
                Colonne::dateHeure('date', 'Date demande'),
                Colonne::texte('lieu', 'Lieu'),
                Colonne::texte('titre', 'Objet / Panne'),
                Colonne::texte('specialite', 'Corps d\'état'),
                Colonne::texte('priorite', 'Priorité'),
                Colonne::texte('technicien', 'Technicien'),
                Colonne::texte('statut', 'Statut'),
                Colonne::nombre('pieces', 'Nb pièces'),
                Colonne::montant('cout', 'Coût pièces (FCFA)'),
            ])
            ->lignes($lignes);
    }
}

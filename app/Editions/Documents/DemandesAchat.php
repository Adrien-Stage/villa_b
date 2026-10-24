<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/**
 * Les demandes d'achat de la période et leur décision : l'archive du circuit
 * d'achat. Chacune s'imprime seule depuis « Retrouver une pièce ».
 */
class DemandesAchat extends Edition
{
    /** « Archivées » : commandées, refusées ou annulées — sorties du circuit. */
    private const ETATS = [
        'en_cours' => 'En cours (à approuver, à commander)',
        'archivees' => 'Archivées (commandées, refusées, annulées)',
        PurchaseRequest::STATUS_PENDING => 'En attente d\'approbation',
        PurchaseRequest::STATUS_APPROVED => 'Approuvées, à commander',
        PurchaseRequest::STATUS_CONVERTED => 'Converties en bons',
        PurchaseRequest::STATUS_REJECTED => 'Refusées',
        PurchaseRequest::STATUS_CANCELLED => 'Annulées',
    ];

    public function cle(): string { return 'demandes-achat'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return "Registre des demandes d'achat"; }

    public function description(): string
    {
        return "Les demandes d'achat de la période : service, demandeur, décision du contrôle de gestion, quantités ajustées, bons tirés et montant estimé.";
    }

    public function droits(): array { return ['economat.purchase_requests.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('etat', 'Statut', fn () => self::ETATS, 'Tous les statuts'),
            Filtre::choix('service', 'Service demandeur', fn () => PurchaseRequest::DEPARTMENTS, 'Tous les services'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $etat = $valeurs['etat'];

        $lignes = PurchaseRequest::query()
            ->with(['requestedBy:id,name', 'reviewedBy:id,name', 'lines', 'purchaseOrders:id,number,purchase_request_id'])
            ->whereBetween('created_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($etat === 'en_cours', fn ($q) => $q->enCours())
            ->when($etat === 'archivees', fn ($q) => $q->archivees())
            ->when(! in_array($etat, ['', 'en_cours', 'archivees'], true), fn ($q) => $q->where('status', $etat))
            ->when($valeurs['service'] !== '', fn ($q) => $q->where('department', $valeurs['service']))
            ->orderBy('created_at')
            ->get()
            ->map(fn (PurchaseRequest $d) => [
                'numero' => $d->number,
                'date' => $d->created_at,
                'service' => $d->departmentLabel(),
                'par' => $d->requestedBy?->name ?? '—',
                'statut' => $d->statusLabel(),
                'decision' => $d->reviewed_at ? ($d->reviewedBy?->name ?? '—') . ', le ' . $d->reviewed_at->format('d/m/Y') : '—',
                'ajustee' => $d->aEteAjustee() ? 'Oui' : '',
                'bons' => $d->purchaseOrders->pluck('number')->implode(', ') ?: '—',
                'montant' => (int) $d->total_estimated_amount,
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('numero', 'N° de demande'),
                Colonne::date('date', 'Date'),
                Colonne::texte('service', 'Service'),
                Colonne::texte('par', 'Demandé par'),
                Colonne::texte('statut', 'Statut'),
                Colonne::texte('decision', 'Décision'),
                Colonne::texte('ajustee', 'Quantités ajustées'),
                Colonne::texte('bons', 'Bons de commande'),
                Colonne::montant('montant', 'Montant estimé'),
            ])
            ->lignes($lignes)
            ->totaux(['montant' => (int) $lignes->sum('montant')]);
    }
}

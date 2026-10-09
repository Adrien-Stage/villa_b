<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\StockRequisition;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les demandes des services au magasin : ce qui est remonté, validé, livré. */
class BonsRequisition extends Edition
{
    public function cle(): string { return 'bons-requisition'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'Registre des bons de réquisition'; }

    public function description(): string
    {
        return "Les demandes de sortie de stock remontées par les services sur la période : service, destination, statut, demandeur et date de livraison.";
    }

    public function droits(): array { return ['economat.requisitions.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('service', 'Service demandeur', fn () => StockRequisition::DEPARTMENTS, 'Tous les services'),
            Filtre::choix('statut', 'Statut', fn () => StockRequisition::STATUSES, 'Tous les statuts'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = StockRequisition::query()
            ->with(['requestedBy:id,name', 'serviceStore:id,name', 'pointOfSale:id,name'])
            ->withCount('lines')
            ->whereBetween('created_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['service'] !== '', fn ($q) => $q->where('department', $valeurs['service']))
            ->when($valeurs['statut'] !== '', fn ($q) => $q->where('status', $valeurs['statut']))
            ->orderBy('created_at')
            ->get()
            ->map(fn (StockRequisition $r) => [
                'numero' => $r->number,
                'date' => $r->created_at,
                'service' => StockRequisition::DEPARTMENTS[$r->department] ?? $r->department,
                'destination' => $r->serviceStore?->name ?? $r->pointOfSale?->name ?? '—',
                'articles' => $r->lines_count,
                'statut' => $r->statusLabel(),
                'par' => $r->requestedBy?->name ?? '—',
                'livre' => $r->delivered_at,
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('numero', 'N° de bon'),
                Colonne::date('date', 'Date'),
                Colonne::texte('service', 'Service'),
                Colonne::texte('destination', 'Destination'),
                Colonne::nombre('articles', 'Articles'),
                Colonne::texte('statut', 'Statut'),
                Colonne::texte('par', 'Demandé par'),
                Colonne::date('livre', 'Livré le'),
            ])
            ->lignes($lignes);
    }
}

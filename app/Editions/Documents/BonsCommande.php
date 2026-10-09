<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les bons de commande fournisseurs de la période ; chacun s'imprime seul depuis « Retrouver une pièce ». */
class BonsCommande extends Edition
{
    /** « Validés » : les bons envoyés au fournisseur, reçus ou en cours de réception. */
    private const ETATS = [
        'valides' => 'Validés (envoyés ou reçus)',
        PurchaseOrder::STATUS_DRAFT => 'Brouillons',
        PurchaseOrder::STATUS_SENT => 'Envoyés, en attente',
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Partiellement reçus',
        PurchaseOrder::STATUS_RECEIVED => 'Réceptionnés',
        PurchaseOrder::STATUS_CANCELLED => 'Annulés',
    ];

    public function cle(): string { return 'bons-commande'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'Registre des bons de commande'; }

    public function description(): string
    {
        return 'Les commandes passées aux fournisseurs sur la période : fournisseur, montant, statut et émetteur. Par défaut, les bons validés.';
    }

    public function droits(): array { return ['economat.orders.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('etat', 'Statut', fn () => self::ETATS, 'Tous les statuts'),
            Filtre::choix('fournisseur', 'Fournisseur', fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(), 'Tous les fournisseurs'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $etat = $valeurs['etat'];

        $lignes = PurchaseOrder::query()
            ->with(['supplier:id,name', 'createdBy:id,name'])
            ->withCount('lines')
            ->whereBetween('created_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($etat === 'valides', fn ($q) => $q->whereIn('status', [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED]))
            ->when($etat !== '' && $etat !== 'valides', fn ($q) => $q->where('status', $etat))
            ->when($valeurs['fournisseur'] !== '', fn ($q) => $q->where('supplier_id', (int) $valeurs['fournisseur']))
            ->orderBy('created_at')
            ->get()
            ->map(fn (PurchaseOrder $o) => [
                'numero' => $o->number,
                'date' => $o->created_at,
                'fournisseur' => $o->supplier?->name ?? '—',
                'articles' => $o->lines_count,
                'statut' => $o->statusLabel(),
                'transmission' => $o->transmissionLabel() ?? '—',
                'par' => $o->createdBy?->name ?? '—',
                'montant' => (int) $o->total_amount,
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('numero', 'N° de bon'),
                Colonne::date('date', 'Date'),
                Colonne::texte('fournisseur', 'Fournisseur'),
                Colonne::nombre('articles', 'Articles'),
                Colonne::texte('statut', 'Statut'),
                Colonne::texte('transmission', 'Transmission'),
                Colonne::texte('par', 'Émis par'),
                Colonne::montant('montant', 'Montant'),
            ])
            ->lignes($lignes)
            ->totaux(['montant' => (int) $lignes->sum('montant')]);
    }
}

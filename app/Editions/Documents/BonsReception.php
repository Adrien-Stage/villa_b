<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les marchandises reçues sur la période, bon par bon. */
class BonsReception extends Edition
{
    public function cle(): string { return 'bons-reception'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return "Registre des bons d'entrée"; }

    public function description(): string
    {
        return 'Les livraisons réceptionnées au magasin sur la période : bon de commande, fournisseur, bon de livraison, montant, réceptionnaire.';
    }

    public function droits(): array { return ['economat.receipts.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('fournisseur', 'Fournisseur', fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(), 'Tous les fournisseurs'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = GoodsReceipt::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,number', 'receivedBy:id,name'])
            ->whereBetween('received_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['fournisseur'] !== '', fn ($q) => $q->where('supplier_id', (int) $valeurs['fournisseur']))
            ->orderBy('received_at')
            ->get()
            ->map(fn (GoodsReceipt $r) => [
                'numero' => $r->number,
                'date' => $r->received_at,
                'commande' => $r->purchaseOrder?->number ?? '—',
                'fournisseur' => $r->supplier?->name ?? '—',
                'livraison' => $r->delivery_note_number ?: '—',
                'statut' => $r->statusLabel(),
                'par' => $r->receivedBy?->name ?? '—',
                'montant' => $r->status === GoodsReceipt::STATUS_CANCELLED ? 0 : (int) $r->total_amount,
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('numero', 'N° de bon'),
                Colonne::date('date', 'Reçu le'),
                Colonne::texte('commande', 'Commande'),
                Colonne::texte('fournisseur', 'Fournisseur'),
                Colonne::texte('livraison', 'BL fournisseur'),
                Colonne::texte('statut', 'Statut'),
                Colonne::texte('par', 'Réceptionnaire'),
                Colonne::montant('montant', 'Montant'),
            ])
            ->lignes($lignes)
            ->totaux(['montant' => (int) $lignes->sum('montant')]);
    }
}

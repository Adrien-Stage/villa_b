<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les factures émises sur la période ; chacune s'imprime aussi seule depuis « Retrouver une pièce ». */
class Factures extends Edition
{
    private const STATUTS = ['draft' => 'Brouillon', 'sent' => 'Envoyée', 'paid' => 'Payée', 'overdue' => 'En retard', 'cancelled' => 'Annulée'];

    public function cle(): string { return 'factures'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Registre des factures'; }

    public function description(): string
    {
        return 'Les factures émises sur la période, avec leurs montants hors taxes, TVA, TTC, réglés et restant dus.';
    }

    public function droits(): array { return ['invoices.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('statut', 'Statut', fn () => self::STATUTS, 'Tous les statuts'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = Invoice::query()
            ->with(['customer', 'booking:id,booking_number'])
            ->whereDate('invoice_date', '>=', $du->toDateString())->whereDate('invoice_date', '<=', $au->toDateString())
            ->when($valeurs['statut'] !== '', fn ($q) => $q->where('status', $valeurs['statut']))
            ->orderBy('invoice_date')->orderBy('id')
            ->get()
            ->map(fn (Invoice $f) => [
                'numero' => $f->invoice_number,
                'date' => $f->invoice_date,
                'client' => $f->customer?->full_name ?? '—',
                'sejour' => $f->booking?->booking_number ?? '—',
                'ht' => (int) $f->subtotal,
                'tva' => (int) $f->tax_amount,
                'ttc' => (int) $f->total_amount,
                'paye' => (int) $f->paid_amount,
                'reste' => (int) $f->balance_due,
                'statut' => self::STATUTS[$f->status] ?? $f->status,
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('numero', 'N°'),
                Colonne::date('date', 'Date'),
                Colonne::texte('client', 'Client'),
                Colonne::texte('sejour', 'Séjour'),
                Colonne::montant('ht', 'HT'),
                Colonne::montant('tva', 'TVA'),
                Colonne::montant('ttc', 'TTC'),
                Colonne::montant('paye', 'Réglé'),
                Colonne::montant('reste', 'Reste dû'),
                Colonne::texte('statut', 'Statut'),
            ])
            ->lignes($lignes)
            ->totaux(collect(['ht', 'tva', 'ttc', 'paye', 'reste'])->mapWithKeys(fn ($c) => [$c => (int) $lignes->sum($c)])->all());
    }
}

<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\PartnerOrganization;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletEntry;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

class ComptesDepotPartenaires extends Edition
{
    public function cle(): string
    {
        return 'comptes-depot-partenaires';
    }

    public function famille(): string
    {
        return self::FINANCES;
    }

    public function titre(): string
    {
        return 'Comptes dépôt partenaires';
    }

    public function description(): string
    {
        return 'Approvisionnements, utilisations autorisées, contre-opérations et soldes après mouvement.';
    }

    public function droits(): array
    {
        return ['partners.wallet.voir'];
    }

    public function filtres(): array
    {
        return [Filtre::periode('mois'), Filtre::choix('partner', 'Partenaire', fn (User $u) => PartnerOrganization::orderBy('name')->pluck('name', 'id')->all(), 'Tous les partenaires')];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $payments = Payment::with(['partnerOrganization', 'walletEntry.createdBy', 'invoice', 'booking'])
            ->whereNotNull('wallet_entry_id')->whereBetween('paid_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['partner'] !== '', fn ($q) => $q->where('partner_organization_id', $valeurs['partner']))
            ->orderBy('id')->get();
        $rows = $payments->map(fn (Payment $p) => [
            'date' => $p->paid_at, 'partenaire' => $p->partnerOrganization?->name ?? '—', 'piece' => $p->reference,
            'facture' => $p->invoice?->invoice_number ?? $p->booking?->booking_number ?? '—',
            'operation' => match ($p->walletEntry->kind) {
                'credit' => 'Approvisionnement', 'debit' => 'Règlement sur accord', default => 'Contre-opération'
            },
            'accord' => implode(' · ', array_filter([$p->walletEntry->metadata['authorized_by'] ?? null, $p->walletEntry->metadata['authorization_reference'] ?? null, $p->walletEntry->metadata['reason'] ?? null])),
            'credit' => max(0, $p->walletEntry->amount), 'debit' => max(0, -$p->walletEntry->amount),
            'solde' => $p->walletEntry->balance_after, 'par' => $p->walletEntry->createdBy?->name ?? '—',
        ]);
        $note = 'Le solde après mouvement est propre à chaque partenaire. Un débit règle une facture et ne constitue pas un nouvel encaissement.';
        if ($valeurs['partner'] !== '') {
            $wallet = PartnerOrganization::find($valeurs['partner'])?->wallet;
            $opening = $wallet ? (int) WalletEntry::where('wallet_id', $wallet->id)->where('created_at', '<', $du->startOfDay())->sum('amount') : 0;
            $ending = $opening + $rows->sum('credit') - $rows->sum('debit');
            $note .= ' Solde initial : '.number_format($opening / 100, 0, ',', ' ').' FCFA ; solde final : '.number_format($ending / 100, 0, ',', ' ').' FCFA.';
        }

        return $this->base($valeurs, $user)->colonnes([
            Colonne::dateHeure('date', 'Date'), Colonne::texte('partenaire', 'Partenaire'), Colonne::texte('piece', 'Pièce'),
            Colonne::texte('operation', 'Opération'), Colonne::texte('facture', 'Facture / séjour'), Colonne::texte('accord', 'Accord / motif'),
            Colonne::montant('credit', 'Crédit'), Colonne::montant('debit', 'Débit'), Colonne::montant('solde', 'Solde après'), Colonne::texte('par', 'Enregistré par'),
        ])->lignes($rows)->totaux(['credit' => (int) $rows->sum('credit'), 'debit' => (int) $rows->sum('debit')])->note($note);
    }
}

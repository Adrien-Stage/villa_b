<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Models\User;
use App\Services\AccountingService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Ce qu'on doit à l'établissement à cet instant : soldes de séjours et notes impayées. */
class Creances extends Edition
{
    public function __construct(private readonly AccountingService $comptes) {}

    public function cle(): string { return 'creances'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Créances clients'; }

    public function description(): string
    {
        return 'Les sommes dues à ce jour : soldes des séjours, notes de restaurant et ventes de la boutique restées impayées. À relancer.';
    }

    public function droits(): array { return ['accounting.receivables']; }

    public function document(array $valeurs, User $user): Document
    {
        $creances = $this->comptes->creances();
        $lignes = collect();

        foreach ($creances['sejours']['items'] as $b) {
            $lignes->push([
                'origine' => 'Séjour', 'piece' => $b->booking_number, 'client' => $b->customer?->full_name ?? '—',
                'date' => $b->check_out, 'statut' => $b->status?->label() ?? '', 'montant' => (int) $b->balance_due,
            ]);
        }
        foreach ($creances['restaurant']['items'] as $c) {
            $lignes->push([
                'origine' => 'Restaurant', 'piece' => 'Note #' . $c->id, 'client' => $c->customer_name ?: ($c->table_number ? 'Table ' . $c->table_number : '—'),
                'date' => $c->placed_at, 'statut' => 'Impayée', 'montant' => (int) $c->total_amount,
            ]);
        }
        foreach ($creances['boutique']['items'] as $o) {
            $lignes->push([
                'origine' => 'Boutique', 'piece' => $o->order_number, 'client' => $o->customer_name ?: '—',
                'date' => $o->created_at, 'statut' => 'Impayée', 'montant' => (int) $o->total_amount,
            ]);
        }

        return $this->base($valeurs, $user, 'Situation au ' . now()->format('d/m/Y à H:i'))
            ->colonnes([
                Colonne::texte('origine', 'Origine'),
                Colonne::texte('piece', 'Pièce'),
                Colonne::texte('client', 'Client'),
                Colonne::date('date', 'Date'),
                Colonne::texte('statut', 'Statut'),
                Colonne::montant('montant', 'Reste dû'),
            ])
            ->lignes($lignes)
            ->totaux(['montant' => (int) $lignes->sum('montant')]);
    }
}

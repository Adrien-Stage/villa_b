<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Editions\Registres;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Chaque vente de la période, comptée une fois, là où elle est née. */
class JournalVentes extends Edition
{
    public function __construct(private readonly Registres $registres) {}

    public function cle(): string { return 'journal-ventes'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Journal des ventes'; }

    public function description(): string
    {
        return "Ce qui a été vendu, service par service : nuitées des séjours effectués, prestations du séjour, comptoir, notes de restaurant, buffets, banquets réalisés, boutique — et comment chaque vente est ou sera réglée.";
    }

    public function droits(): array { return ['accounting.journal', 'accounting.revenue_journal', 'accounting.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('jour'),
            Filtre::choix('service', 'Service', fn (User $u) => $this->registres->services($u), 'Tous les services'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $lignes = $this->registres->ventes($du, $au, $valeurs['service']);

        $recap = collect(Registres::parService($lignes))
            ->map(fn ($m, $s) => $s . ' : ' . number_format($m / 100, 0, ',', ' ') . ' FCFA')->implode(' · ');

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::date('date', 'Date'),
                Colonne::texte('service', 'Service'),
                Colonne::texte('piece', 'Pièce'),
                Colonne::texte('client', 'Client'),
                Colonne::texte('designation', 'Désignation'),
                Colonne::texte('reglement', 'Règlement'),
                Colonne::montant('montant', 'Montant'),
            ])
            ->lignes($lignes)
            ->totaux(['montant' => (int) $lignes->sum('montant')])
            ->note(trim(($recap !== '' ? 'Par service — ' . $recap . '. ' : '')
                . "Un repas ou un achat porté à la chambre est compté dans son service, pas une seconde fois au séjour ; les nuitées sont comptées nuit par nuit pour les séjours effectués, hors gratuités."));
    }
}

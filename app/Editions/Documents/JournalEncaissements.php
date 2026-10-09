<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Editions\Registres;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Chaque somme entrée en caisse ou en banque, ligne par ligne, tous services réunis. */
class JournalEncaissements extends Edition
{
    public function __construct(private readonly Registres $registres) {}

    public function cle(): string { return 'journal-encaissements'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Journal des encaissements'; }

    public function description(): string
    {
        return "Chaque encaissement de la période : séjours, comptoir de la réception, restaurants (notes, buffets, banquets) et boutique, avec la pièce, le mode de règlement et qui l'a encaissé.";
    }

    public function droits(): array { return ['accounting.journal', 'accounting.cash', 'accounting.revenue_journal']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('jour'),
            Filtre::choix('service', 'Service', fn (User $u) => $this->registres->services($u), 'Tous les services'),
            Filtre::choix('mode', 'Mode de règlement', fn () => $this->registres->modes(), 'Tous les modes'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $lignes = $this->registres->encaissements($du, $au, $valeurs['service'], $valeurs['mode']);

        $parMode = $lignes->groupBy('mode')->map(fn ($l) => $l->sum('montant'))->sortDesc();
        $recap = $parMode->map(fn ($m, $mode) => $mode . ' : ' . number_format($m / 100, 0, ',', ' ') . ' FCFA')->implode(' · ');

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::dateHeure('date', 'Date'),
                Colonne::texte('service', 'Service'),
                Colonne::texte('piece', 'Pièce'),
                Colonne::texte('client', 'Client'),
                Colonne::texte('mode', 'Mode'),
                Colonne::texte('par', 'Encaissé par'),
                Colonne::montant('montant', 'Montant'),
            ])
            ->lignes($lignes)
            ->totaux(['montant' => (int) $lignes->sum('montant')])
            ->note($recap !== '' ? 'Par mode de règlement — ' . $recap . '. Les consommations portées à la chambre seront encaissées au règlement du séjour.' : null);
    }
}

<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Editions\Registres;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les encaissements de la période, totalisés par service et par mode de règlement. */
class RecapEncaissements extends Edition
{
    public function __construct(private readonly Registres $registres) {}

    public function cle(): string { return 'recapitulatif-encaissements'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Récapitulatif des encaissements'; }

    public function description(): string
    {
        return 'Les encaissements totalisés par service et par mode de règlement : la feuille à rapprocher des caisses et du relevé bancaire.';
    }

    public function droits(): array { return ['accounting.journal', 'accounting.cash', 'accounting.revenue_journal']; }

    public function filtres(): array
    {
        return [Filtre::periode('jour')];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $encaissements = $this->registres->encaissements($du, $au);

        $lignes = $encaissements->groupBy(fn ($l) => $l['service'] . '|' . $l['mode'])
            ->map(fn ($groupe) => [
                'service' => $groupe->first()['service'],
                'mode' => $groupe->first()['mode'],
                'nombre' => $groupe->count(),
                'montant' => (int) $groupe->sum('montant'),
            ])
            ->sortBy(fn ($l) => [$l['service'], $l['mode']])
            ->values();

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('service', 'Service'),
                Colonne::texte('mode', 'Mode de règlement'),
                Colonne::nombre('nombre', 'Opérations'),
                Colonne::montant('montant', 'Montant'),
            ])
            ->lignes($lignes)
            ->totaux(['nombre' => (int) $lignes->sum('nombre'), 'montant' => (int) $lignes->sum('montant')]);
    }
}

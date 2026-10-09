<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Editions\Registres;
use App\Models\User;
use App\Services\AccountingService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** La situation de la période en une page : ventes, encaissements, dépenses, créances, indicateurs. */
class SituationSynthetique extends Edition
{
    public function __construct(private readonly Registres $registres, private readonly AccountingService $comptes) {}

    public function cle(): string { return 'situation-synthetique'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Situation synthétique'; }

    public function description(): string
    {
        return "La période en une page : ventes par service, encaissements par mode, dépenses, solde de trésorerie, créances, et les indicateurs de l'hébergement (taux d'occupation, prix moyen, RevPAR, gratuités).";
    }

    public function droits(): array { return ['accounting.voir', 'accounting.income_statement', 'analytics.voir']; }

    public function filtres(): array
    {
        return [Filtre::periode('mois')];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $fcfa = fn (int $c) => number_format($c / 100, 0, ',', ' ') . ' FCFA';

        $ventes = $this->registres->ventes($du, $au);
        $encaissements = $this->registres->encaissements($du, $au);
        $depenses = (int) ($this->comptes->depenses($du->startOfDay(), $au->endOfDay())['total'] ?? 0);
        $creances = (int) ($this->comptes->creances()['total'] ?? 0);
        $h = $this->registres->hebergement($du, $au);

        $lignes = collect();
        $section = fn (string $titre) => $lignes->push(['rubrique' => mb_strtoupper($titre), 'valeur' => '']);
        $ligne = fn (string $rubrique, string $valeur) => $lignes->push(['rubrique' => '   ' . $rubrique, 'valeur' => $valeur]);

        $section('Ventes');
        foreach (Registres::parService($ventes) as $service => $montant) {
            $ligne($service, $fcfa($montant));
        }
        $ligne('Total des ventes', $fcfa((int) $ventes->sum('montant')));

        $section('Encaissements');
        foreach ($encaissements->groupBy('mode')->map(fn ($l) => (int) $l->sum('montant'))->sortDesc() as $mode => $montant) {
            $ligne($mode, $fcfa($montant));
        }
        $ligne('Total encaissé', $fcfa((int) $encaissements->sum('montant')));

        $section('Trésorerie');
        $ligne('Dépenses décaissées', $fcfa($depenses));
        $ligne('Solde (encaissé − dépensé)', $fcfa((int) $encaissements->sum('montant') - $depenses));
        $ligne('Créances à ce jour (soldes dus)', $fcfa($creances));

        $section('Hébergement');
        $ligne('Chambres disponibles × jours', number_format($h['capacite'], 0, ',', ' '));
        $ligne('Nuitées occupées', number_format($h['nuitees'], 0, ',', ' '));
        $ligne("Taux d'occupation", $h['capacite'] > 0 ? round($h['nuitees'] * 100 / $h['capacite'], 1) . ' %' : '—');
        $ligne('Prix moyen par nuitée payante (ADR)', $h['nuitees_payantes'] > 0 ? $fcfa(intdiv($h['revenu_chambres'], $h['nuitees_payantes'])) : '—');
        $ligne('Revenu par chambre disponible (RevPAR)', $h['capacite'] > 0 ? $fcfa(intdiv($h['revenu_chambres'], $h['capacite'])) : '—');
        $ligne('Nuitées offertes (valeur)', $h['gratuites'] . ' (' . $fcfa($h['valeur_gratuites']) . ')');

        return $this->base($valeurs, $user)
            ->colonnes([Colonne::texte('rubrique', 'Rubrique'), Colonne::texte('valeur', 'Valeur')])
            ->lignes($lignes)
            ->note('Ventes et encaissements suivent les mêmes règles que leurs journaux : chaque vente comptée une fois, là où elle naît ; chaque encaissement sur sa pièce.');
    }
}

<?php

namespace App\Services;

use App\Models\Account;
use App\Support\Document\Colonne;
use Illuminate\Support\Carbon;

class PeriodicTaxReport
{
    public function __construct(private readonly LedgerBooks $books)
    {
    }

    public function documents(Carbon $from, Carbon $to): array
    {
        $accounts = [
            Account::VAT_COLLECTED => 'TVA facturée',
            Account::VAT_SURTAX => 'Centimes additionnels communaux',
            Account::VAT_DEDUCTIBLE => 'TVA déductible',
            Account::WITHHOLDING => 'Retenues à la source',
            Account::TOURIST_TAX => 'Taxe de séjour',
            '431000' => 'Cotisations sociales',
            '441000' => 'Impôt sur les bénéfices et acomptes',
            '447200' => 'Droits de timbre',
            '447300' => 'Impôts et retenues sur salaires',
        ];
        $lines = collect(iterator_to_array($this->books->lines($from, $to), false))
            ->filter(fn ($line) => isset($accounts[$line['account']]))
            ->map(fn ($line) => $line + ['tax' => $accounts[$line['account']]]);

        return [
            $this->books->document('Synthèse fiscale de période', $from, $to)->colonnes([
                Colonne::texte('account', 'Compte'), Colonne::texte('tax', 'Rubrique'),
                Colonne::montant('debit', 'Débit'), Colonne::montant('credit', 'Crédit'),
                Colonne::montant('net', 'Crédit net'),
            ])->lignes($lines->groupBy('account')->map(fn ($group, $account) => [
                'account' => $account, 'tax' => $accounts[$account], 'debit' => $group->sum('debit'),
                'credit' => $group->sum('credit'), 'net' => $group->sum('credit') - $group->sum('debit'),
            ]))->note('État préparatoire. Vérifier les bases, les déductions, les reversements et les régularisations selon le régime de l’établissement.'),
            $this->books->document('Pièces fiscales de période', $from, $to)->colonnes([
                Colonne::date('date', 'Date'), Colonne::texte('number', 'Écriture'),
                Colonne::texte('reference', 'Pièce'), Colonne::texte('account', 'Compte'),
                Colonne::texte('tax', 'Rubrique'), Colonne::texte('label', 'Libellé'),
                Colonne::montant('debit', 'Débit'), Colonne::montant('credit', 'Crédit'),
            ])->lignes($lines),
        ];
    }
}

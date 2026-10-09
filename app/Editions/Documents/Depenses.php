<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Editions\Registres;
use App\Models\Expense;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les dépenses décaissées sur la période, par catégorie. */
class Depenses extends Edition
{
    public function cle(): string { return 'depenses'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Journal des dépenses'; }

    public function description(): string
    {
        return 'Les dépenses saisies sur la période : date, catégorie, libellé, mode de paiement et qui les a enregistrées.';
    }

    public function droits(): array { return ['accounting.expenses']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('categorie', 'Catégorie', fn () => Expense::CATEGORIES, 'Toutes les catégories'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $utilisateurs = User::query()->pluck('name', 'id');

        $lignes = Expense::query()
            ->whereBetween('occurred_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['categorie'] !== '', fn ($q) => $q->where('category', $valeurs['categorie']))
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (Expense $e) => [
                'date' => $e->occurred_at,
                'categorie' => Expense::CATEGORIES[$e->category] ?? 'Divers',
                'libelle' => $e->label,
                'mode' => Registres::mode($e->payment_method),
                'par' => $utilisateurs[$e->recorded_by] ?? '—',
                'montant' => (int) $e->amount,
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::date('date', 'Date'),
                Colonne::texte('categorie', 'Catégorie'),
                Colonne::texte('libelle', 'Libellé'),
                Colonne::texte('mode', 'Paiement'),
                Colonne::texte('par', 'Saisie par'),
                Colonne::montant('montant', 'Montant'),
            ])
            ->lignes($lignes)
            ->totaux(['montant' => (int) $lignes->sum('montant')]);
    }
}

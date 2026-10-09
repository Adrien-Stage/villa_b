<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\ExternalIssue;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Le matériel sorti du magasin sans servir l'établissement : prêts, réparations, dons, cessions. */
class SortiesHorsEtablissement extends Edition
{
    public function cle(): string { return 'sorties-hors-etablissement'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'Sorties hors établissement'; }

    public function description(): string
    {
        return "Le matériel sorti du magasin sans servir l'hôtel sur la période : motif, personne qui l'a emporté, valeur, retour prévu et économe qui a validé.";
    }

    public function droits(): array { return ['economat.external_issues.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('motif', 'Motif', fn () => ExternalIssue::REASONS, 'Tous les motifs'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = ExternalIssue::query()
            ->validees()
            ->with('issuedBy:id,name')
            ->withCount('lines')
            ->whereBetween('issued_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['motif'] !== '', fn ($q) => $q->where('reason', $valeurs['motif']))
            ->orderBy('issued_at')
            ->get()
            ->map(fn (ExternalIssue $s) => [
                'numero'    => $s->number,
                'date'      => $s->issued_at,
                'motif'     => $s->reasonLabel(),
                'emporte'   => $s->beneficiaire,
                'telephone' => $s->beneficiary_phone ?? '—',
                'articles'  => $s->lines_count,
                'valeur'    => (int) $s->total_value,
                'retour'    => $s->expected_return_at,
                'par'       => $s->issuedBy?->name ?? '—',
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('numero', 'N° de bon'),
                Colonne::dateHeure('date', 'Sortie le'),
                Colonne::texte('motif', 'Motif'),
                Colonne::texte('emporte', 'Emporté par'),
                Colonne::texte('telephone', 'Téléphone'),
                Colonne::nombre('articles', 'Articles'),
                Colonne::montant('valeur', 'Valeur'),
                Colonne::date('retour', 'Retour prévu'),
                Colonne::texte('par', 'Validé par'),
            ])
            ->lignes($lignes)
            ->totaux(['valeur' => (int) $lignes->sum('valeur')]);
    }
}

<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\MinibarCheck;
use App\Models\MinibarCheckLine;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Ce que les clients ont consommé au mini-bar, relevé par relevé. */
class RelevesMinibar extends Edition
{
    public function cle(): string { return 'releves-minibar'; }

    public function famille(): string { return self::HOUSEKEEPING; }

    public function titre(): string { return 'Relevés du mini-bar'; }

    public function description(): string
    {
        return "Chaque consommation relevée dans les mini-bars : chambre, moment (séjour, départ, après le départ), qui l'a relevée, article, quantité, montant, et où il a été facturé.";
    }

    public function droits(): array { return ['housekeeping.minibar.voir']; }

    public function module(): ?string { return 'housekeeping'; }

    public function filtres(): array
    {
        return [
            Filtre::periode(),
            Filtre::choix('moment', 'Moment', fn () => MinibarCheck::OCCASIONS, 'Tous'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $facturation = [
            MinibarCheck::BILLING_FOLIO => 'Folio du séjour',
            MinibarCheck::BILLING_A_FACTURER => 'Facture complémentaire',
            MinibarCheck::BILLING_AUCUNE => '',
        ];

        $lignes = MinibarCheck::query()
            ->with(['room', 'user', 'lines', 'invoice'])
            ->whereBetween('checked_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['moment'] !== Filtre::TOUS, fn ($q) => $q->where('occasion', $valeurs['moment']))
            ->orderBy('checked_at')
            ->get()
            ->flatMap(fn (MinibarCheck $c) => $c->lines->where('consumed', '>', 0)->map(fn (MinibarCheckLine $l) => [
                'quand' => $c->checked_at,
                'chambre' => $c->room?->number,
                'moment' => $c->occasionLabel(),
                'qui' => $c->user?->name ?? '—',
                'article' => $l->label,
                'quantite' => $l->consumed,
                'montant' => $l->total_price,
                'facture' => ($facturation[$c->billing] ?? '') . ($c->invoice ? ' ' . $c->invoice->invoice_number : ''),
            ]))
            ->values();

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::dateHeure('quand', 'Relevé le'),
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('moment', 'Moment'),
                Colonne::texte('qui', 'Relevé par'),
                Colonne::texte('article', 'Article'),
                Colonne::nombre('quantite', 'Qté'),
                Colonne::montant('montant', 'Montant'),
                Colonne::texte('facture', 'Facturé sur'),
            ])
            ->lignes($lignes);
    }
}

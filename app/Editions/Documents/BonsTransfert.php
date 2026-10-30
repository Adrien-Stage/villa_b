<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\ServiceStore;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Les approvisionnements des dépôts de l'économat par le magasin central. */
class BonsTransfert extends Edition
{
    public function cle(): string { return 'bons-transfert'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'Registre des bons de transfert'; }

    public function description(): string
    {
        return "Les transferts du magasin central vers les dépôts de l'économat sur la période : dépôt approvisionné, articles, valeur au coût moyen et agent qui l'a fait.";
    }

    public function droits(): array { return ['economat.transfers.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('depot', 'Dépôt', fn () => ServiceStore::query()->where('department', 'economat')->orderBy('name')->pluck('name', 'id')->all(), 'Tous les dépôts'),
            Filtre::choix('statut', 'Statut', fn () => StockTransfer::STATUSES, 'Tous les statuts'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = StockTransfer::query()
            ->with(['transferredBy:id,name', 'depot:id,name'])
            ->withCount('lines')
            ->whereBetween('transferred_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($valeurs['depot'] !== '', fn ($q) => $q->where('service_store_id', (int) $valeurs['depot']))
            ->when($valeurs['statut'] !== '', fn ($q) => $q->where('status', $valeurs['statut']))
            ->orderBy('transferred_at')
            ->get()
            ->map(fn (StockTransfer $t) => [
                'numero'   => $t->number,
                'date'     => $t->transferred_at,
                'depot'    => $t->depot?->name ?? '—',
                'articles' => $t->lines_count,
                // Un transfert annulé ne compte pas dans le total.
                'valeur'   => $t->isCancelled() ? 0 : (int) $t->total_value,
                'statut'   => $t->statusLabel(),
                'par'      => $t->transferredBy?->name ?? '—',
            ]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('numero', 'N° de bon'),
                Colonne::dateHeure('date', 'Transféré le'),
                Colonne::texte('depot', 'Dépôt'),
                Colonne::nombre('articles', 'Articles'),
                Colonne::montant('valeur', 'Valeur'),
                Colonne::texte('statut', 'Statut'),
                Colonne::texte('par', 'Par'),
            ])
            ->lignes($lignes)
            ->totaux(['valeur' => (int) $lignes->sum('valeur')]);
    }
}

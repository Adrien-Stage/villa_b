<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Ce que chaque fournisseur nous livre, et à quel prix. */
class TarifsFournisseurs extends Edition
{
    public function cle(): string { return 'tarifs-fournisseurs'; }

    public function famille(): string { return self::ACHATS; }

    public function titre(): string { return 'Tarifs des fournisseurs'; }

    public function description(): string
    {
        return "Les articles de chaque fournisseur avec leur prix d'achat et sa date, le fournisseur habituel et le moins cher de chaque article.";
    }

    public function droits(): array { return ['economat.suppliers.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::choix('fournisseur', 'Fournisseur', fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(), 'Tous les fournisseurs'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        $tarifs = DB::table('stock_item_supplier as t')
            ->join('suppliers as s', 's.id', '=', 't.supplier_id')
            ->join('stock_items as i', 'i.id', '=', 't.stock_item_id')
            ->when($valeurs['fournisseur'] !== '', fn ($q) => $q->where('t.supplier_id', (int) $valeurs['fournisseur']))
            ->orderBy('s.name')->orderBy('i.name')
            ->get(['s.name as fournisseur', 'i.id as article_id', 'i.name as article', 'i.unit', 'i.supplier_id as habituel',
                't.supplier_id', 't.purchase_price', 't.priced_at']);

        // Le meilleur prix de chaque article, tous fournisseurs confondus.
        $meilleurs = DB::table('stock_item_supplier')->where('purchase_price', '>', 0)
            ->whereIn('stock_item_id', $tarifs->pluck('article_id')->unique())
            ->groupBy('stock_item_id')->selectRaw('stock_item_id, MIN(purchase_price) AS prix, COUNT(*) AS offres')
            ->get()->keyBy('stock_item_id');

        $lignes = $tarifs->map(function ($t) use ($meilleurs) {
            $prix = (int) $t->purchase_price;
            $marche = $meilleurs[$t->article_id] ?? null;

            return [
                'fournisseur' => $t->fournisseur,
                'article' => $t->article,
                'unite' => \App\Models\StockUnit::symbolFor($t->unit),
                'prix' => $prix,
                'date' => $t->priced_at ? Carbon::parse($t->priced_at) : null,
                'observation' => collect([
                    (int) $t->habituel === (int) $t->supplier_id ? 'Fournisseur habituel' : null,
                    $prix > 0 && $marche && (int) $marche->offres > 1 && $prix <= (int) $marche->prix ? 'Moins cher' : null,
                    $prix <= 0 ? 'Prix à renseigner' : null,
                ])->filter()->implode(' · ') ?: '—',
            ];
        });

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('fournisseur', 'Fournisseur'),
                Colonne::texte('article', 'Article'),
                Colonne::texte('unite', 'Unité'),
                Colonne::montant('prix', "Prix d'achat", false),
                Colonne::date('date', 'Prix du'),
                Colonne::texte('observation', 'Observation'),
            ])
            ->lignes($lignes);
    }
}

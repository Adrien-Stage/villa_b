<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Le journal des mouvements de l'économat, sur tous les articles ou sur un
 * seul : l'écran « Mouvements de stock », son export et l'édition partagent
 * cette lecture, pour qu'ils ne divergent jamais.
 *
 * Chaque ligne dit le stock avant, l'entrée ou la sortie, le stock après, le
 * coût et la valeur du mouvement, et le document qui l'a causé.
 */
class StockMovementJournal
{
    /**
     * @param  array{du?: ?CarbonInterface, au?: ?CarbonInterface, article?: ?int, categorie?: ?int,
     *               type?: ?string, source?: ?string, recherche?: ?string}  $filtres
     */
    public function query(array $filtres): Builder
    {
        return StockMovement::query()
            ->with(['item:id,name,reference,unit,stock_category_id', 'user:id,name'])
            ->when($filtres['du'] ?? null, fn ($q, $du) => $q->where('occurred_at', '>=', $du->copy()->startOfDay()))
            ->when($filtres['au'] ?? null, fn ($q, $au) => $q->where('occurred_at', '<=', $au->copy()->endOfDay()))
            ->when($filtres['article'] ?? null, fn ($q, $id) => $q->where('stock_item_id', $id))
            ->when($filtres['categorie'] ?? null, fn ($q, $id) => $q->whereHas('item', fn ($i) => $i->where('stock_category_id', $id)))
            ->when($filtres['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filtres['source'] ?? null, fn ($q, $source) => $q->where('source_type', $source))
            ->when(trim((string) ($filtres['recherche'] ?? '')), fn ($q, $texte) => $q->where('reason', 'like', '%' . $texte . '%'));
    }

    /** Totaux de la sélection : nombre de mouvements et valeurs par nature. */
    public function synthese(array $filtres): array
    {
        $mouvements = $this->query($filtres)->get(['id', 'type', 'quantity', 'unit_cost']);

        $valeur = fn (string $type) => (int) $mouvements->where('type', $type)->sum(fn (StockMovement $m) => $m->valeur());

        return [
            'nombre'      => $mouvements->count(),
            'entrees'     => $valeur(StockMovement::TYPE_IN),
            'sorties'     => -$valeur(StockMovement::TYPE_OUT),
            'ajustements' => $valeur(StockMovement::TYPE_ADJUSTMENT),
        ];
    }

    /**
     * La fiche de stock d'un article sur une période : stock au début, entrées,
     * sorties, ajustements, stock en fin.
     */
    public function fiche(StockItem $item, ?CarbonInterface $du, ?CarbonInterface $au): array
    {
        $periode = $this->query(['article' => $item->id, 'du' => $du, 'au' => $au])->orderBy('occurred_at')->orderBy('id')->get();

        $precedent = $du
            ? StockMovement::query()->where('stock_item_id', $item->id)->where('occurred_at', '<', $du->copy()->startOfDay())
                ->orderByDesc('occurred_at')->orderByDesc('id')->first()
            : null;

        // Sans mouvement avant ni pendant la période, le stock n'a pas bougé :
        // c'est le stock courant (article repris avant l'historique).
        $debut = match (true) {
            $periode->isNotEmpty() => $periode->first()->stockAvant(),
            $precedent !== null    => (float) $precedent->stock_after,
            default                => (float) $item->current_stock,
        };

        $somme = fn (string $type) => round((float) $periode->where('type', $type)->sum(fn ($m) => (float) $m->quantity), 3);

        return [
            'debut'       => $debut,
            'entrees'     => $somme(StockMovement::TYPE_IN),
            'sorties'     => -$somme(StockMovement::TYPE_OUT),
            'ajustements' => $somme(StockMovement::TYPE_ADJUSTMENT),
            'fin'         => $periode->isNotEmpty() ? (float) $periode->last()->stock_after : $debut,
            'mouvements'  => $periode->count(),
        ];
    }

    /**
     * Les documents d'origine des mouvements, en une requête par sorte :
     * [source_type => [id => ['numero' => …, 'url' => …]]].
     *
     * @param  iterable<StockMovement>  $mouvements
     */
    public function documents(iterable $mouvements): array
    {
        $ids = collect($mouvements)->filter(fn ($m) => $m->source_id)->groupBy('source_type')
            ->map(fn (Collection $groupe) => $groupe->pluck('source_id')->unique()->values()->all());

        $sortes = [
            StockMovement::SOURCE_GOODS_RECEIPT  => [GoodsReceipt::class, 'number', 'economat.receipts.show'],
            StockMovement::SOURCE_PURCHASE_ORDER => [PurchaseOrder::class, 'number', 'economat.orders.show'],
            StockMovement::SOURCE_REQUISITION    => [StockRequisition::class, 'number', 'economat.requisitions.show'],
            StockMovement::SOURCE_STOCK_COUNT    => [StockCount::class, 'reference', 'economat.stock_counts.show'],
        ];

        $documents = [];
        foreach ($sortes as $source => [$modele, $colonne, $route]) {
            if (empty($ids[$source])) {
                continue;
            }
            foreach ($modele::query()->whereKey($ids[$source])->pluck($colonne, 'id') as $id => $numero) {
                $documents[$source][$id] = ['numero' => $numero, 'url' => route($route, $id)];
            }
        }

        return $documents;
    }

    /** Une ligne du journal, telle qu'elle s'affiche et s'exporte. */
    public function ligne(StockMovement $m, array $documents): array
    {
        $quantite = (float) $m->quantity;
        $document = $documents[$m->source_type][$m->source_id] ?? null;

        return [
            'date'      => $m->occurred_at,
            'article'   => $m->item?->name ?? '—',
            'reference' => $m->item?->reference,
            'unite'     => $m->item?->unit ?? '',
            'type'      => $m->type,
            'nature'    => $m->typeLabel(),
            'origine'   => $m->sourceLabel() . ($document ? ' ' . $document['numero'] : ''),
            'url'       => $document['url'] ?? null,
            'avant'     => $m->stockAvant(),
            'entree'    => $quantite > 0 ? $quantite : null,
            'sortie'    => $quantite < 0 ? -$quantite : null,
            'apres'     => (float) $m->stock_after,
            'cout'      => (int) $m->unit_cost,
            'valeur'    => $m->valeur(),
            'motif'     => $m->reason ?: '—',
            'par'       => $m->user?->name ?? '—',
        ];
    }
}

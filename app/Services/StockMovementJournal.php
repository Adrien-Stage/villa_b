<?php

namespace App\Services;

use App\Models\ExternalIssue;
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
     *               type?: ?string, source?: ?string, service?: ?string, recherche?: ?string}  $filtres
     */
    public function query(array $filtres): Builder
    {
        return StockMovement::query()
            ->with(['item:id,name,reference,unit,stock_category_id', 'user:id,name', 'stockImport:id,reference'])
            ->when($filtres['du'] ?? null, fn ($q, $du) => $q->where('occurred_at', '>=', $du->copy()->startOfDay()))
            ->when($filtres['au'] ?? null, fn ($q, $au) => $q->where('occurred_at', '<=', $au->copy()->endOfDay()))
            ->when($filtres['article'] ?? null, fn ($q, $id) => $q->where('stock_item_id', $id))
            ->when($filtres['categorie'] ?? null, fn ($q, $id) => $q->whereHas('item', fn ($i) => $i->where('stock_category_id', $id)))
            ->when($filtres['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filtres['source'] ?? null, fn ($q, $source) => $q->where('source_type', $source))
            ->when(!empty($filtres['service']), fn ($q) => $this->appliquerFiltreService($q, (string) $filtres['service']))
            // Sans tenir compte de la casse : LIKE la distingue sous PostgreSQL.
            ->when(trim((string) ($filtres['recherche'] ?? '')), fn ($q, $texte) => $q->whereRaw('LOWER(reason) LIKE ?', ['%' . mb_strtolower($texte) . '%']));
    }

    /**
     * Filtre les mouvements par département ou service demandeur / de destination.
     */
    private function appliquerFiltreService(Builder $query, string $service): void
    {
        if ($service === 'economat') {
            $query->where(function (Builder $sub) {
                // Opérations intrinsèques au magasin central
                $sub->whereIn('source_type', [
                    StockMovement::SOURCE_OPENING,
                    StockMovement::SOURCE_MANUAL,
                    StockMovement::SOURCE_PACKAGING,
                    StockMovement::SOURCE_EXTERNAL_ISSUE,
                ])
                // Inventaires du magasin central
                ->orWhere(function (Builder $q) {
                    $q->where('source_type', StockMovement::SOURCE_STOCK_COUNT)
                      ->where(function (Builder $sq) {
                          $sq->whereNull('source_id')
                             ->orWhereIn('source_id', function ($raw) {
                                 $raw->select('id')->from('stock_counts')
                                     ->where('department', 'economat')
                                     ->orWhereNull('department');
                             });
                      });
                })
                // Demandes internes de l'économat
                ->orWhere(function (Builder $q) {
                    $q->where('source_type', StockMovement::SOURCE_REQUISITION)
                      ->whereIn('source_id', function ($raw) {
                          $raw->select('id')->from('stock_requisitions')->where('department', 'economat');
                      });
                })
                // Réceptions fournisseurs sans demande extérieure (entrées directes en stock central)
                ->orWhere(function (Builder $q) {
                    $q->where('source_type', StockMovement::SOURCE_GOODS_RECEIPT)
                      ->whereNotIn('source_id', function ($raw) {
                          $raw->select('gr.id')
                              ->from('goods_receipts as gr')
                              ->join('purchase_orders as po', 'po.id', '=', 'gr.purchase_order_id')
                              ->join('purchase_requests as pr', 'pr.id', '=', 'po.purchase_request_id')
                              ->whereNotNull('pr.department')
                              ->where('pr.department', '!=', 'economat');
                      });
                })
                // Bons de commande sans demande extérieure
                ->orWhere(function (Builder $q) {
                    $q->where('source_type', StockMovement::SOURCE_PURCHASE_ORDER)
                      ->whereNotIn('source_id', function ($raw) {
                          $raw->select('po.id')
                              ->from('purchase_orders as po')
                              ->join('purchase_requests as pr', 'pr.id', '=', 'po.purchase_request_id')
                              ->whereNotNull('pr.department')
                              ->where('pr.department', '!=', 'economat');
                      });
                })
                // Imports non rattachés à un dépôt de service
                ->orWhere(function (Builder $q) {
                    $q->where('source_type', StockMovement::SOURCE_IMPORT)
                      ->where(function (Builder $sq) {
                          $sq->whereNull('stock_import_id')
                             ->orWhereIn('stock_import_id', function ($raw) {
                                 $raw->select('id')->from('stock_imports')->whereNull('service_store_id');
                             });
                      });
                })
                ->orWhereRaw('LOWER(reason) LIKE ?', ['%économat%'])
                ->orWhereRaw('LOWER(reason) LIKE ?', ['%economat%'])
                ->orWhereRaw('LOWER(reason) LIKE ?', ['%magasin central%']);
            });
            return;
        }

        // Cas dépôt de service spécifique: depot-{id}
        if (str_starts_with($service, 'depot-')) {
            $storeId = (int) substr($service, 6);
            $store = \App\Models\ServiceStore::find($storeId);
            $storeName = $store ? mb_strtolower($store->name) : null;

            $query->where(function (Builder $sub) use ($storeId, $storeName) {
                $sub->where(function (Builder $q) use ($storeId) {
                    $q->where('source_type', StockMovement::SOURCE_REQUISITION)
                      ->whereIn('source_id', function ($raw) use ($storeId) {
                          $raw->select('id')->from('stock_requisitions')->where('service_store_id', $storeId);
                      });
                })
                ->orWhere(function (Builder $q) use ($storeId) {
                    $q->where('source_type', StockMovement::SOURCE_IMPORT)
                      ->where(function (Builder $sq) use ($storeId) {
                          $sq->whereIn('source_id', function ($raw) use ($storeId) {
                              $raw->select('id')->from('stock_imports')->where('service_store_id', $storeId);
                          })->orWhereIn('stock_import_id', function ($raw) use ($storeId) {
                              $raw->select('id')->from('stock_imports')->where('service_store_id', $storeId);
                          });
                      });
                });

                if ($storeName) {
                    $sub->orWhereRaw('LOWER(reason) LIKE ?', ['%' . $storeName . '%']);
                }
            });
            return;
        }

        // Cas département standard
        $dept = $service;
        $purchaseDepts = match ($dept) {
            'restaurant'   => ['restaurant', 'cuisine', 'bar'],
            'technique'    => ['technique', 'maintenance'],
            'housekeeping' => ['housekeeping', 'hebergement'],
            'hebergement'  => ['hebergement', 'reception'],
            'comptabilite' => ['comptabilite', 'finance', 'finances', 'direction'],
            default        => [$dept],
        };

        $keywords = match ($dept) {
            'restaurant'   => ['restaurant', 'cuisine', 'bar', 'restauration'],
            'technique'    => ['technique', 'maintenance'],
            'housekeeping' => ['housekeeping', 'étage', 'etage', 'chambre'],
            'hebergement'  => ['hébergement', 'hebergement', 'réception', 'reception'],
            'boutique'     => ['boutique'],
            'comptabilite' => ['comptabilité', 'comptabilite', 'finance'],
            'autre'        => ['autre'],
            default        => [$dept],
        };

        $query->where(function (Builder $sub) use ($dept, $purchaseDepts, $keywords) {
            // 1. Demandes de réquisition du département ou de ses dépôts
            $sub->where(function (Builder $q) use ($dept) {
                $q->where('source_type', StockMovement::SOURCE_REQUISITION)
                  ->whereIn('source_id', function ($raw) use ($dept) {
                      $raw->select('id')->from('stock_requisitions')
                          ->where('department', $dept)
                          ->orWhereIn('service_store_id', function ($subRaw) use ($dept) {
                              $subRaw->select('id')->from('service_stores')->where('department', $dept);
                          });
                  });
            });

            // 2. Découpes boucherie vers le restaurant
            if ($dept === 'restaurant') {
                $sub->orWhere('source_type', StockMovement::SOURCE_CUT);
            }

            // 3. Imports assignés à un dépôt de ce département
            $sub->orWhere(function (Builder $q) use ($dept) {
                $q->where('source_type', StockMovement::SOURCE_IMPORT)
                  ->where(function (Builder $sq) use ($dept) {
                      $sq->whereIn('source_id', function ($raw) use ($dept) {
                          $raw->select('id')->from('stock_imports')
                              ->whereIn('service_store_id', function ($subRaw) use ($dept) {
                                  $subRaw->select('id')->from('service_stores')->where('department', $dept);
                              });
                      })->orWhereIn('stock_import_id', function ($raw) use ($dept) {
                          $raw->select('id')->from('stock_imports')
                              ->whereIn('service_store_id', function ($subRaw) use ($dept) {
                                  $subRaw->select('id')->from('service_stores')->where('department', $dept);
                              });
                      });
                  });
            });

            // 4. Réceptions issues d'achats pour ce département
            $sub->orWhere(function (Builder $q) use ($purchaseDepts) {
                $q->where('source_type', StockMovement::SOURCE_GOODS_RECEIPT)
                  ->whereIn('source_id', function ($raw) use ($purchaseDepts) {
                      $raw->select('gr.id')
                          ->from('goods_receipts as gr')
                          ->join('purchase_orders as po', 'po.id', '=', 'gr.purchase_order_id')
                          ->join('purchase_requests as pr', 'pr.id', '=', 'po.purchase_request_id')
                          ->whereIn('pr.department', $purchaseDepts);
                  });
            });

            // 5. Bons de commande pour ce département
            $sub->orWhere(function (Builder $q) use ($purchaseDepts) {
                $q->where('source_type', StockMovement::SOURCE_PURCHASE_ORDER)
                  ->whereIn('source_id', function ($raw) use ($purchaseDepts) {
                      $raw->select('po.id')
                          ->from('purchase_orders as po')
                          ->join('purchase_requests as pr', 'pr.id', '=', 'po.purchase_request_id')
                          ->whereIn('pr.department', $purchaseDepts);
                  });
            });

            // 6. Inventaires physiques de ce département
            $sub->orWhere(function (Builder $q) use ($dept) {
                $q->where('source_type', StockMovement::SOURCE_STOCK_COUNT)
                  ->whereIn('source_id', function ($raw) use ($dept) {
                      $raw->select('id')->from('stock_counts')->where('department', $dept);
                  });
            });

            // 7. Mots-clés dans reason
            foreach ($keywords as $kw) {
                $sub->orWhereRaw('LOWER(reason) LIKE ?', ['%' . mb_strtolower($kw) . '%']);
            }
        });
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
            StockMovement::SOURCE_EXTERNAL_ISSUE => [ExternalIssue::class, 'number', 'economat.external_issues.show'],
            StockMovement::SOURCE_CUT            => [\App\Models\StockCut::class, 'number', 'economat.cuts.show'],
            StockMovement::SOURCE_IMPORT         => [\App\Models\StockImport::class, 'reference', 'economat.classeur.show'],
            StockMovement::SOURCE_TRANSFER       => [\App\Models\StockTransfer::class, 'number', 'economat.transfers.show'],
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
            'origine'   => $m->sourceLabel() . ($document ? ' ' . $document['numero'] : '')
                . ($m->stockImport && $m->source_type !== StockMovement::SOURCE_IMPORT ? ' · import ' . $m->stockImport->reference : ''),
            'url'       => $document['url'] ?? null,
            'avant'     => $m->stockAvant(),
            'entree'    => $quantite > 0 ? $quantite : null,
            'sortie'    => $quantite < 0 ? -$quantite : null,
            'apres'     => (float) $m->stock_after,
            // Unités fermées après le mouvement, et ce qu'il a ouvert.
            'apres_decompose' => $m->stockApresDecompose(),
            'ouvertures' => $m->source_type === StockMovement::SOURCE_PACKAGING ? null : $m->ouvertures(),
            'cout'      => (int) $m->unit_cost,
            'valeur'    => $m->valeur(),
            'motif'     => $m->reason ?: '—',
            'par'       => $m->user?->name ?? '—',
        ];
    }
}

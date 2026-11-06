<?php

namespace App\Services;

use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Models\ServiceStoreCountLine;
use App\Models\ServiceStoreMovement;
use App\Models\ServiceStoreStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cycle de l'inventaire d'un dépôt : ouverture (théorique figé), comptage,
 * clôture qui inscrit la consommation au dépôt.
 *
 * Pendant le comptage, le dépôt ne reçoit plus de livraison : une entrée
 * passée entre l'ouverture et la clôture serait comptée comme consommée.
 */
class ServiceStoreCountService
{
    public function open(ServiceStore $store, ?User $user = null, ?string $notes = null): ServiceStoreCount
    {
        return DB::transaction(function () use ($store, $user, $notes) {
            // Verrou du dépôt : deux ouvertures simultanées ne passent pas.
            $store = ServiceStore::query()->lockForUpdate()->findOrFail($store->id);

            if ($encours = self::inProgress($store)) {
                throw new RuntimeException("L'inventaire {$encours->reference} du dépôt {$store->name} est déjà en cours.");
            }

            $inventaire = ServiceStoreCount::create([
                'service_store_id' => $store->id,
                'status'           => ServiceStoreCount::STATUS_DRAFT,
                'count_date'       => now()->toDateString(),
                'notes'            => $notes,
                'opened_by'        => $user?->id ?? auth()->id(),
            ]);

            // Verrou des stocks : une livraison en cours se termine avant le relevé.
            $stocks = ServiceStoreStock::query()
                ->where('service_store_id', $store->id)
                ->lockForUpdate()
                ->get();

            foreach ($stocks as $stock) {
                ServiceStoreCountLine::create([
                    'service_store_count_id' => $inventaire->id,
                    'stock_item_id'          => $stock->stock_item_id,
                    'theoretical_quantity'   => $stock->current_stock,
                    'unit_cost'              => $stock->average_cost,
                ]);
            }

            return $inventaire->load('lines.item');
        });
    }

    /** @param  array<int, array{counted_quantity?: mixed, notes?: string|null}>  $saisies  [ligne => saisie] */
    public function updateCounts(ServiceStoreCount $inventaire, array $saisies): void
    {
        if ($inventaire->isClosed()) {
            throw new RuntimeException("Cet inventaire est déjà clôturé.");
        }

        if ($inventaire->isSubmitted()) {
            throw new RuntimeException("L'inventaire {$inventaire->reference} est soumis au RAF pour validation et ne peut plus être modifié.");
        }

        if ($inventaire->created_at && now()->greaterThan($inventaire->created_at->copy()->addHours(48))) {
            throw new RuntimeException("Le délai de 48h pour la saisie de l'inventaire {$inventaire->reference} est expiré. L'inventaire est verrouillé.");
        }

        DB::transaction(function () use ($inventaire, $saisies) {
            $inventaire = $this->lockDraft($inventaire);

            foreach ($inventaire->lines()->get() as $ligne) {
                if (!array_key_exists($ligne->id, $saisies)) {
                    continue;
                }

                $compte = $saisies[$ligne->id]['counted_quantity'] ?? null;

                $ligne->update([
                    'counted_quantity' => $compte === null || $compte === '' ? null : max(0, (float) $compte),
                    'notes'            => trim((string) ($saisies[$ligne->id]['notes'] ?? '')) ?: null,
                ]);
            }
        });
    }

    /**
     * Soumet la feuille d'inventaire de dépôt saisie par le contrôleur au RAF pour validation.
     */
    public function submit(ServiceStoreCount $inventaire, ?User $user = null): ServiceStoreCount
    {
        return DB::transaction(function () use ($inventaire, $user) {
            $inventaire = ServiceStoreCount::query()->lockForUpdate()->findOrFail($inventaire->id);

            if ($inventaire->isClosed()) {
                throw new RuntimeException("Cet inventaire est déjà validé.");
            }

            if (!$inventaire->isDraft() && !$inventaire->isRejected()) {
                throw new RuntimeException("Seul un inventaire en brouillon ou rejeté peut être soumis au RAF.");
            }

            $inventaire->update([
                'status'       => ServiceStoreCount::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $user?->id ?? auth()->id(),
            ]);

            return $inventaire->fresh(['lines.item', 'store']);
        });
    }

    /**
     * Rejette la soumission d'inventaire par le RAF avec un motif obligatoire.
     */
    public function reject(ServiceStoreCount $inventaire, string $reason, ?User $user = null): ServiceStoreCount
    {
        if (empty(trim($reason))) {
            throw new RuntimeException("Un motif de rejet est obligatoire.");
        }

        return DB::transaction(function () use ($inventaire, $reason, $user) {
            $inventaire = ServiceStoreCount::query()->lockForUpdate()->findOrFail($inventaire->id);

            if (!$inventaire->isSubmitted()) {
                throw new RuntimeException("Seul un inventaire soumis au RAF peut être rejeté.");
            }

            $inventaire->update([
                'status'           => ServiceStoreCount::STATUS_REJECTED,
                'rejected_at'      => now(),
                'rejected_by'      => $user?->id ?? auth()->id(),
                'rejection_reason' => trim($reason),
            ]);

            return $inventaire->fresh(['lines.item', 'store']);
        });
    }

    /**
     * Clôture (Exclusivité RAF) : chaque ligne comptée cale le stock du dépôt sur le compté.
     * L'écart, presque toujours négatif, est la consommation du service.
     * Une ligne non comptée garde son stock théorique.
     */
    public function close(ServiceStoreCount $inventaire, ?User $user = null): ServiceStoreCount
    {
        return DB::transaction(function () use ($inventaire, $user) {
            $inventaire = ServiceStoreCount::query()->lockForUpdate()->findOrFail($inventaire->id);

            if ($inventaire->isClosed()) {
                throw new RuntimeException("Cet inventaire est déjà clôturé (plus en cours de comptage).");
            }

            if (!$inventaire->isDraft() && !$inventaire->isSubmitted() && !$inventaire->isRejected()) {
                throw new RuntimeException("L'inventaire {$inventaire->reference} n'est pas dans un état validable.");
            }

            $inventaire->load('lines.item', 'store');

            foreach ($inventaire->lines as $ligne) {
                $ecart = $ligne->varianceQuantity();

                if (!$ligne->isCounted() || abs($ecart) < 0.0005 || $ligne->item === null) {
                    continue;
                }

                $stock = ServiceStoreStock::query()
                    ->where('service_store_id', $inventaire->service_store_id)
                    ->where('stock_item_id', $ligne->stock_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $stock->update(['current_stock' => $ligne->counted_quantity]);

                ServiceStoreMovement::create([
                    'service_store_id' => $inventaire->service_store_id,
                    'stock_item_id'    => $ligne->stock_item_id,
                    'type'             => ServiceStoreMovement::TYPE_ADJUSTMENT,
                    'quantity'         => $ecart,
                    'stock_after'      => $ligne->counted_quantity,
                    'unit_cost'        => $ligne->unit_cost,
                    'stock_account'    => $ligne->item->stockAccount(),
                    'source_type'      => ServiceStoreMovement::SOURCE_STOCK_COUNT,
                    'source_id'        => $inventaire->id,
                    'reason'           => ($ecart < 0 ? 'Consommation' : 'Excédent') . " — inventaire {$inventaire->reference}",
                    'user_id'          => $user?->id ?? auth()->id(),
                    'occurred_at'      => now(),
                ]);
            }

            $maintenant = now();
            $inventaire->update([
                'status'       => ServiceStoreCount::STATUS_CLOSED,
                'closed_by'    => $user?->id ?? auth()->id(),
                'closed_at'    => $maintenant,
                'validated_by' => $user?->id ?? auth()->id(),
                'validated_at' => $maintenant,
            ]);

            return $inventaire->fresh(['lines.item', 'store']);
        });
    }

    /**
     * Annule une feuille d'inventaire :
     * - En cours (draft/submitted/rejected) : sans impact de stock.
     * - Validée : annulation par le RAF sous 48h maximum avec contre-passation complète des mouvements.
     */
    public function cancel(ServiceStoreCount $inventaire, ?User $user = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($inventaire, $user, $reason) {
            $inventaire = ServiceStoreCount::query()->lockForUpdate()->findOrFail($inventaire->id);

            if ($inventaire->isCancelled()) {
                throw new RuntimeException("Cet inventaire est déjà annulé.");
            }

            if ($inventaire->isClosed()) {
                // Règle stricte des 48h
                $dateValidation = $inventaire->validated_at ?? $inventaire->closed_at;
                if ($dateValidation && now()->greaterThan($dateValidation->copy()->addHours(48))) {
                    throw new RuntimeException("Le délai légal de 48h pour annuler la validation de cet inventaire est expiré. L'inventaire {$inventaire->reference} est définitivement verrouillé.");
                }

                // Contre-passation des ajustements de stock
                $inventaire->loadMissing('lines.item');
                foreach ($inventaire->lines as $ligne) {
                    $ecart = $ligne->varianceQuantity();

                    if (!$ligne->isCounted() || abs($ecart) < 0.0005 || $ligne->item === null) {
                        continue;
                    }

                    $stock = ServiceStoreStock::query()
                        ->where('service_store_id', $inventaire->service_store_id)
                        ->where('stock_item_id', $ligne->stock_item_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stock) {
                        $nouveauStock = max(0, (float) $stock->current_stock - $ecart);
                        $stock->update(['current_stock' => $nouveauStock]);

                        ServiceStoreMovement::create([
                            'service_store_id' => $inventaire->service_store_id,
                            'stock_item_id'    => $ligne->stock_item_id,
                            'type'             => ServiceStoreMovement::TYPE_ADJUSTMENT,
                            'quantity'         => -$ecart,
                            'stock_after'      => $nouveauStock,
                            'unit_cost'        => $ligne->unit_cost,
                            'stock_account'    => $ligne->item->stockAccount(),
                            'source_type'      => ServiceStoreMovement::SOURCE_STOCK_COUNT,
                            'source_id'        => $inventaire->id,
                            'reason'           => "Contre-passation annulation inventaire {$inventaire->reference} par RAF" . ($reason ? " ({$reason})" : ''),
                            'user_id'          => $user?->id ?? auth()->id(),
                            'occurred_at'      => now(),
                        ]);
                    }
                }

                $inventaire->update([
                    'status'              => ServiceStoreCount::STATUS_CANCELLED,
                    'cancelled_at'        => now(),
                    'cancelled_by'        => $user?->id ?? auth()->id(),
                    'cancellation_reason' => $reason ?? 'Annulation par le RAF sous 48h',
                ]);
            } else {
                $inventaire->update([
                    'status'              => ServiceStoreCount::STATUS_CANCELLED,
                    'cancelled_at'        => now(),
                    'cancelled_by'        => $user?->id ?? auth()->id(),
                    'cancellation_reason' => $reason ?? 'Annulation de la feuille de comptage',
                ]);
            }
        });

        $inventaire->refresh();
    }

    public static function inProgress(ServiceStore $store): ?ServiceStoreCount
    {
        return ServiceStoreCount::query()
            ->where('service_store_id', $store->id)
            ->whereIn('status', [ServiceStoreCount::STATUS_DRAFT, ServiceStoreCount::STATUS_SUBMITTED])
            ->first();
    }

    private function lockDraft(ServiceStoreCount $inventaire): ServiceStoreCount
    {
        $inventaire = ServiceStoreCount::query()->lockForUpdate()->findOrFail($inventaire->id);

        if (!$inventaire->isDraft() && !$inventaire->isRejected()) {
            throw new RuntimeException("L'inventaire {$inventaire->reference} n'est plus en cours de comptage.");
        }

        if ($inventaire->created_at && now()->greaterThan($inventaire->created_at->copy()->addHours(48))) {
            throw new RuntimeException("Le délai de 48h pour la saisie de l'inventaire {$inventaire->reference} est expiré. L'inventaire est verrouillé.");
        }

        return $inventaire;
    }
}

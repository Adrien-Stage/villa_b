<?php

namespace App\Services;

use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockCountService
{
    public function __construct(private StockService $stockService)
    {
    }

    /**
     * Ouvre une nouvelle feuille d'inventaire physique.
     * Fige le stock théorique et le CUMP de tous les articles concernés.
     */
    public function open(array $data, ?User $user = null): StockCount
    {
        $department = !empty($data['department']) ? (string) $data['department'] : 'economat';

        // Un seul inventaire ouvert ou soumis à la fois pour ce service
        $existing = StockCount::whereIn('status', [StockCount::STATUS_DRAFT, StockCount::STATUS_SUBMITTED])
            ->where(function ($q) use ($department) {
                if ($department === 'economat') {
                    $q->where('department', 'economat')->orWhereNull('department');
                } else {
                    $q->where('department', $department);
                }
            })->first();

        if ($existing) {
            throw new RuntimeException("L'inventaire {$existing->reference} pour ce service est déjà en cours. Veuillez le clôturer ou l'annuler avant d'en ouvrir un nouveau.");
        }

        return DB::transaction(function () use ($data, $user, $department) {
            $countDate = !empty($data['count_date']) ? Carbon::parse($data['count_date'])->toDateString() : now()->toDateString();
            $categoryId = !empty($data['stock_category_id']) ? (int) $data['stock_category_id'] : null;
            // Une zone de rangement : la cave, une chambre froide… comptée à part.
            $zoneId = !empty($data['storage_zone_id']) ? (int) $data['storage_zone_id'] : null;

            $stockCount = StockCount::create([
                'department'        => $department,
                'stock_category_id' => $categoryId,
                'storage_zone_id'   => $zoneId,
                'status'            => StockCount::STATUS_DRAFT,
                'count_date'        => $countDate,
                'notes'             => $data['notes'] ?? null,
                'opened_by'         => $user?->id ?? Auth::id(),
                // Le contrôle des stocks lit les écarts de l'établissement : un
                // inventaire sans établissement n'y paraîtrait jamais.
                'tenant_id'         => ($user ?? Auth::user())?->tenant_id ?? \App\Models\Tenant::current()?->id,
            ]);

            $query = StockItem::query()->active()->orderBy('name');
            if ($categoryId) {
                $query->where('stock_category_id', $categoryId);
            }
            if ($zoneId) {
                $query->where('storage_zone_id', $zoneId);
            }

            // Verrou des articles : un mouvement en cours se termine avant le
            // relevé du théorique, et le suivant verra l'inventaire ouvert.
            $items = $query->lockForUpdate()->get();
            $totalTheoreticalValue = 0;

            foreach ($items as $item) {
                $theoreticalQty = (float) $item->current_stock;
                $unitCost = (int) $item->average_cost;
                $theoreticalValue = (int) round($theoreticalQty * $unitCost);

                StockCountLine::create([
                    'stock_count_id'       => $stockCount->id,
                    'stock_item_id'        => $item->id,
                    'theoretical_quantity' => $theoreticalQty,
                    'counted_quantity'     => null, // non compté initialement
                    'variance_quantity'    => 0,
                    'unit_cost'            => $unitCost,
                    'theoretical_value'    => $theoreticalValue,
                    'counted_value'        => null,
                    'variance_value'       => 0,
                ]);

                $totalTheoreticalValue += $theoreticalValue;
            }

            $stockCount->update([
                'total_theoretical_value' => $totalTheoreticalValue,
            ]);

            return $stockCount->fresh('lines.item');
        });
    }

    /**
     * Enregistre les comptages physiques saisis par l'économe/magasinier.
     * Met à jour les écarts et motifs sans modifier le stock réel.
     */
    public function updateCounts(StockCount $stockCount, array $linesData): StockCount
    {
        if ($stockCount->isClosed()) {
            throw new RuntimeException("Cet inventaire est déjà clôturé.");
        }

        if ($stockCount->isSubmitted()) {
            throw new RuntimeException("L'inventaire {$stockCount->reference} est soumis au RAF pour validation et ne peut plus être modifié.");
        }

        if ($stockCount->created_at && now()->greaterThan($stockCount->created_at->copy()->addHours(48))) {
            throw new RuntimeException("Le délai de 48h pour la saisie de l'inventaire {$stockCount->reference} est expiré. L'inventaire est verrouillé.");
        }

        return DB::transaction(function () use ($stockCount, $linesData) {
            $stockCount = $this->lockDraft($stockCount);
            $stockCount->load('lines.item.packagings');

            $totalCountedValue = 0;
            $totalVariance = 0;
            $totalLoss = 0;
            $totalSurplus = 0;

            foreach ($stockCount->lines as $line) {
                if (!array_key_exists($line->id, $linesData)) {
                    continue;
                }

                $input = $linesData[$line->id];
                $countedRaw = $input['counted_quantity'] ?? null;
                $reason = !empty($input['reason']) ? trim((string) $input['reason']) : null;
                $notes = !empty($input['notes']) ? trim((string) $input['notes']) : null;

                // Compté par niveau (3 cartons, 18 paquets, 5 pièces) : le total
                // s'en déduit, et le détail calera les unités fermées à la clôture.
                [$detail, $totalDetail] = $this->detailCompte($line, $input);
                if ($detail !== null) {
                    $countedRaw = $totalDetail;
                }

                if ($countedRaw === null || $countedRaw === '') {
                    $line->update([
                        'counted_quantity'  => null,
                        'packaging_counts'  => null,
                        'variance_quantity' => 0,
                        'counted_value'     => null,
                        'variance_value'    => 0,
                        'reason'            => null,
                        'notes'             => $notes,
                    ]);
                    continue;
                }

                $countedQty = max(0, (float) $countedRaw);
                $theoreticalQty = (float) $line->theoretical_quantity;
                $varianceQty = round($countedQty - $theoreticalQty, 3);
                $unitCost = (int) $line->unit_cost;
                $countedValue = (int) round($countedQty * $unitCost);
                $varianceValue = (int) round($varianceQty * $unitCost);

                $line->update([
                    'counted_quantity'  => $countedQty,
                    'packaging_counts'  => $detail,
                    'variance_quantity' => $varianceQty,
                    'counted_value'     => $countedValue,
                    'variance_value'    => $varianceValue,
                    'reason'            => $varianceQty != 0 ? $reason : null,
                    'notes'             => $notes,
                ]);

                $totalCountedValue += $countedValue;
                $totalVariance += $varianceValue;

                if ($varianceValue < 0) {
                    $totalLoss += abs($varianceValue);
                } elseif ($varianceValue > 0) {
                    $totalSurplus += $varianceValue;
                }
            }

            $stockCount->update([
                'total_counted_value' => $totalCountedValue,
                'variance_value'      => $totalVariance,
                'loss_value'          => $totalLoss,
                'surplus_value'       => $totalSurplus,
            ]);

            return $stockCount->fresh('lines.item');
        });
    }

    /**
     * Soumet la feuille d'inventaire saisie par le contrôleur au RAF pour validation.
     */
    public function submit(StockCount $stockCount, ?User $user = null): StockCount
    {
        return DB::transaction(function () use ($stockCount, $user) {
            $stockCount = StockCount::query()->lockForUpdate()->findOrFail($stockCount->id);

            if ($stockCount->isClosed()) {
                throw new RuntimeException("Cet inventaire est déjà validé.");
            }

            if (!$stockCount->isDraft() && !$stockCount->isRejected()) {
                throw new RuntimeException("Seul un inventaire en brouillon ou rejeté peut être soumis au RAF.");
            }

            $stockCount->update([
                'status'       => StockCount::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $user?->id ?? Auth::id(),
            ]);

            return $stockCount->fresh('lines.item');
        });
    }

    /**
     * Rejette la soumission d'inventaire par le RAF avec un motif obligatoire.
     */
    public function reject(StockCount $stockCount, string $reason, ?User $user = null): StockCount
    {
        if (empty(trim($reason))) {
            throw new RuntimeException("Un motif de rejet est obligatoire.");
        }

        return DB::transaction(function () use ($stockCount, $reason, $user) {
            $stockCount = StockCount::query()->lockForUpdate()->findOrFail($stockCount->id);

            if (!$stockCount->isSubmitted()) {
                throw new RuntimeException("Seul un inventaire soumis au RAF peut être rejeté.");
            }

            $stockCount->update([
                'status'           => StockCount::STATUS_REJECTED,
                'rejected_at'      => now(),
                'rejected_by'      => $user?->id ?? Auth::id(),
                'rejection_reason' => trim($reason),
            ]);

            return $stockCount->fresh('lines.item');
        });
    }

    /**
     * Valide et clôture l'inventaire physique (Exclusivité RAF) :
     * 1. Ajuste le stock réel de chaque article ayant un écart via StockService::adjust
     * 2. Fige les montants financiers et passe le statut à closed
     */
    public function close(StockCount $stockCount, ?User $user = null): StockCount
    {
        if ($stockCount->isClosed()) {
            throw new RuntimeException("Cet inventaire est déjà clôturé.");
        }

        return DB::transaction(function () use ($stockCount, $user) {
            $stockCount = StockCount::query()->lockForUpdate()->findOrFail($stockCount->id);

            if (!$stockCount->isDraft() && !$stockCount->isSubmitted() && !$stockCount->isRejected()) {
                throw new RuntimeException("L'inventaire {$stockCount->reference} n'est pas dans un état validable.");
            }

            $stockCount->load('lines.item.packagings');

            $totalCountedValue = 0;
            $totalVariance = 0;
            $totalLoss = 0;
            $totalSurplus = 0;

            foreach ($stockCount->lines as $line) {
                if (!$line->isCounted() || !$line->item) {
                    continue;
                }

                $counted = (float) $line->counted_quantity;
                $theoretical = (float) $line->theoretical_quantity;
                $variance = round($counted - $theoretical, 3);
                $unitCost = (int) $line->unit_cost;
                $varianceValue = (int) round($variance * $unitCost);
                $countedValue = (int) round($counted * $unitCost);

                $line->update([
                    'variance_quantity' => $variance,
                    'counted_value'     => $countedValue,
                    'variance_value'    => $varianceValue,
                ]);

                $totalCountedValue += $countedValue;
                $totalVariance += $varianceValue;

                if ($varianceValue < 0) {
                    $totalLoss += abs($varianceValue);
                } elseif ($varianceValue > 0) {
                    $totalSurplus += $varianceValue;
                }

                // Ajustement physique si écart significatif
                if (abs($variance) >= 0.0005) {
                    $reasonLabel = $line->reasonLabel();
                    $reasonText = "Inventaire {$stockCount->reference}";
                    if ($stockCount->department && $stockCount->department !== 'economat') {
                        $reasonText .= " — {$stockCount->departmentLabel()}";
                    }
                    if (!empty($line->reason)) {
                        $reasonText .= " ({$reasonLabel})";
                    }
                    if (!empty($line->notes)) {
                        $reasonText .= " — {$line->notes}";
                    }

                    $this->stockService->adjust(
                        item: $line->item,
                        countedQuantity: $counted,
                        reason: $reasonText,
                        sourceType: StockMovement::SOURCE_STOCK_COUNT,
                        sourceId: $stockCount->id,
                    );
                }

                // Compté par niveau : les unités fermées se calent sur le comptage.
                if (!empty($line->packaging_counts)) {
                    $this->stockService->recompterConditionnements(
                        $line->item,
                        $line->packaging_counts,
                        "Inventaire {$stockCount->reference}",
                        StockMovement::SOURCE_STOCK_COUNT,
                        $stockCount->id,
                    );
                }
            }

            $maintenant = now();
            $stockCount->update([
                'status'              => StockCount::STATUS_CLOSED,
                'total_counted_value' => $totalCountedValue,
                'variance_value'      => $totalVariance,
                'loss_value'          => $totalLoss,
                'surplus_value'       => $totalSurplus,
                'closed_by'           => $user?->id ?? Auth::id(),
                'closed_at'           => $maintenant,
                'validated_by'        => $user?->id ?? Auth::id(),
                'validated_at'        => $maintenant,
            ]);

            return $stockCount->fresh('lines.item');
        });
    }

    /**
     * Annule une feuille d'inventaire :
     * - En cours (draft/submitted/rejected) : annulation sans impact de stock.
     * - Déjà validée (closed) : annulation exclusive RAF sous 48h maximum avec contre-écritures complètes.
     */
    public function cancel(StockCount $stockCount, ?User $user = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($stockCount, $user, $reason) {
            $stockCount = StockCount::query()->lockForUpdate()->findOrFail($stockCount->id);

            if ($stockCount->isCancelled()) {
                throw new RuntimeException("Cet inventaire est déjà annulé.");
            }

            if ($stockCount->isClosed()) {
                // Règle stricte des 48h
                $dateValidation = $stockCount->validated_at ?? $stockCount->closed_at;
                if ($dateValidation && now()->greaterThan($dateValidation->copy()->addHours(48))) {
                    throw new RuntimeException("Le délai légal de 48h pour annuler la validation de cet inventaire est expiré. L'inventaire {$stockCount->reference} est définitivement verrouillé.");
                }

                // Contre-passation des ajustements de stocks
                $stockCount->loadMissing('lines.item');
                foreach ($stockCount->lines as $line) {
                    if (!$line->isCounted() || !$line->item) {
                        continue;
                    }

                    $variance = (float) $line->variance_quantity;
                    if (abs($variance) >= 0.0005) {
                        $this->stockService->adjust(
                            item: $line->item,
                            countedQuantity: max(0, (float) $line->item->current_stock - $variance),
                            reason: "Contre-passation annulation inventaire {$stockCount->reference} par RAF" . ($reason ? " ({$reason})" : ''),
                            sourceType: StockMovement::SOURCE_STOCK_COUNT,
                            sourceId: $stockCount->id,
                        );
                    }
                }

                $stockCount->update([
                    'status'              => StockCount::STATUS_CANCELLED,
                    'cancelled_at'        => now(),
                    'cancelled_by'        => $user?->id ?? Auth::id(),
                    'cancellation_reason' => $reason ?? 'Annulation par le RAF sous 48h',
                ]);
            } else {
                $stockCount->update([
                    'status'              => StockCount::STATUS_CANCELLED,
                    'cancelled_at'        => now(),
                    'cancelled_by'        => $user?->id ?? Auth::id(),
                    'cancellation_reason' => $reason ?? 'Annulation de la feuille de comptage',
                ]);
            }
        });

        $stockCount->refresh();
    }

    /**
     * Relit la feuille sous verrou et exige qu'elle soit encore modifiable (draft ou rejected, < 48h).
     */
    private function lockDraft(StockCount $stockCount): StockCount
    {
        $stockCount = StockCount::query()->lockForUpdate()->findOrFail($stockCount->id);

        if (!$stockCount->isDraft() && !$stockCount->isRejected()) {
            throw new RuntimeException("L'inventaire {$stockCount->reference} n'est plus en cours de comptage.");
        }

        if ($stockCount->created_at && now()->greaterThan($stockCount->created_at->copy()->addHours(48))) {
            throw new RuntimeException("Le délai de 48h pour la saisie de l'inventaire {$stockCount->reference} est expiré. L'inventaire est verrouillé.");
        }

        return $stockCount;
    }

    /**
     * Le comptage par niveau d'une ligne : [détail, total dans l'unité de
     * l'article], ou [null, null] quand la ligne est saisie en total.
     *
     * @return array{0: ?array<string, int>, 1: ?float}
     */
    private function detailCompte(StockCountLine $line, array $input): array
    {
        $niveaux = $line->item?->packagings ?? collect();
        $fermes = array_filter((array) ($input['fermes'] ?? []), fn ($v) => $v !== null && $v !== '');
        $vrac = $input['vrac'] ?? null;

        if ($niveaux->isEmpty() || ($fermes === [] && ($vrac === null || $vrac === ''))) {
            return [null, null];
        }

        $detail = [];
        $total = max(0.0, (float) $vrac);
        foreach ($niveaux as $niveau) {
            $nombre = max(0, (int) ($fermes[$niveau->name] ?? 0));
            $detail[$niveau->name] = $nombre;
            $total += $nombre * (float) $niveau->factor;
        }

        return [$detail, round($total, 3)];
    }
}

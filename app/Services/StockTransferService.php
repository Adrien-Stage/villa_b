<?php

namespace App\Services;

use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bons de transfert : l'économat approvisionne lui-même un dépôt qui lui
 * appartient. Chaque ligne sort du magasin central au coût moyen et entre au
 * dépôt au même coût ; l'annulation fait le chemin inverse, tant que le dépôt
 * a encore la marchandise.
 */
class StockTransferService
{
    public function __construct(private StockService $stock, private ServiceStoreService $depots)
    {
    }

    /**
     * Seuls les dépôts de l'économat se servent par transfert : ceux des
     * autres services passent par une demande, que leur chef vise.
     */
    public static function peutRecevoir(ServiceStore $depot): bool
    {
        return $depot->is_active && $depot->department === 'economat';
    }

    /**
     * @param  array{notes?: ?string, lines: list<array{stock_item_id: int, quantity: float, packaging?: ?string}>}  $data
     */
    public function transferer(ServiceStore $depot, array $data, User $econome): StockTransfer
    {
        if (! self::peutRecevoir($depot)) {
            throw new RuntimeException("Le dépôt « {$depot->name} » n'appartient pas à l'économat : il se sert par une demande de son service.");
        }

        return DB::transaction(function () use ($depot, $data, $econome) {
            $transfert = StockTransfer::create([
                'service_store_id' => $depot->id,
                'notes'            => $this->texte($data['notes'] ?? null),
                'transferred_by'   => $econome->id,
                'issuer_signature' => $econome->signatureName(),
                'tenant_id'        => $econome->tenant_id ?? Tenant::current()?->id,
            ]);

            $total = 0;

            foreach ($data['lines'] as $ligne) {
                $item = StockItem::with('packagings')->findOrFail($ligne['stock_item_id']);

                // Transféré en cartons ou en paquets : la quantité se ramène à l'unité de l'article.
                $conditionnement = trim((string) ($ligne['packaging'] ?? ''));
                $conditionnement = $conditionnement === '' || $conditionnement === $item->unit ? null : $conditionnement;
                $quantite = round((float) $ligne['quantity'] * ($conditionnement !== null ? $item->facteurDe($conditionnement) : 1.0), 3);

                // Le moteur refuse une sortie sans stock et pendant un inventaire.
                $sortie = $this->stock->recordOut(
                    $item,
                    $quantite,
                    StockMovement::SOURCE_TRANSFER,
                    $transfert->id,
                    "Transfert {$transfert->number} vers le dépôt {$depot->name}",
                    $conditionnement
                );

                $cout = (int) $sortie->unit_cost;

                // Le dépôt refuse une entrée pendant son propre inventaire.
                $this->depots->receive(
                    $depot,
                    $item,
                    $quantite,
                    $cout,
                    ServiceStoreMovement::SOURCE_TRANSFER,
                    $transfert->id,
                    "Transfert {$transfert->number} du magasin central"
                );

                $valeur = (int) round($quantite * $cout);

                StockTransferLine::create([
                    'stock_transfer_id' => $transfert->id,
                    'stock_item_id'     => $item->id,
                    'quantity'          => $quantite,
                    'packaging_name'    => $conditionnement,
                    'unit_cost'         => $cout,
                    'total_cost'        => $valeur,
                ]);

                $total += $valeur;
            }

            $transfert->update(['total_value' => $total]);

            return $transfert->fresh(['lines.item', 'depot', 'transferredBy']);
        });
    }

    /** Annule un transfert : la marchandise quitte le dépôt et revient au magasin central, au coût où elle était partie. */
    public function annuler(StockTransfer $transfert, User $econome, string $motif): StockTransfer
    {
        return DB::transaction(function () use ($transfert, $econome, $motif) {
            // Verrou : une double annulation ferait revenir deux fois la marchandise.
            $transfert = StockTransfer::query()->lockForUpdate()->findOrFail($transfert->id);

            if ($transfert->isCancelled()) {
                throw new RuntimeException("Le bon {$transfert->number} est déjà annulé.");
            }

            $depot = $transfert->depot()->firstOrFail();

            foreach ($transfert->lines()->with('item')->get() as $ligne) {
                // Le dépôt doit encore l'avoir : ce qu'il a consommé ne revient pas.
                $this->depots->issue(
                    $depot,
                    $ligne->item,
                    (float) $ligne->quantity,
                    ServiceStoreMovement::SOURCE_TRANSFER,
                    $transfert->id,
                    "Annulation du transfert {$transfert->number} — {$motif}"
                );

                $this->stock->reverseOut(
                    $ligne->item,
                    (float) $ligne->quantity,
                    (int) $ligne->unit_cost,
                    StockMovement::SOURCE_TRANSFER,
                    $transfert->id,
                    "Annulation du transfert {$transfert->number} — {$motif}",
                    // Partis en cartons : ils reviennent fermés.
                    $ligne->packaging_name
                );
            }

            $transfert->update([
                'status'              => StockTransfer::STATUS_CANCELLED,
                'cancelled_by'        => $econome->id,
                'cancelled_at'        => now(),
                'cancellation_reason' => $motif,
            ]);

            return $transfert->fresh(['lines.item', 'depot', 'transferredBy', 'cancelledBy']);
        });
    }

    private function texte(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : $valeur;
    }
}

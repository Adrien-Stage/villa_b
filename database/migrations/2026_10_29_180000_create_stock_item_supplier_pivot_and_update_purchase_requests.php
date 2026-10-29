<?php

use App\Models\PurchaseRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table pivot multi-fournisseurs avec prix convenus distincts
        if (! Schema::hasTable('stock_item_supplier')) {
            Schema::create('stock_item_supplier', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                // Prix unitaire d'achat convenu / catalogue chez ce fournisseur en centimes FCFA
                $table->unsignedBigInteger('purchase_price')->default(0);
                $table->string('unit', 30)->nullable();
                $table->string('supplier_reference', 80)->nullable();
                $table->unsignedInteger('delivery_lead_time_days')->nullable();
                $table->timestamps();

                $table->unique(['stock_item_id', 'supplier_id']);
                $table->index(['stock_item_id']);
                $table->index(['supplier_id']);
            });
        }

        // 2. Rattachement direct de la Demande d'Achat (PurchaseRequest) à un fournisseur unique
        if (Schema::hasTable('purchase_requests') && ! Schema::hasColumn('purchase_requests', 'supplier_id')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->after('number')->constrained('suppliers')->nullOnDelete();
            });
        }

        // 3. Migration des données existantes
        // 3.a) Migrer chaque supplier_id existant de stock_items dans la table pivot
        $items = DB::table('stock_items')->whereNotNull('supplier_id')->get();
        $now = now();

        foreach ($items as $item) {
            $price = $item->last_purchase_price > 0 ? $item->last_purchase_price : ($item->average_cost > 0 ? $item->average_cost : 0);
            DB::table('stock_item_supplier')->updateOrInsert(
                [
                    'stock_item_id' => $item->id,
                    'supplier_id'   => $item->supplier_id,
                ],
                [
                    'purchase_price'          => $price,
                    'unit'                    => $item->unit,
                    'delivery_lead_time_days' => 2,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]
            );
        }

        // 3.b) Enrichir les données de démo avec plusieurs fournisseurs par article
        // pour que le calcul du prix moyen soit immédiatement démontrable
        $activeSuppliers = DB::table('suppliers')->where('is_active', true)->orderBy('id')->get();
        if ($activeSuppliers->count() >= 2) {
            $allStockItems = DB::table('stock_items')->orderBy('id')->get();
            $suppA = $activeSuppliers->get(0);
            $suppB = $activeSuppliers->get(1);
            $suppC = $activeSuppliers->get(2) ?? $suppA;

            foreach ($allStockItems as $item) {
                $basePrice = $item->last_purchase_price > 0 ? $item->last_purchase_price : ($item->average_cost > 0 ? $item->average_cost : 50000);
                if ($basePrice <= 0) {
                    $basePrice = 25000;
                }

                // S'assurer qu'au moins 2 fournisseurs approvisionnent l'article
                // Fournisseur 1 : basePrice
                DB::table('stock_item_supplier')->updateOrInsert(
                    ['stock_item_id' => $item->id, 'supplier_id' => $suppA->id],
                    [
                        'purchase_price' => $basePrice,
                        'unit'           => $item->unit,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]
                );

                // Fournisseur 2 : variation réaliste (+10%)
                $priceB = (int) round($basePrice * 1.10);
                DB::table('stock_item_supplier')->updateOrInsert(
                    ['stock_item_id' => $item->id, 'supplier_id' => $suppB->id],
                    [
                        'purchase_price' => $priceB,
                        'unit'           => $item->unit,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]
                );

                // Pour un article sur deux, ajouter un 3ème fournisseur (-5%)
                if ($item->id % 2 === 0 && $suppC->id !== $suppA->id && $suppC->id !== $suppB->id) {
                    $priceC = (int) round($basePrice * 0.95);
                    DB::table('stock_item_supplier')->updateOrInsert(
                        ['stock_item_id' => $item->id, 'supplier_id' => $suppC->id],
                        [
                            'purchase_price' => $priceC,
                            'unit'           => $item->unit,
                            'created_at'     => $now,
                            'updated_at'     => $now,
                        ]
                    );
                }
            }
        }

        // 3.c) Renseigner le supplier_id des demandes d'achat existantes
        $requests = DB::table('purchase_requests')->whereNull('supplier_id')->get();
        $premierFournisseur = DB::table('suppliers')->where('is_active', true)->value('id');

        foreach ($requests as $req) {
            $premierArticleFournisseur = DB::table('purchase_request_lines')
                ->join('stock_items', 'purchase_request_lines.stock_item_id', '=', 'stock_items.id')
                ->where('purchase_request_lines.purchase_request_id', $req->id)
                ->whereNotNull('stock_items.supplier_id')
                ->value('stock_items.supplier_id');

            $supId = $premierArticleFournisseur ?: $premierFournisseur;
            if ($supId) {
                DB::table('purchase_requests')->where('id', $req->id)->update(['supplier_id' => $supId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'supplier_id')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('supplier_id');
            });
        }

        Schema::dropIfExists('stock_item_supplier');
    }
};

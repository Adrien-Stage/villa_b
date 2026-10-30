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

        // 3. Reprise des données existantes — rien d'inventé : un tarif est
        //    une donnée d'achat réelle.
        // 3.a) Le fournisseur habituel de chaque article reçoit sa ligne de
        //      tarif, au dernier prix d'achat connu (0 : à renseigner).
        $items = DB::table('stock_items')->whereNotNull('supplier_id')->get();
        $now = now();

        foreach ($items as $item) {
            $price = $item->last_purchase_price > 0 ? $item->last_purchase_price : 0;
            DB::table('stock_item_supplier')->updateOrInsert(
                [
                    'stock_item_id' => $item->id,
                    'supplier_id'   => $item->supplier_id,
                ],
                [
                    'purchase_price' => $price,
                    'unit'           => $item->unit,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]
            );
        }

        // 3.b) Une demande d'achat existante prend le fournisseur de ses
        //      articles seulement s'ils l'ont tous en commun ; sinon le
        //      responsable des achats le choisira.
        $requests = DB::table('purchase_requests')->whereNull('supplier_id')->pluck('id');

        foreach ($requests as $requestId) {
            $fournisseurs = DB::table('purchase_request_lines')
                ->join('stock_items', 'purchase_request_lines.stock_item_id', '=', 'stock_items.id')
                ->where('purchase_request_lines.purchase_request_id', $requestId)
                ->pluck('stock_items.supplier_id')
                ->unique();

            if ($fournisseurs->count() === 1 && $fournisseurs->first() !== null) {
                DB::table('purchase_requests')->where('id', $requestId)->update(['supplier_id' => $fournisseurs->first()]);
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

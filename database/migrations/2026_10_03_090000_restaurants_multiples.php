<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurants multiples.
 *
 * L'hôtel crée autant de restaurants qu'il en exploite. Chacun a sa carte, sa
 * cuisine et son bar, son garde-manger, ses inventaires, ses serveurs et sa
 * caisse ; chacun choisit ses modes de service — à la carte, au buffet, ou
 * les deux — et peut accueillir des banquets.
 *
 * Tout ce que le restaurant enregistrait sans dire où est désormais rattaché à
 * un restaurant (point de vente de restauration). L'existant rejoint le
 * restaurant d'origine, et le personnel du restaurant y est affecté : rien ne
 * change pour un établissement qui n'en a qu'un.
 */
return new class extends Migration
{
    /** Tables du restaurant qui ne disaient pas à quel restaurant elles appartiennent. */
    private const TABLES = [
        'restaurant_menu_categories',
        'restaurant_menu_items',
        'restaurant_pantry_items',
        'restaurant_recipes',
        'restaurant_stock_counts',
        'restaurant_waste_logs',
        'restaurant_shifts',
        'restaurant_notes',
        // Une livraison de l'économat au restaurant désigne la cuisine qui la reçoit.
        'stock_requisitions',
    ];

    /** Noms uniques jusqu'ici dans tout l'établissement, désormais dans chaque restaurant. */
    private const NOMS_PAR_RESTAURANT = [
        'restaurant_menu_categories',
        'restaurant_menu_items',
        'restaurant_pantry_items',
    ];

    /** Rôles du personnel d'un restaurant. */
    private const ROLES_DU_RESTAURANT = ['restaurant_manager', 'restaurant_chief', 'restaurant_staff', 'restaurant_cook', 'cashier'];

    public function up(): void
    {
        Schema::table('points_of_sale', function (Blueprint $table) {
            // Modes de service d'un restaurant : « carte », « buffet », ou les deux.
            $table->json('service_modes')->nullable()->after('kind');
        });

        Schema::create('point_of_sale_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['point_of_sale_id', 'user_id']);
        });

        foreach (self::TABLES as $nom) {
            if (Schema::hasTable($nom) && ! Schema::hasColumn($nom, 'point_of_sale_id')) {
                Schema::table($nom, function (Blueprint $table) {
                    $table->foreignId('point_of_sale_id')->nullable()->constrained('points_of_sale')->nullOnDelete();
                });
            }
        }

        // Deux restaurants peuvent servir chacun leur « Coca-Cola » ou tenir
        // chacun leur « Riz » au garde-manger.
        foreach (self::NOMS_PAR_RESTAURANT as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->dropUnique(['name']);
                $table->unique(['point_of_sale_id', 'name']);
            });
        }

        // Cuisine ou bar : chaque ligne de commande sait où elle se prépare.
        Schema::table('restaurant_customer_order_items', function (Blueprint $table) {
            $table->string('station', 10)->default('cuisine');
            $table->timestamp('ready_at')->nullable();
        });

        Schema::create('restaurant_buffet_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale');
            $table->date('service_date');
            // breakfast | lunch | dinner
            $table->string('meal_service', 20);
            // Prix d'entrée, en centimes.
            $table->unsignedBigInteger('adult_price');
            $table->unsignedBigInteger('child_price')->default(0);
            // open | closed
            $table->string('status', 10)->default('open');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['point_of_sale_id', 'service_date']);
        });

        Schema::create('restaurant_buffet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_buffet_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale');
            $table->unsignedSmallInteger('adults')->default(0);
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedBigInteger('amount');
            $table->string('payment_method', 20);
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('folio_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_register_session_id')->nullable()->constrained('cash_register_sessions')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('restaurant_banquets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale');
            $table->string('reference', 30)->unique();
            $table->string('title');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name');
            $table->string('client_phone', 40)->nullable();
            $table->string('client_email')->nullable();
            $table->date('event_date');
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->foreignId('space_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('covers');
            // Montants en centimes.
            $table->unsignedBigInteger('price_per_cover');
            $table->unsignedBigInteger('extras_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('deposit_required')->default(0);
            $table->text('menu')->nullable();
            // devis | confirme | realise | solde | annule
            $table->string('status', 15)->default('devis');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['point_of_sale_id', 'event_date']);
        });

        Schema::create('restaurant_banquet_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_banquet_id')->constrained()->cascadeOnDelete();
            // acompte | solde
            $table->string('kind', 10);
            $table->unsignedBigInteger('amount');
            $table->string('payment_method', 20);
            $table->foreignId('cash_register_session_id')->nullable()->constrained('cash_register_sessions')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        $this->rattacherLExistant();
    }

    /**
     * L'existant rejoint le restaurant d'origine ; le personnel du restaurant
     * y est affecté. Un établissement qui n'a qu'un restaurant ne voit rien
     * changer.
     */
    private function rattacherLExistant(): void
    {
        $restaurant = DB::table('points_of_sale')->where('kind', 'restauration')
            ->orderBy('sort_order')->orderBy('id')->value('id');

        if ($restaurant === null) {
            $restaurant = DB::table('points_of_sale')->insertGetId([
                'code' => 'RES', 'slug' => 'restaurant', 'name' => 'Restaurant', 'kind' => 'restauration',
                'series_prefix' => 'RES-', 'is_active' => true, 'sort_order' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('points_of_sale')->where('kind', 'restauration')->whereNull('service_modes')
            ->update(['service_modes' => json_encode(['carte'])]);

        foreach ([...self::TABLES, 'restaurant_customer_orders'] as $nom) {
            if ($nom === 'stock_requisitions') {
                DB::table($nom)->where('department', 'restaurant')->whereNull('service_store_id')
                    ->whereNull('point_of_sale_id')->update(['point_of_sale_id' => $restaurant]);

                continue;
            }

            DB::table($nom)->whereNull('point_of_sale_id')->update(['point_of_sale_id' => $restaurant]);
        }

        // Une boisson se prépare au bar, le reste en cuisine.
        $boissons = DB::table('restaurant_menu_items')->where('type', 'drink')->pluck('id');
        if ($boissons->isNotEmpty()) {
            DB::table('restaurant_customer_order_items')->whereIn('menu_item_id', $boissons)->update(['station' => 'bar']);
        }

        // Le personnel du restaurant appartient au restaurant d'origine.
        $roles = DB::table('roles')->whereIn('slug', self::ROLES_DU_RESTAURANT)->pluck('id');
        $parAffectation = DB::table('role_user')->whereIn('role_id', $roles)->pluck('user_id');
        $parColonne = DB::table('users')->whereIn('role', self::ROLES_DU_RESTAURANT)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('role_user')->whereColumn('role_user.user_id', 'users.id'))
            ->pluck('id');

        foreach ($parAffectation->merge($parColonne)->unique() as $userId) {
            DB::table('point_of_sale_user')->insertOrIgnore([
                'point_of_sale_id' => $restaurant, 'user_id' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_banquet_payments');
        Schema::dropIfExists('restaurant_banquets');
        Schema::dropIfExists('restaurant_buffet_entries');
        Schema::dropIfExists('restaurant_buffet_services');

        Schema::table('restaurant_customer_order_items', function (Blueprint $table) {
            $table->dropColumn(['station', 'ready_at']);
        });

        foreach (self::NOMS_PAR_RESTAURANT as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->dropUnique(['point_of_sale_id', 'name']);
                $table->unique(['name']);
            });
        }

        foreach (self::TABLES as $nom) {
            if (Schema::hasTable($nom) && Schema::hasColumn($nom, 'point_of_sale_id')) {
                Schema::table($nom, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('point_of_sale_id');
                });
            }
        }

        Schema::dropIfExists('point_of_sale_user');

        Schema::table('points_of_sale', function (Blueprint $table) {
            $table->dropColumn('service_modes');
        });
    }
};

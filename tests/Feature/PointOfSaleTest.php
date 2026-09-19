<?php

/**
 * Points de vente, espaces, et journal des encaissements.
 *
 * Restaurant, Boutique et Réception étaient trois silos écrits en dur :
 * ajouter un second restaurant demandait du code. Un établissement réel en
 * aligne cinq sur son journal — HOTEL, KOTIBE, BALENG, MINI BAR, BANQUET.
 */

use App\Models\PointOfSale;
use App\Models\Space;
use App\Services\RevenueJournal;
use App\Support\PointOfSaleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('les trois silos historiques existent comme points de vente', function () {
    PointOfSaleCatalog::sync();

    expect(PointOfSale::pluck('slug')->sort()->values()->all())
        ->toBe(['boutique', 'hotel', 'restaurant']);
});

test('un second restaurant s\'ajoute sans code', function () {
    PointOfSaleCatalog::sync();

    PointOfSale::create([
        'code' => 'KOT', 'slug' => 'kotibe', 'name' => 'Kotibe',
        'kind' => PointOfSale::KIND_RESTAURATION, 'series_prefix' => 'KOT-', 'sort_order' => 4,
    ]);
    PointOfSale::create([
        'code' => 'BAL', 'slug' => 'baleng', 'name' => 'Baleng',
        'kind' => PointOfSale::KIND_RESTAURATION, 'series_prefix' => 'BAL-', 'sort_order' => 5,
    ]);

    expect(PointOfSale::ofKind(PointOfSale::KIND_RESTAURATION)->count())->toBe(3);
});

test('la série préfixe le numéro de pièce', function () {
    $kotibe = PointOfSale::create([
        'code' => 'KOT', 'slug' => 'kotibe', 'name' => 'Kotibe',
        'kind' => PointOfSale::KIND_RESTAURATION, 'series_prefix' => 'KOT-',
    ]);

    // « KOT-4853 » se rattache d'un coup d'œil sur un journal papier ;
    // « 4853 » ne se rattache à rien.
    expect($kotibe->numero(4853))->toBe('KOT-4853');
});

test("une synchronisation ne renomme pas un point de vente rebaptisé", function () {
    PointOfSaleCatalog::sync();
    PointOfSale::where('slug', 'restaurant')->update(['name' => 'Kotibe']);

    PointOfSaleCatalog::sync();

    // Le nom appartient au client.
    expect(PointOfSale::where('slug', 'restaurant')->value('name'))->toBe('Kotibe');
});

test("une synchronisation ne supprime pas un point de vente ajouté", function () {
    PointOfSaleCatalog::sync();
    PointOfSale::create(['code' => 'MB', 'slug' => 'mini-bar', 'name' => 'Mini bar', 'kind' => PointOfSale::KIND_MINI_BAR]);

    PointOfSaleCatalog::sync();

    expect(PointOfSale::where('slug', 'mini-bar')->exists())->toBeTrue();
});

test("le banquet facture sur sa série et occupe la salle d'un autre", function () {
    $kotibe  = PointOfSale::create(['code' => 'KOT', 'slug' => 'kotibe', 'name' => 'Kotibe', 'kind' => PointOfSale::KIND_RESTAURATION, 'series_prefix' => 'KOT-']);
    $banquet = PointOfSale::create(['code' => 'BQT', 'slug' => 'banquet', 'name' => 'Banquet', 'kind' => PointOfSale::KIND_BANQUET, 'series_prefix' => 'BQT']);

    $salleKotibe = Space::create(['name' => 'Salle Kotibe', 'slug' => 'salle-kotibe', 'capacity' => 80, 'point_of_sale_id' => $kotibe->id]);
    $sallePoly   = Space::create(['name' => 'Salle polyvalente', 'slug' => 'salle-polyvalente', 'capacity' => 200]);

    // L'espace change, le point de vente non : c'est tout l'intérêt de les
    // séparer.
    expect($salleKotibe->pointOfSale->slug)->toBe('kotibe')
        ->and(Space::polyvalents()->pluck('slug')->all())->toBe(['salle-polyvalente'])
        ->and($banquet->numero(167))->toBe('BQT167')
        ->and($sallePoly->point_of_sale_id)->toBeNull();
});

test('le journal ventile les encaissements par point de vente et par mode', function () {
    PointOfSaleCatalog::sync();
    $hotel      = PointOfSale::where('slug', 'hotel')->first();
    $restaurant = PointOfSale::where('slug', 'restaurant')->first();

    DB::table('restaurant_customer_orders')->insert([
        ['point_of_sale_id' => $restaurant->id, 'amount_paid' => 17_500, 'payment_method' => 'cash',
         'payment_status' => 'paid', 'paid_at' => now(), 'status' => 'served', 'total_amount' => 17_500,
         'created_at' => now(), 'updated_at' => now()],
        ['point_of_sale_id' => $restaurant->id, 'amount_paid' => 10_000, 'payment_method' => 'orange_money',
         'payment_status' => 'paid', 'paid_at' => now(), 'status' => 'served', 'total_amount' => 10_000,
         'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('payments')->insert([
        'point_of_sale_id' => $hotel->id, 'amount' => 73_200, 'method' => 'cash', 'reference' => 'PAY-1',
        'status' => 'completed', 'paid_at' => now(), 'currency' => 'XAF',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $journal = app(RevenueJournal::class)->forPeriod(now()->startOfDay(), now()->endOfDay());

    expect($journal['total'])->toBe(100_700)
        ->and($journal['par_point_de_vente']['Restaurant'])->toBe(27_500)
        ->and($journal['par_point_de_vente']['Hôtel'])->toBe(73_200)
        ->and($journal['par_mode']['cash'])->toBe(90_700)
        ->and($journal['par_mode']['orange_money'])->toBe(10_000);
});

test("une vente déjà rattachée à un règlement n'est pas comptée deux fois", function () {
    PointOfSaleCatalog::sync();
    $hotel = PointOfSale::where('slug', 'hotel')->first();

    $paiementId = DB::table('payments')->insertGetId([
        'point_of_sale_id' => $hotel->id, 'amount' => 50_000, 'method' => 'cash', 'reference' => 'PAY-2',
        'status' => 'completed', 'paid_at' => now(), 'currency' => 'XAF',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('reception_sales')->insert([
        'point_of_sale_id' => $hotel->id, 'sale_number' => 'RS-1', 'total_amount' => 50_000,
        'user_id' => \App\Models\User::factory()->create(['role' => 'reception'])->id,
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_id' => $paiementId,
        'paid_at' => now(), 'subtotal' => 50_000, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Le même argent vu deux fois gonflerait le journal de moitié.
    expect(app(RevenueJournal::class)->forPeriod(now()->startOfDay(), now()->endOfDay())['total'])
        ->toBe(50_000);
});

test("une recette sans point de vente apparaît plutôt que de disparaître", function () {
    DB::table('payments')->insert([
        'point_of_sale_id' => null, 'amount' => 9_000, 'method' => 'cash', 'reference' => 'PAY-3',
        'status' => 'completed', 'paid_at' => now(), 'currency' => 'XAF',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $journal = app(RevenueJournal::class)->forPeriod(now()->startOfDay(), now()->endOfDay());

    // La taire ferait un journal qui ne tombe pas juste.
    expect($journal['par_point_de_vente']['Non rattaché'])->toBe(9_000)
        ->and($journal['total'])->toBe(9_000);
});

test('une période sans mouvement rend un journal vide mais valide', function () {
    $journal = app(RevenueJournal::class)->forPeriod(
        Carbon::parse('2020-01-01')->startOfDay(),
        Carbon::parse('2020-01-01')->endOfDay()
    );

    expect($journal['lignes'])->toBe([])->and($journal['total'])->toBe(0);
});

<?php

use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\TouristTaxBracket;
use App\Services\TaxationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Active la TVA et la taxe de séjour sur l'établissement de test.
 * Les taux et le barème viennent de la migration de données.
 */
function taxationSetup(array $settings = []): TaxationService
{
    Tenant::create([
        'name'     => 'Hôtel de test',
        'slug'     => 'hotel-test',
        'currency' => 'XAF',
        'settings' => ['taxes' => array_merge([
            'vat_enabled'          => true,
            'tourist_tax_enabled'  => true,
            'classification'       => '3',
            'tourist_tax_basis'    => TaxationService::BASIS_PER_ROOM,
        ], $settings)],
    ]);

    return new TaxationService();
}

// ── Extraction « en dedans » ────────────────────────────────────────────────

test('la décomposition retombe toujours sur le total TTC', function () {
    $service = taxationSetup();

    // Balayage large : c'est l'invariant qui protège la balance comptable.
    foreach ([1, 7, 99, 100, 333, 1_000, 12_345, 999_999, 4_500_000, 123_456_789] as $ttc) {
        $b = $service->breakdown($ttc);

        expect($b->ht + $b->vat)->toBe($ttc, "TTC {$ttc} : la base et la taxe ne retombent pas sur le total");
        expect($b->vatOnly + $b->surtax)->toBe($b->vat, "TTC {$ttc} : TVA et CAC ne retombent pas sur la taxe");
        expect($b->ht)->toBeGreaterThan(0);
    }
});

test('le taux camerounais de 19,25 % est extrait du montant TTC', function () {
    $service = taxationSetup();

    // 11 925 F TTC => 10 000 F HT et 1 925 F de taxe, par construction du taux.
    $b = $service->breakdown(1_192_500);

    expect($b->ht)->toBe(1_000_000);
    expect($b->vat)->toBe(192_500);
    expect($b->rateBasisPoints)->toBe(1925);
    expect($b->ratePercentage())->toBe(19.25);
});

test('les centimes additionnels communaux sont ventilés dans la taxe', function () {
    $service = taxationSetup();

    $b = $service->breakdown(1_192_500);

    // Le CAC vaut 10 % de la TVA : 175 pdb sur 1 925.
    expect($b->surtax)->toBe(17_500);
    expect($b->vatOnly)->toBe(175_000);
    expect($b->vatOnly + $b->surtax)->toBe($b->vat);
});

test('un montant exonéré reste intégralement en base', function () {
    $service = taxationSetup();

    $b = $service->breakdown(500_000, TaxRate::CODE_EXEMPT);

    expect($b->ht)->toBe(500_000);
    expect($b->vat)->toBe(0);
    expect($b->isExempt())->toBeTrue();
});

test('TVA désactivée : aucun montant n’est décomposé', function () {
    $service = taxationSetup(['vat_enabled' => false]);

    $b = $service->breakdown(1_192_500);

    expect($b->ht)->toBe(1_192_500);
    expect($b->vat)->toBe(0);
});

test('un client exonéré bascule sur le taux zéro', function () {
    $service = taxationSetup();

    expect($service->rateForSale(exempt: true)->code)->toBe(TaxRate::CODE_EXEMPT);
    expect($service->rateForSale()->code)->toBe(TaxRate::CODE_STANDARD);
});

// ── Cohérence d'une facture multi-lignes ────────────────────────────────────

test('la somme des bases de lignes égale la base du total', function () {
    $service = taxationSetup();

    // Montants choisis pour provoquer une dérive d'arrondi ligne à ligne.
    $amounts = [3_333, 3_333, 3_333, 10_001, 7];

    $result = $service->breakdownLines($amounts);

    $sumHt  = array_sum(array_map(fn ($l) => $l->ht, $result['lines']));
    $sumVat = array_sum(array_map(fn ($l) => $l->vat, $result['lines']));

    expect($sumHt)->toBe($result['total']->ht, 'les bases de lignes ne totalisent pas la base du pied de facture');
    expect($sumVat)->toBe($result['total']->vat);
    expect($sumHt + $sumVat)->toBe(array_sum($amounts));
});

// ── Taxe de séjour ──────────────────────────────────────────────────────────

test('la taxe de séjour suit le classement de l’établissement', function () {
    $service = taxationSetup();

    // 3 étoiles => 2 000 F la nuitée, sur 3 nuits.
    expect($service->touristTax(nights: 3))->toBe(600_000);
    expect($service->touristTaxBracket()->classification)->toBe('3');
});

test('la taxe de séjour peut être assise sur les personnes', function () {
    $service = taxationSetup(['tourist_tax_basis' => TaxationService::BASIS_PER_PERSON]);

    // 2 000 F × 3 nuits × 2 personnes
    expect($service->touristTax(nights: 3, persons: 2))->toBe(1_200_000);
});

test('la taxe de séjour désactivée n’est pas appliquée', function () {
    expect(taxationSetup(['tourist_tax_enabled' => false])->touristTaxEnabled())->toBeFalse();
});

test('sans classement déclaré, aucune taxe de séjour n’est due', function () {
    $service = taxationSetup(['classification' => null]);

    expect($service->touristTaxBracket())->toBeNull();
    expect($service->touristTax(nights: 2))->toBe(0);
});

test('la taxe de séjour ne s’applique pas à un séjour sans nuitée', function () {
    expect(taxationSetup()->touristTax(nights: 0))->toBe(0);
});

// ── Données de référence ────────────────────────────────────────────────────

test('la migration de données a livré les taux et le barème', function () {
    expect(TaxRate::where('code', TaxRate::CODE_STANDARD)->exists())->toBeTrue();
    expect(TaxRate::where('code', TaxRate::CODE_EXEMPT)->exists())->toBeTrue();
    expect(TaxRate::default()->code)->toBe(TaxRate::CODE_STANDARD);
    expect(TouristTaxBracket::count())->toBe(6);
});

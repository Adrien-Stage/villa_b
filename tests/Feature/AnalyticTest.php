<?php

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\AnalyticPostingService;
use App\Services\AnalyticReportService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** Charge imputée à un centre d'analyse. */
function charge(int $montant, ?string $centre, string $compte = '601000', ?Carbon $date = null): JournalEntry
{
    return app(LedgerService::class)->post(
        Journal::PURCHASES,
        $date ?? Carbon::parse('2026-06-10'),
        'Achat',
        [
            ['account' => $compte, 'debit' => $montant, 'center' => $centre],
            ['account' => Account::SUPPLIERS, 'credit' => $montant],
        ]
    );
}

/** Produit imputé à un centre d'analyse. */
function produit(int $montant, string $centre, string $compte, ?Carbon $date = null): JournalEntry
{
    return app(LedgerService::class)->post(
        Journal::SALES,
        $date ?? Carbon::parse('2026-06-10'),
        'Vente',
        [
            ['account' => Account::CASH, 'debit' => $montant],
            ['account' => $compte, 'credit' => $montant, 'center' => $centre],
        ]
    );
}

function juin(): array
{
    return [Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')];
}

// ── Reflet de classe 9 ──────────────────────────────────────────────────────

test('le reflet ventile les charges par centre et se solde à zéro', function () {
    charge(300_000, JournalEntryLine::CENTER_RESTAURANT);
    charge(200_000, JournalEntryLine::CENTER_ACCOMMODATION);
    charge(100_000, null); // charge de structure

    [$du, $au] = juin();
    $reflet = app(AnalyticPostingService::class)->mirror($du, $au);

    expect($reflet)->not->toBeNull();
    expect($reflet->isBalanced())->toBeTrue();

    $parCompte = $reflet->lines->keyBy('account_code');

    expect($parCompte['922000']->debit)->toBe(300_000);
    expect($parCompte['921000']->debit)->toBe(200_000);
    expect($parCompte['911000']->debit)->toBe(100_000);
    expect($parCompte['901000']->credit)->toBe(600_000);
});

test('le reflet ne touche ni le bilan ni le résultat', function () {
    charge(500_000, JournalEntryLine::CENTER_RESTAURANT);

    $classe6Avant = soldeClasse(6);
    $classe4Avant = soldeClasse(4);

    [$du, $au] = juin();
    app(AnalyticPostingService::class)->mirror($du, $au);

    // C'est toute la raison d'être des comptes réfléchis : la charge d'origine
    // reste intacte à sa place.
    expect(soldeClasse(6))->toBe($classe6Avant);
    expect(soldeClasse(4))->toBe($classe4Avant);

    // Et la classe 9 se solde à zéro sur elle-même.
    expect(soldeClasse(9))->toBe(0);
});

test('le reflet est idempotent', function () {
    charge(500_000, JournalEntryLine::CENTER_RESTAURANT);

    [$du, $au] = juin();
    $service = app(AnalyticPostingService::class);

    expect($service->mirror($du, $au))->not->toBeNull();
    expect($service->mirror($du, $au))->toBeNull();

    expect(JournalEntry::where('schema', 'like', 'analytic_mirror%')->count())->toBe(1);
});

test('le recalcul contre-passe le reflet précédent', function () {
    charge(500_000, JournalEntryLine::CENTER_RESTAURANT);

    [$du, $au] = juin();
    $service = app(AnalyticPostingService::class);
    $service->mirror($du, $au);

    // Une charge arrive après coup : le reflet devient faux.
    charge(200_000, JournalEntryLine::CENTER_SHOP);

    $nouveau = $service->remirror($du, $au);

    expect($nouveau)->not->toBeNull();
    expect($nouveau->lines->firstWhere('account_code', '901000')->credit)->toBe(700_000);

    // La classe 9 reste à zéro : reflet + extourne + nouveau reflet.
    expect(soldeClasse(9))->toBe(0);
});

test('l’écart signale un reflet périmé', function () {
    charge(500_000, JournalEntryLine::CENTER_RESTAURANT);

    [$du, $au] = juin();
    $service = app(AnalyticPostingService::class);
    $service->mirror($du, $au);

    expect($service->status($du, $au)['drift'])->toBe(0);

    charge(150_000, JournalEntryLine::CENTER_SHOP);

    $etat = $service->status($du, $au);
    expect($etat['mirrored'])->toBeTrue();
    expect($etat['drift'])->toBe(150_000);
});

test('une période sans charge ne produit pas de reflet', function () {
    [$du, $au] = juin();

    expect(app(AnalyticPostingService::class)->mirror($du, $au))->toBeNull();
});

// ── Résultat par centre ─────────────────────────────────────────────────────

test('la marge se calcule par centre, produits contre charges', function () {
    produit(1_000_000, JournalEntryLine::CENTER_RESTAURANT, '706100');
    charge(400_000, JournalEntryLine::CENTER_RESTAURANT);

    produit(2_000_000, JournalEntryLine::CENTER_ACCOMMODATION, '706000');
    charge(500_000, JournalEntryLine::CENTER_ACCOMMODATION);

    [$du, $au] = juin();
    $resultat = app(AnalyticReportService::class)->resultByCenter($du, $au);

    $restaurant = $resultat['rows']->firstWhere('center', JournalEntryLine::CENTER_RESTAURANT);
    expect($restaurant['revenue'])->toBe(1_000_000);
    expect($restaurant['cost'])->toBe(400_000);
    expect($restaurant['margin'])->toBe(600_000);
    expect($restaurant['margin_pct'])->toBe(60.0);

    $hebergement = $resultat['rows']->firstWhere('center', JournalEntryLine::CENTER_ACCOMMODATION);
    expect($hebergement['margin'])->toBe(1_500_000);
});

test('les charges de structure restent hors des centres', function () {
    charge(300_000, null, '622000'); // loyer

    [$du, $au] = juin();
    $resultat = app(AnalyticReportService::class)->resultByCenter($du, $au);

    // Les répartir au prorata donnerait une marge d'apparence précise et de
    // fait arbitraire : elles doivent rester visibles à part.
    expect($resultat['unassigned']['cost'])->toBe(300_000);
    expect($resultat['rows']->sum('cost'))->toBe(0);
    expect($resultat['totals']['cost'])->toBe(300_000);
});

test('le taux de ventilation mesure la qualité de l’analytique', function () {
    charge(700_000, JournalEntryLine::CENTER_RESTAURANT);
    charge(300_000, null);

    [$du, $au] = juin();
    $ventilation = app(AnalyticReportService::class)->ventilationRate($du, $au);

    expect($ventilation['assigned'])->toBe(700_000);
    expect($ventilation['unassigned'])->toBe(300_000);
    expect($ventilation['rate'])->toBe(70.0);
});

test('le dernier jour de la période est bien compté', function () {
    // Régression : entry_date est une colonne DATE, mais SQLite y stocke
    // « 2026-06-30 00:00:00 ». Comparée en chaîne à la borne « 2026-06-30 »,
    // l'écriture du dernier jour tombait hors de la période — la balance de
    // juin ignorait silencieusement le 30 juin.
    charge(500_000, JournalEntryLine::CENTER_RESTAURANT, '601000', Carbon::parse('2026-06-30'));

    [$du, $au] = juin();

    expect(app(AnalyticReportService::class)->resultByCenter($du, $au)['totals']['cost'])->toBe(500_000);
    expect(app(\App\Services\LedgerReportService::class)->balance($du, $au))->not->toBeEmpty();
});

test('la période borne les montants', function () {
    charge(500_000, JournalEntryLine::CENTER_RESTAURANT, '601000', Carbon::parse('2026-06-10'));
    charge(900_000, JournalEntryLine::CENTER_RESTAURANT, '601000', Carbon::parse('2026-07-10'));

    [$du, $au] = juin();
    $resultat = app(AnalyticReportService::class)->resultByCenter($du, $au);

    expect($resultat['totals']['cost'])->toBe(500_000);
});

// ── RevPAR ──────────────────────────────────────────────────────────────────

test('le RevPAR rapporte le produit à toutes les chambres vendables', function () {
    chambres(4);
    produit(6_000_000, JournalEntryLine::CENTER_ACCOMMODATION, '706000');

    $revpar = app(AnalyticReportService::class)->revpar(
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-10'),
    );

    // 4 chambres × 10 nuits (bornes incluses) = 40 nuitées disponibles.
    expect($revpar['rooms'])->toBe(4);
    expect($revpar['nights'])->toBe(10);
    expect($revpar['available'])->toBe(40);
    expect($revpar['revpar'])->toBe(150_000);
});

test('sans chambre vendable, le RevPAR ne s’invente pas', function () {
    produit(1_000_000, JournalEntryLine::CENTER_ACCOMMODATION, '706000');

    $revpar = app(AnalyticReportService::class)->revpar(
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    );

    expect($revpar['revpar'])->toBeNull();
    expect($revpar['occupancy'])->toBeNull();
});

// ── Utilitaires ─────────────────────────────────────────────────────────────

/** Solde signé d'une classe de comptes, toutes écritures confondues. */
function soldeClasse(int $classe): int
{
    return (int) JournalEntryLine::query()
        ->join('accounts', 'accounts.code', '=', 'journal_entry_lines.account_code')
        ->where('accounts.account_class', $classe)
        ->sum(\Illuminate\Support\Facades\DB::raw('journal_entry_lines.debit - journal_entry_lines.credit'));
}

/** Crée n chambres vendables. */
function chambres(int $n): void
{
    $type = \App\Models\RoomType::create([
        'name'          => 'Standard',
        'code'          => 'STD',
        'base_price'    => 5_000_000,
        'base_capacity' => 2,
        'max_capacity'  => 2,
        'is_active'     => true,
    ]);

    for ($i = 1; $i <= $n; $i++) {
        \App\Models\Room::create([
            'number'       => (string) (100 + $i),
            'room_type_id' => $type->id,
            'status'       => \App\Enums\RoomStatus::AVAILABLE,
            'is_active'    => true,
        ]);
    }
}

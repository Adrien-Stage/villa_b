<?php

use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Role;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['comptabilite', 'accounting', 'ledger']);
    RoleCatalog::sync();
    FiscalYear::openYear(2026);
    $this->comptable = User::factory()->create(['is_active' => true]);
    $this->comptable->roles()->sync(Role::where('slug', 'accountant')->pluck('id'));
    $this->comptable = $this->comptable->fresh();
});

function depenseTest(string $quand = '2026-06-15'): Expense
{
    return Expense::create(['occurred_at' => $quand, 'category' => 'maintenance', 'label' => 'Climatisation', 'amount' => 6_000_000, 'payment_method' => 'cash']);
}

test('une dépense déjà comptabilisée ne se modifie ni ne se supprime', function () {
    $depense = depenseTest();
    app(LedgerPostingService::class)->postExpense($depense);

    $this->actingAs($this->comptable);

    $this->put(route('accounting.expenses.update', $depense), [
        'occurred_at' => '2026-06-15', 'category' => 'maintenance', 'label' => 'Autre libellé', 'amount' => 10, 'payment_method' => 'cash',
    ])->assertSessionHasErrors('expense');
    $this->delete(route('accounting.expenses.destroy', $depense))->assertSessionHasErrors('expense');

    expect($depense->fresh()->label)->toBe('Climatisation')
        ->and(Expense::count())->toBe(1);
});

test('une dépense pas encore comptabilisée se corrige et se supprime', function () {
    $depense = depenseTest();

    $this->actingAs($this->comptable);

    $this->put(route('accounting.expenses.update', $depense), [
        'occurred_at' => '2026-06-15', 'category' => 'maintenance', 'label' => 'Climatisation, salle', 'amount' => 70000, 'payment_method' => 'cash',
    ])->assertSessionHas('success');
    expect($depense->fresh()->label)->toBe('Climatisation, salle');

    $this->delete(route('accounting.expenses.destroy', $depense))->assertSessionHas('success');
    expect(Expense::count())->toBe(0);
});

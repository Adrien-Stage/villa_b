<?php

/**
 * Mode intervention de l'administrateur.
 *
 * L'administrateur — le service informatique — consulte tout et n'écrit que la
 * configuration et les comptes. Il n'écrit dans l'exploitation que pendant une
 * intervention déclarée : motif, durée, services. Le manager est prévenu,
 * chaque action est marquée au journal, la trace part à la console — plus
 * tard, marquée tardive, si elle est injoignable.
 */

use App\Models\AuditLog;
use App\Models\Intervention;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat', 'restaurant', 'comptabilite', 'accounting', 'hebergement']);
    config([
        'orchestration.erp_url' => 'http://console',
        'orchestration.secret' => 'jeton-console',
        'orchestration.tenant_slug' => 'zingana',
    ]);
});

function avecLeRole(string $role, array $attributs = []): User
{
    $user = User::factory()->create(['role' => $role, 'is_active' => true] + $attributs);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user;
}

function creerArticle(): void
{
    test()->post('/economat/articles', ['name' => 'Savon de Marseille', 'unit' => 'pièce']);
}

test("sans intervention, l'administrateur n'écrit pas dans l'exploitation", function () {
    $this->actingAs(avecLeRole('admin'));

    creerArticle();

    expect(StockItem::where('name', 'Savon de Marseille')->exists())->toBeFalse();
});

test("une intervention ouvre l'écriture dans ses services, la trace part à la console et le manager est prévenu", function () {
    Http::fake(['http://console/*' => Http::response(['ok' => true], 201)]);
    $admin = avecLeRole('admin', ['name' => 'Serge Informatique']);
    $manager = avecLeRole('manager');

    $this->actingAs($admin)->post(route('interventions.store'), [
        'motif' => "Correction d'un article mal saisi à l'économat",
        'duree' => 30,
        'perimetres' => ['economat'],
    ])->assertSessionHas('success');

    $intervention = Intervention::firstOrFail();
    expect($intervention->estEnCours())->toBeTrue()
        ->and($intervention->erp_a_transmettre)->toBeFalse()
        ->and($intervention->erp_tardive)->toBeFalse();

    creerArticle();
    expect(StockItem::where('name', 'Savon de Marseille')->exists())->toBeTrue();

    // Chaque action porte la référence de l'intervention.
    expect(AuditLog::where('event_type', 'activity')->where('payload->intervention_id', $intervention->id)->exists())->toBeTrue();

    expect($manager->notifications()->count())->toBe(1);

    Http::assertSent(fn ($r) => $r->url() === 'http://console/api/etablissements/zingana/interventions'
        && $r->hasHeader('Authorization', 'Bearer jeton-console')
        && $r['reference'] === $intervention->id
        && $r['administrateur'] === 'Serge Informatique'
        && $r['perimetres'] === ['Économat']);
});

test("l'intervention ne couvre que les services déclarés", function () {
    Http::fake();
    $admin = avecLeRole('admin');
    Intervention::create([
        'user_id' => $admin->id, 'motif' => 'Économat seulement', 'perimetres' => ['economat'],
        'debut' => now(), 'fin_prevue' => now()->addHour(),
    ]);

    $resolveur = app(\App\Services\PermissionResolver::class);

    expect($resolveur->allows($admin, 'economat.items.creer'))->toBeTrue()
        ->and($resolveur->allows($admin, 'restaurant.billing.paid'))->toBeFalse();
});

test("terminée, l'intervention referme l'écriture", function () {
    Http::fake();
    $admin = avecLeRole('admin');
    $intervention = Intervention::create([
        'user_id' => $admin->id, 'motif' => 'Correction ponctuelle', 'perimetres' => ['economat'],
        'debut' => now(), 'fin_prevue' => now()->addHour(), 'erp_a_transmettre' => false,
    ]);

    $this->actingAs($admin)->post(route('interventions.terminer', $intervention))->assertSessionHas('success');

    expect($intervention->fresh()->cloture)->toBe(Intervention::TERMINEE);
    creerArticle();
    expect(StockItem::where('name', 'Savon de Marseille')->exists())->toBeFalse();
    Http::assertSentCount(1);
});

test("une console injoignable n'empêche pas l'intervention ; la trace part plus tard, tardive", function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refusée'));
    $admin = avecLeRole('admin');

    $this->actingAs($admin)->post(route('interventions.store'), [
        'motif' => 'Urgence en caisse, console hors ligne',
        'duree' => 15,
        'perimetres' => ['comptabilite'],
    ])->assertSessionHas('success', fn ($m) => str_contains($m, 'tardive'));

    $intervention = Intervention::firstOrFail();
    expect($intervention->estEnCours())->toBeTrue()
        ->and($intervention->erp_a_transmettre)->toBeTrue()
        ->and($intervention->erp_tardive)->toBeTrue();

    // La console revient : le planificateur rattrape la trace.
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['*' => Http::response(['ok' => true], 201)]);

    $this->artisan('interventions:transmettre')->assertExitCode(0);

    expect($intervention->fresh()->erp_a_transmettre)->toBeFalse()
        ->and($intervention->fresh()->erp_tardive)->toBeTrue();
    Http::assertSent(fn ($r) => $r['tardive'] === true);
});

test("une intervention arrivée au bout de sa durée est close et sa fin transmise", function () {
    Http::fake(['*' => Http::response(['ok' => true], 201)]);
    $admin = avecLeRole('admin');
    $intervention = Intervention::create([
        'user_id' => $admin->id, 'motif' => 'Oubliée ouverte', 'perimetres' => ['economat'],
        'debut' => now()->subHours(2), 'fin_prevue' => now()->subHour(), 'erp_a_transmettre' => false,
    ]);

    $this->artisan('interventions:transmettre')->assertExitCode(0);

    expect($intervention->fresh()->cloture)->toBe(Intervention::EXPIREE)
        ->and($intervention->fresh()->fin_reelle->equalTo($intervention->fin_prevue))->toBeTrue();
    Http::assertSent(fn ($r) => $r['cloture'] === 'expiree');
});

test("le manager suit les interventions, mais n'en ouvre pas", function () {
    Http::fake();
    $manager = avecLeRole('manager');

    $this->actingAs($manager)->get(route('interventions.index'))->assertOk();
    $this->post(route('interventions.store'), ['motif' => 'Tentative du manager', 'duree' => 15, 'perimetres' => ['economat']]);

    expect(Intervention::count())->toBe(0);
});

test('une intervention se motive et désigne ses services', function () {
    Http::fake();

    $this->actingAs(avecLeRole('admin'))
        ->post(route('interventions.store'), ['motif' => 'court', 'duree' => 30, 'perimetres' => []])
        ->assertSessionHasErrors(['motif', 'perimetres']);

    expect(Intervention::count())->toBe(0);
});

test("le bandeau signale l'intervention à l'administrateur et au manager", function () {
    Http::fake();
    $admin = avecLeRole('admin', ['name' => 'Serge Informatique']);
    Intervention::create([
        'user_id' => $admin->id, 'motif' => 'Correction de stock', 'perimetres' => ['economat'],
        'debut' => now(), 'fin_prevue' => now()->addHour(),
    ]);

    $this->actingAs($admin)->get(route('dashboard'))->assertSee('Intervention de Serge Informatique en cours')->assertSee('Terminer');
    $this->actingAs(avecLeRole('manager'))->get(route('dashboard'))->assertSee('Intervention de Serge Informatique en cours');
});

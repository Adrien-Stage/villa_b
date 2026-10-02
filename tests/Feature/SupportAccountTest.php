<?php

/**
 * Le support de l'éditeur entre par un compte technique distinct, dont les
 * sessions sont visibles par l'hôtel. Il ne se connecte plus sous le compte de
 * l'administrateur, et il consulte sans écrire.
 */

use App\Models\Role;
use App\Models\StockItem;
use App\Models\SupportSession;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat', 'hebergement']);
    config(['assistance.secret' => 'secret-assistance', 'orchestration.tenant_slug' => 'zingana']);
});

function jetonAssistance(array $charge = []): string
{
    $encode = rtrim(strtr(base64_encode(json_encode($charge + [
        'slug' => 'zingana', 'session' => 'AS-42', 'admin' => 'Ada Tech', 'exp' => now()->addMinutes(30)->timestamp,
    ])), '+/', '-_'), '=');

    return $encode . '.' . hash_hmac('sha256', $encode, 'secret-assistance');
}

test("le support entre sous son propre compte, pas sous celui de l'administrateur", function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $admin->roles()->sync(Role::where('slug', 'admin')->pluck('id'));

    $this->get(route('assistance.enter', ['token' => jetonAssistance()]))->assertRedirect(route('dashboard'));

    $connecte = auth()->user();
    expect($connecte->id)->not->toBe($admin->id)
        ->and($connecte->isSupport())->toBeTrue()
        ->and($connecte->rolesDetenus())->toBe(['support']);

    $session = SupportSession::firstOrFail();
    expect($session->technicien)->toBe('Ada Tech')
        ->and($session->reference)->toBe('AS-42')
        ->and($session->fin)->toBeNull();
});

test('le support consulte, sans écrire', function () {
    $this->get(route('assistance.enter', ['token' => jetonAssistance()]));

    $this->get('/economat/articles')->assertOk();
    $this->post('/economat/articles', ['name' => 'Savon de Marseille', 'unit' => 'pièce']);

    expect(StockItem::where('name', 'Savon de Marseille')->exists())->toBeFalse()
        ->and(app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'customers.export'))->toBeFalse();
});

test('le compte du support ne s\'ouvre pas par mot de passe', function () {
    $support = User::factory()->create(['role' => 'support', 'email' => 'support@wetchah.invalid', 'password' => bcrypt('devine-moi')]);
    $support->roles()->sync(Role::where('slug', 'support')->pluck('id'));

    $this->post('/login', ['email' => 'support@wetchah.invalid', 'password' => 'devine-moi'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test("la fin de la session du support est enregistrée", function () {
    $this->get(route('assistance.enter', ['token' => jetonAssistance()]));
    $this->post('/logout');

    expect(SupportSession::firstOrFail()->fin)->not->toBeNull();
});

test("l'hôtel voit les sessions du support", function () {
    SupportSession::create(['technicien' => 'Ada Tech', 'reference' => 'AS-7', 'debut' => now()->subHour(), 'fin' => now()]);
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
    $manager->roles()->sync(Role::where('slug', 'manager')->pluck('id'));

    $this->actingAs($manager)->get(route('support.sessions.index'))->assertOk()->assertSee('Ada Tech')->assertSee('AS-7');
});

test("un même compte sert à toutes les sessions du support", function () {
    $this->get(route('assistance.enter', ['token' => jetonAssistance()]));
    $this->post('/logout');
    $this->get(route('assistance.enter', ['token' => jetonAssistance(['session' => 'AS-43'])]));

    expect(User::query()->havingRole(['support'])->count())->toBe(1)
        ->and(SupportSession::count())->toBe(2);
});

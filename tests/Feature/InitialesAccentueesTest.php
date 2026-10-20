<?php

use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Les pastilles d'initiales coupaient le premier caractère en deux : « Léa »
 * donnait « L » suivi d'un demi-é, affiché « L� ».
 */

test("la liste du personnel affiche les initiales d'un prénom accentué sans casser l'UTF-8", function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();

    $manager = User::factory()->create(['name' => 'Marie Abena', 'is_active' => true]);
    $manager->roles()->sync(Role::where('slug', 'manager')->pluck('id'));
    $lea = User::factory()->create(['name' => 'Léa Ngono', 'is_active' => true]);
    $helene = User::factory()->create(['name' => 'Élodie Tamba', 'is_active' => true]);

    $html = $this->actingAs($manager)->get(route('users.index'))->assertOk()->getContent();

    expect(mb_check_encoding($html, 'UTF-8'))->toBeTrue()
        ->and($html)->toContain('>LÉ<')->toContain('>ÉL<')->not->toContain("L\xC3<");
});

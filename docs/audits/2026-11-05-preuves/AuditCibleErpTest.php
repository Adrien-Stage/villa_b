<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
uses(RefreshDatabase::class);
test('audit ERP login accepts eight failed attempts without rate limiting', function () {
    for ($i=0;$i<8;$i++) {
        $this->postJson('/login',['login'=>'audit@example.test','password'=>'invalid-test-value'])->assertStatus(422)->assertJsonValidationErrors('login');
    }
});
test('audit disabled tech account retains authenticated access', function () {
    $user=User::factory()->create(['role'=>User::ROLE_TECH_ADMIN,'is_active'=>false]);
    $this->actingAs($user)->get(route('tech.owners.index'))->assertOk();
});

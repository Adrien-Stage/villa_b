<?php

use App\Models\CashRegisterSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Villa Boutanga',
        'slug' => 'villa-boutanga',
        'currency' => 'XAF',
        'is_active' => true]);

    $this->receptionist = User::factory()->create([
        'email' => 'receptionist@example.com',
        'role' => 'reception',
        'is_active' => true]);
});

test('logout is blocked and redirects back with alert flag when receptionist has an open caisse', function () {
    $this->actingAs($this->receptionist);

    // Create open cash register session
    $session = CashRegisterSession::create([
        'user_id' => $this->receptionist->id,
        'module' => 'reception',
        'status' => 'open',
        'opening_amount' => 5000,
        'opened_at' => now()]);

    // Request logout
    $response = $this->from(route('dashboard'))->post(route('logout'));

    // Should redirect back to dashboard
    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('confirm_logout_caisse_open', true);
    $response->assertSessionHas('caisse_module', 'reception');
    $response->assertSessionHas('caisse_id', $session->id);

    // Receptionist should still be authenticated
    $this->assertAuthenticatedAs($this->receptionist);
});

test('logout with force flag pauses the caisse and successfully logs out receptionist', function () {
    $this->actingAs($this->receptionist);

    // Create open cash register session
    $session = CashRegisterSession::create([
        'user_id' => $this->receptionist->id,
        'module' => 'reception',
        'status' => 'open',
        'opening_amount' => 5000,
        'opened_at' => now()]);

    // Request logout with force
    $response = $this->post(route('logout'), ['force' => '1']);

    // Should log out successfully and redirect to welcome/login page
    $response->assertRedirect('/');
    $this->assertGuest();

    // The session should be paused in database
    $session->refresh();
    expect($session->status)->toBe('paused');
    expect($session->closed_at)->toBeNull();
});

test('logging in with a paused caisse flags the paused session in Laravel session', function () {
    // Create a paused session for the receptionist
    $session = CashRegisterSession::create([
        'user_id' => $this->receptionist->id,
        'module' => 'reception',
        'status' => 'paused',
        'opening_amount' => 5000,
        'opened_at' => now()]);

    // Submit login request
    $response = $this->post(route('login'), [
        'email' => 'receptionist@example.com',
        'password' => 'password']);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->receptionist);

    // Check Laravel session has the paused caisse flag
    $response->assertSessionHas('paused_caisse_session', [
        'id' => $session->id,
        'module' => 'reception']);
});

test('receptionist can resume the paused caisse and clear session flag', function () {
    $this->actingAs($this->receptionist);

    // Create a paused session
    $session = CashRegisterSession::create([
        'user_id' => $this->receptionist->id,
        'module' => 'reception',
        'status' => 'paused',
        'opening_amount' => 5000,
        'opened_at' => now()]);

    // Add flag to session
    session(['paused_caisse_session' => ['id' => $session->id, 'module' => 'reception']]);

    // Submit resume POST request
    $response = $this->from(route('dashboard'))->post(route('cash_register.resume'), [
        'session_id' => $session->id]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionMissing('paused_caisse_session');

    $session->refresh();
    expect($session->status)->toBe('open');
});

test('receptionist can resume the paused caisse and redirect to close page', function () {
    $this->actingAs($this->receptionist);

    // Create a paused session
    $session = CashRegisterSession::create([
        'user_id' => $this->receptionist->id,
        'module' => 'reception',
        'status' => 'paused',
        'opening_amount' => 5000,
        'opened_at' => now()]);

    // Add flag to session
    session(['paused_caisse_session' => ['id' => $session->id, 'module' => 'reception']]);

    // Submit resume with redirect_to_close
    $response = $this->post(route('cash_register.resume'), [
        'session_id' => $session->id,
        'redirect_to_close' => '1']);

    $response->assertRedirect(route('bookings.cash_register.close'));
    $response->assertSessionMissing('paused_caisse_session');

    $session->refresh();
    expect($session->status)->toBe('open');
});

<?php

use App\Models\Customer;
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

    $this->manager = User::factory()->create([
        'role' => 'manager',
        'is_active' => true]);

    $this->receptionist = User::factory()->create([
        'role' => 'reception',
        'is_active' => true]);

    $this->cashier = User::factory()->create([
        'role' => 'cashier',
        'is_active' => true]);

    $this->customer = Customer::create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'phone' => '123456789',
        'nationality' => 'French',
        'is_vip' => false,
        'is_blacklisted' => false]);
});

test('a receptionist can access the customer edit view', function () {
    $this->actingAs($this->receptionist);

    $response = $this->get(route('customers.edit', $this->customer));

    $response->assertStatus(200);
    $response->assertSee('Modifier les informations du client');
    $response->assertSee('John');
    $response->assertSee('Doe');
});

test('a manager can access the customer edit view', function () {
    $this->actingAs($this->manager);

    $response = $this->get(route('customers.edit', $this->customer));

    $response->assertStatus(200);
});

test('a cashier cannot access the customer edit view', function () {
    $this->actingAs($this->cashier);

    $response = $this->from(route('dashboard'))->get(route('customers.edit', $this->customer));

    $response->assertStatus(302);
    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('access_denied_popup', true);
});

test('receptionist can update customer details', function () {
    $this->actingAs($this->receptionist);

    $response = $this->put(route('customers.update', $this->customer), [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'phone' => '987654321',
        'email' => 'jane.smith@example.com',
        'nationality' => 'Ivorian',
        'date_of_birth' => '1990-05-15',
        'id_document_type' => 'Passeport',
        'id_document_number' => 'PASS9876',
        'address' => '123 Plateau',
        'city' => 'Abidjan',
        'is_vip' => '1',
        'is_blacklisted' => '1',
        'notes' => 'Some private internal notes here.']);

    $response->assertRedirect(route('customers.show', $this->customer));
    $response->assertSessionHas('success');

    $this->customer->refresh();
    expect($this->customer->first_name)->toBe('Jane');
    expect($this->customer->last_name)->toBe('Smith');
    expect($this->customer->phone)->toBe('987654321');
    expect($this->customer->email)->toBe('jane.smith@example.com');
    expect($this->customer->nationality)->toBe('Ivorian');
    expect($this->customer->date_of_birth->format('Y-m-d'))->toBe('1990-05-15');
    expect($this->customer->id_document_type)->toBe('Passeport');
    expect($this->customer->id_document_number)->toBe('PASS9876');
    expect($this->customer->address)->toBe('123 Plateau');
    expect($this->customer->city)->toBe('Abidjan');
    expect($this->customer->is_vip)->toBeTrue();
    expect($this->customer->is_blacklisted)->toBeTrue();
    expect($this->customer->notes)->toBe('Some private internal notes here.');
});

test('receptionist cannot update customer with invalid details', function () {
    $this->actingAs($this->receptionist);

    $response = $this->put(route('customers.update', $this->customer), [
        'first_name' => '', // required
        'last_name' => '',  // required
        'email' => 'invalid-email-format']);

    $response->assertSessionHasErrors(['first_name', 'last_name', 'email']);
    
    // Check that customer details did not change
    $this->customer->refresh();
    expect($this->customer->first_name)->toBe('John');
    expect($this->customer->last_name)->toBe('Doe');
});

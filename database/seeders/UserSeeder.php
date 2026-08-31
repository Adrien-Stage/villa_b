<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'wetchah-app')->first();

        if (!$tenant) {
            return;
        }

        $roles = Role::whereIn('slug', [
            'admin',
            'manager',
            'reception',
            'housekeeping_leader',
            'housekeeping_staff',
            'restaurant_chief',
            'restaurant_staff',
            'restaurant_cook',
            'cashier',
            'shop_manager',
            'shop_cashier',
        ])->get()->keyBy('slug');

        $admin = User::firstOrCreate(
            ['email' => 'admin@wetchah-app.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $roles->get('admin')?->users()->syncWithoutDetaching([$admin->id]);

        $manager = User::firstOrCreate(
            ['email' => 'manager@wetchah-app.test'],
            [
                'name' => 'Jean-Pierre Kamga',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'is_active' => true,
            ]
        );
        $roles->get('manager')?->users()->syncWithoutDetaching([$manager->id]);

        $reception = User::firstOrCreate(
            ['email' => 'reception@wetchah-app.test'],
            [
                'name' => 'Marie Tchoupo',
                'password' => Hash::make('password'),
                'role' => 'reception',
                'is_active' => true,
            ]
        );
        $roles->get('reception')?->users()->syncWithoutDetaching([$reception->id]);

        $housekeepingLeader = User::firstOrCreate(
            ['email' => 'housekeeping.leader@wetchah-app.test'],
            [
                'name' => 'Paul Nguemo',
                'password' => Hash::make('password'),
                'role' => 'housekeeping_leader',
                'is_active' => true,
            ]
        );
        $roles->get('housekeeping_leader')?->users()->syncWithoutDetaching([$housekeepingLeader->id]);

        $restaurantChief = User::firstOrCreate(
            ['email' => 'restaurant.chief@wetchah-app.test'],
            [
                'name' => 'Chef Restaurant',
                'password' => Hash::make('password'),
                'role' => 'restaurant_chief',
                'is_active' => true,
            ]
        );
        $roles->get('restaurant_chief')?->users()->syncWithoutDetaching([$restaurantChief->id]);

        $restaurantStaff = User::firstOrCreate(
            ['email' => 'restaurant.staff@wetchah-app.test'],
            [
                'name' => 'Serveur Restaurant',
                'password' => Hash::make('password'),
                'role' => 'restaurant_staff',
                'is_active' => true,
            ]
        );
        $roles->get('restaurant_staff')?->users()->syncWithoutDetaching([$restaurantStaff->id]);

        $restaurantCook = User::firstOrCreate(
            ['email' => 'restaurant.cook@wetchah-app.test'],
            [
                'name' => 'Cuisinier Restaurant',
                'password' => Hash::make('password'),
                'role' => 'restaurant_cook',
                'is_active' => true,
            ]
        );
        $roles->get('restaurant_cook')?->users()->syncWithoutDetaching([$restaurantCook->id]);

        $restaurantCashier = User::firstOrCreate(
            ['email' => 'restaurant.cashier@wetchah-app.test'],
            [
                'name' => 'Caissier Restaurant',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'is_active' => true,
            ]
        );
        $roles->get('cashier')?->users()->syncWithoutDetaching([$restaurantCashier->id]);

        $shopManager = User::firstOrCreate(
            ['email' => 'shop.manager@wetchah-app.test'],
            [
                'name' => 'Gérant Boutique',
                'password' => Hash::make('password'),
                'role' => 'shop_manager',
                'is_active' => true,
            ]
        );
        $roles->get('shop_manager')?->users()->syncWithoutDetaching([$shopManager->id]);

        $shopCashier = User::firstOrCreate(
            ['email' => 'shop.cashier@wetchah-app.test'],
            [
                'name' => 'Caissier Boutique',
                'password' => Hash::make('password'),
                'role' => 'shop_cashier',
                'is_active' => true,
            ]
        );
        $roles->get('shop_cashier')?->users()->syncWithoutDetaching([$shopCashier->id]);

        $staffMembers = [
            [
                'name' => 'Aline Ndzi',
                'email' => 'housekeeping.staff1@wetchah-app.test',
            ],
            [
                'name' => 'Brice Ndzié',
                'email' => 'housekeeping.staff2@wetchah-app.test',
            ],
            [
                'name' => 'Cynthia Fokou',
                'email' => 'housekeeping.staff3@wetchah-app.test',
            ],
        ];

        foreach ($staffMembers as $staffData) {
            $staff = User::firstOrCreate(
                ['email' => $staffData['email']],
                [
                    'name' => $staffData['name'],
                    'password' => Hash::make('password'),
                    'role' => 'housekeeping_staff',
                    'is_active' => true,
                ]
            );

            $roles->get('housekeeping_staff')?->users()->syncWithoutDetaching([$staff->id]);
        }
    }
}

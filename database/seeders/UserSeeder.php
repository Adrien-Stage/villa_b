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
        $tenant = Tenant::where('slug', 'villa-boutanga')->first();

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
            'cashier',
            'shop_manager',
            'shop_cashier',
        ])->get()->keyBy('slug');

        $admin = User::firstOrCreate(
            ['email' => 'admin@villaboutanga.cm'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $roles->get('admin')?->users()->syncWithoutDetaching([$admin->id]);

        $manager = User::firstOrCreate(
            ['email' => 'manager@villaboutanga.cm'],
            [
                'name' => 'Jean-Pierre Kamga',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'is_active' => true,
            ]
        );
        $roles->get('manager')?->users()->syncWithoutDetaching([$manager->id]);

        $reception = User::firstOrCreate(
            ['email' => 'reception@villaboutanga.cm'],
            [
                'name' => 'Marie Tchoupo',
                'password' => Hash::make('password'),
                'role' => 'reception',
                'is_active' => true,
            ]
        );
        $roles->get('reception')?->users()->syncWithoutDetaching([$reception->id]);

        $housekeepingLeader = User::firstOrCreate(
            ['email' => 'housekeeping.leader@villaboutanga.cm'],
            [
                'name' => 'Paul Nguemo',
                'password' => Hash::make('password'),
                'role' => 'housekeeping_leader',
                'is_active' => true,
            ]
        );
        $roles->get('housekeeping_leader')?->users()->syncWithoutDetaching([$housekeepingLeader->id]);

        $restaurantChief = User::firstOrCreate(
            ['email' => 'restaurant.chief@villaboutanga.cm'],
            [
                'name' => 'Chef Restaurant',
                'password' => Hash::make('password'),
                'role' => 'restaurant_chief',
                'is_active' => true,
            ]
        );
        $roles->get('restaurant_chief')?->users()->syncWithoutDetaching([$restaurantChief->id]);

        $restaurantStaff = User::firstOrCreate(
            ['email' => 'restaurant.staff@villaboutanga.cm'],
            [
                'name' => 'Serveur Restaurant',
                'password' => Hash::make('password'),
                'role' => 'restaurant_staff',
                'is_active' => true,
            ]
        );
        $roles->get('restaurant_staff')?->users()->syncWithoutDetaching([$restaurantStaff->id]);

        $restaurantCashier = User::firstOrCreate(
            ['email' => 'restaurant.cashier@villaboutanga.cm'],
            [
                'name' => 'Caissier Restaurant',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'is_active' => true,
            ]
        );
        $roles->get('cashier')?->users()->syncWithoutDetaching([$restaurantCashier->id]);

        $shopManager = User::firstOrCreate(
            ['email' => 'shop.manager@villaboutanga.cm'],
            [
                'name' => 'Gérant Boutique',
                'password' => Hash::make('password'),
                'role' => 'shop_manager',
                'is_active' => true,
            ]
        );
        $roles->get('shop_manager')?->users()->syncWithoutDetaching([$shopManager->id]);

        $shopCashier = User::firstOrCreate(
            ['email' => 'shop.cashier@villaboutanga.cm'],
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
                'email' => 'housekeeping.staff1@villaboutanga.cm',
            ],
            [
                'name' => 'Brice Ndzié',
                'email' => 'housekeeping.staff2@villaboutanga.cm',
            ],
            [
                'name' => 'Cynthia Fokou',
                'email' => 'housekeeping.staff3@villaboutanga.cm',
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

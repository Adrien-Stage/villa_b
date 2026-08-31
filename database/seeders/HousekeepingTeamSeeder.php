<?php

namespace Database\Seeders;

use App\Models\HousekeepingTeam;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class HousekeepingTeamSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'wetchah-app')->first();

        if (!$tenant) {
            return;
        }

        $leader = User::where('email', 'housekeeping.leader@wetchah-app.test')->first();
        $staffA = User::where('email', 'housekeeping.staff1@wetchah-app.test')->first();
        $staffB = User::where('email', 'housekeeping.staff2@wetchah-app.test')->first();
        $staffC = User::where('email', 'housekeeping.staff3@wetchah-app.test')->first();

        if (!$leader || !$staffA || !$staffB || !$staffC) {
            return;
        }

        $eastTeam = HousekeepingTeam::firstOrCreate(
            ['name' => 'Equipe Aile Est'],
            [
                'code' => 'HK-EAST',
                'leader_id' => $leader->id,
                'notes' => 'Etages 1 et 2, rotation du matin',
                'is_active' => true,
            ]
        );

        $eastTeam->members()->syncWithoutDetaching([
            $leader->id,
            $staffA->id,
            $staffB->id,
        ]);

        $villaTeam = HousekeepingTeam::firstOrCreate(
            ['name' => 'Equipe Villas'],
            [
                'code' => 'HK-VILLA',
                'leader_id' => $leader->id,
                'notes' => 'Suites, villas et demandes spéciales',
                'is_active' => true,
            ]
        );

        $villaTeam->members()->syncWithoutDetaching([
            $leader->id,
            $staffC->id,
        ]);
    }
}

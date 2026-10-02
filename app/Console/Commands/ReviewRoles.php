<?php

namespace App\Console\Commands;

use App\Support\RoleReview;
use Illuminate\Console\Command;

/**
 * Revue des comptes : ce que l'administrateur doit trancher après la reprise
 * des rôles. Lecture seule — rien n'est modifié.
 */
class ReviewRoles extends Command
{
    protected $signature = 'roles:revue';

    protected $description = 'Liste les comptes dont les rôles demandent une décision (lecture seule)';

    public function handle(): int
    {
        $constats = RoleReview::constats();

        if ($constats === []) {
            $this->info('Aucun constat : les rôles des comptes sont cohérents.');

            return self::SUCCESS;
        }

        foreach ($constats as $constat) {
            $this->newLine();
            $this->line("<options=bold>[{$constat['gravite']}] {$constat['constat']}</>");
            $this->line("  Décision : {$constat['decision']}");

            foreach ($constat['comptes'] as $compte) {
                $this->line("  - {$compte}");
            }
        }

        return self::SUCCESS;
    }
}

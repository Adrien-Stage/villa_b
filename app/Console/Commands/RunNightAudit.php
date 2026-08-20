<?php

namespace App\Console\Commands;

use App\Models\NightAudit;
use App\Services\NightAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Clôture journalière.
 *
 * Sans option, clôture la veille : on ne ferme jamais la journée en cours,
 * dont les opérations ne sont pas terminées.
 *
 *   php artisan night-audit:run
 *   php artisan night-audit:run --date=2026-06-15
 *   php artisan night-audit:run --catch-up      (rattrape les journées oubliées)
 */
class RunNightAudit extends Command
{
    protected $signature = 'night-audit:run
        {--date=      : Journée à clôturer (AAAA-MM-JJ). Par défaut : hier}
        {--catch-up   : Clôture aussi les journées antérieures restées ouvertes}
        {--notes=     : Commentaire porté au constat}';

    protected $description = 'Clôture la journée : comptabilise, constate le chiffre d’affaires, fige';

    public function handle(NightAuditService $audit): int
    {
        $jours = $this->journees($audit);

        if ($jours === []) {
            $this->info('Aucune journée à clôturer.');

            return self::SUCCESS;
        }

        $echecs = 0;

        foreach ($jours as $jour) {
            try {
                $resultat = $audit->run($jour, $this->option('notes'));
            } catch (\Throwable $e) {
                $this->warn("  {$jour->format('d/m/Y')} — {$e->getMessage()}");
                $echecs++;
                continue;
            }

            $this->afficher($resultat);
        }

        return $echecs > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, Carbon> */
    private function journees(NightAuditService $audit): array
    {
        if ($this->option('date')) {
            return [Carbon::parse($this->option('date'))->startOfDay()];
        }

        if ($this->option('catch-up')) {
            return $audit->pendingDays();
        }

        return [Carbon::yesterday()->startOfDay()];
    }

    private function afficher(NightAudit $a): void
    {
        $f = fn (int $c) => number_format($c / 100, 0, ',', ' ') . ' F';

        $this->info("Journée du {$a->audit_date->format('d/m/Y')} clôturée — {$a->entries_posted} écriture(s).");

        $this->table(['Poste', 'Montant'], [
            ['Hébergement',            $f($a->revenue_accommodation)],
            ['Restauration',           $f($a->revenue_restaurant)],
            ['Boutique',               $f($a->revenue_shop)],
            ['Chiffre d’affaires',     $f($a->revenue_total)],
            ['Trésorerie encaissée',   $f($a->cash_collected)],
        ]);

        // Un écart de caisse n'est pas une erreur technique : c'est un fait à
        // remonter, jamais à noyer dans le succès de la commande.
        if ($a->hasDiscrepancy()) {
            $this->warn("  Écart de caisse constaté : {$f($a->cash_discrepancy)} sur {$a->registers_closed} caisse(s).");
        }

        if ($a->hasOpenRegisters()) {
            $this->warn("  {$a->registers_left_open} caisse(s) ouverte(s) non fermée(s) à la clôture.");
        }
    }
}

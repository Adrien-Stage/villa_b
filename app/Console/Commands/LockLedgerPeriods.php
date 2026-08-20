<?php

namespace App\Console\Commands;

use App\Models\FiscalPeriod;
use App\Services\LedgerService;
use Illuminate\Console\Command;

/**
 * Verrouille les périodes dont le délai légal est dépassé (Article 22).
 *
 * L'Acte Uniforme impose de rendre les écritures irréversibles au plus tard
 * un mois après la fin de la période. Laisser ce geste à la seule vigilance
 * humaine revient à le découvrir au contrôle : la commande le systématise.
 *
 *   php artisan ledger:lock-periods            (signale seulement)
 *   php artisan ledger:lock-periods --force    (verrouille réellement)
 */
class LockLedgerPeriods extends Command
{
    protected $signature = 'ledger:lock-periods
        {--force : Verrouille réellement. Sans cette option, la commande se contente de signaler}';

    protected $description = 'Signale — ou verrouille — les périodes comptables dont le délai est dépassé';

    public function handle(LedgerService $ledger): int
    {
        $echues = FiscalPeriod::query()
            ->with('fiscalYear')
            ->whereNull('locked_at')
            ->get()
            ->filter(fn (FiscalPeriod $p) => $p->isOverdue())
            ->sortBy('starts_on');

        if ($echues->isEmpty()) {
            $this->info('Aucune période en retard de verrouillage.');

            return self::SUCCESS;
        }

        $this->warn("{$echues->count()} période(s) au-delà du délai d'un mois :");

        foreach ($echues as $periode) {
            $this->line("  {$periode->label()} — délai dépassé depuis le {$periode->lockDeadline()->format('d/m/Y')}");
        }

        // Le verrouillage est définitif : on ne l'applique jamais par défaut.
        // Une exécution planifiée alerte ; c'est un geste humain qui tranche.
        if (!$this->option('force')) {
            $this->newLine();
            $this->info('Aucune période verrouillée : relancez avec --force, ou verrouillez depuis l’interface.');

            return self::SUCCESS;
        }

        foreach ($echues as $periode) {
            $ledger->lockPeriod($periode);
            $this->info("  {$periode->label()} verrouillée.");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\LedgerPostingService;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Comptabilise les opérations d'une journée ou d'une plage de dates.
 *
 * Rejouable sans risque : chaque schéma est idempotent, une journée déjà
 * comptabilisée ne produit rien de plus. C'est ce qui permet de rattraper
 * l'historique — ou de relancer après correction — sans rien dédoubler.
 *
 *   php artisan ledger:post                       (hier)
 *   php artisan ledger:post --date=2026-06-15
 *   php artisan ledger:post --from=2026-01-01 --to=2026-06-30
 */
class PostLedgerEntries extends Command
{
    protected $signature = 'ledger:post
        {--date= : Journée à comptabiliser (AAAA-MM-JJ)}
        {--from= : Début de la plage}
        {--to=   : Fin de la plage}';

    protected $description = 'Génère les écritures comptables des opérations de la période';

    public function handle(LedgerPostingService $posting): int
    {
        [$from, $to] = $this->plage();

        if ($from->greaterThan($to)) {
            $this->error('La date de début est postérieure à la date de fin.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Comptabilisation du %s au %s…',
            $from->format('d/m/Y'),
            $to->format('d/m/Y')
        ));

        $totaux = [];
        $erreurs = 0;

        foreach (CarbonPeriod::create($from, $to) as $jour) {
            try {
                $resultat = $posting->postDay($jour);
            } catch (\Throwable $e) {
                // Une journée en échec ne doit pas interrompre les suivantes :
                // le plus souvent, c'est une période verrouillée.
                $this->warn("  {$jour->format('d/m/Y')} — ignorée : {$e->getMessage()}");
                $erreurs++;
                continue;
            }

            $produit = array_sum($resultat);

            if ($produit > 0) {
                $this->line("  {$jour->format('d/m/Y')} — {$produit} écriture(s)");

                foreach ($resultat as $schema => $nombre) {
                    $totaux[$schema] = ($totaux[$schema] ?? 0) + $nombre;
                }
            }
        }

        if ($totaux === []) {
            // Ne pas annoncer « tout est comptabilisé » quand des journées ont
            // échoué : le message masquerait le problème au lieu de le poser.
            if ($erreurs > 0) {
                $this->error("{$erreurs} journée(s) n'ont pas pu être comptabilisées — voir les avertissements ci-dessus.");

                return self::FAILURE;
            }

            $this->info('Aucune écriture à produire : tout était déjà comptabilisé.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Schéma', 'Écritures'],
            collect($totaux)->filter()->map(fn ($n, $s) => [$s, $n])->values()->all()
        );

        return $erreurs > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function plage(): array
    {
        if ($this->option('date')) {
            $jour = Carbon::parse($this->option('date'))->startOfDay();

            return [$jour, $jour->copy()];
        }

        if ($this->option('from') || $this->option('to')) {
            $from = Carbon::parse($this->option('from') ?? $this->option('to'))->startOfDay();
            $to   = Carbon::parse($this->option('to') ?? $this->option('from'))->startOfDay();

            return [$from, $to];
        }

        // Par défaut : la veille. C'est la cadence du night audit — on
        // comptabilise une journée close, jamais celle en cours.
        $hier = Carbon::yesterday()->startOfDay();

        return [$hier, $hier->copy()];
    }
}

<?php

namespace App\Console\Commands;

use App\Services\InterventionTrace;
use Illuminate\Console\Command;

/**
 * Clôt les interventions arrivées au bout de leur durée et transmet à la
 * console les traces qui attendent. Lancée par le planificateur.
 */
class TransmitInterventions extends Command
{
    protected $signature = 'interventions:transmettre';

    protected $description = "Clôt les interventions échues et transmet leurs traces à la console d'orchestration";

    public function handle(InterventionTrace $trace): int
    {
        $bilan = $trace->rattraper();

        $this->info("{$bilan['expirees']} intervention(s) close(s), {$bilan['transmises']} trace(s) transmise(s), {$bilan['en_attente']} en attente.");

        return self::SUCCESS;
    }
}

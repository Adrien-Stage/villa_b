<?php

namespace App\Console\Commands;

use App\Services\AlertesDeGestion;
use Illuminate\Console\Command;

/**
 * Chaque matin, les dérives de gestion du mois en cours : dépassement de
 * budget, recettes en retard, coût matière, marge en baisse. Chacune n'est
 * notifiée qu'une fois par mois et par centre (AlertesDeGestion).
 */
class ConstaterAlertesDeGestion extends Command
{
    protected $signature = 'pilotage:alertes';

    protected $description = 'Constate les dérives de gestion du mois et prévient le contrôle de gestion';

    public function handle(AlertesDeGestion $alertes): int
    {
        $nouvelles = $alertes->constater();

        foreach ($nouvelles as $alerte) {
            $this->line("[{$alerte->rule}] {$alerte->message}");
        }
        $this->info(count($nouvelles) . ' nouvelle(s) alerte(s) de gestion.');

        return self::SUCCESS;
    }
}

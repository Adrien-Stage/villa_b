<?php

namespace App\Console\Commands;

use App\Notifications\ShiftPlanningReminder;
use App\Services\Notifier;
use App\Services\PlanningService;
use Illuminate\Console\Command;

/**
 * Le dimanche : chaque chef de service dont la semaine suivante n'est pas
 * encore envoyée est invité à la programmer. Un service sans chef relève de
 * la direction.
 */
class RemindShiftPlanning extends Command
{
    protected $signature = 'planning:rappel';

    protected $description = 'Invite les chefs de service à programmer les quarts de la semaine suivante';

    public function handle(PlanningService $planning, Notifier $notifier): int
    {
        $lundi = PlanningService::lundi()->addWeek();

        foreach ($planning->semainesAProgrammer($lundi) as ['departement' => $departement, 'chefs' => $chefs]) {
            $notifier->send($chefs, new ShiftPlanningReminder($lundi, $departement->name, $departement->id));
            $this->info("Rappel : {$departement->name} ({$chefs->count()} destinataire(s)).");
        }

        return self::SUCCESS;
    }
}

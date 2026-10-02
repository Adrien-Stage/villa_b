<?php

namespace App\Console\Commands;

use App\Notifications\GeneralInventoryScheduled;
use App\Services\Notifier;
use App\Support\InventorySchedule;
use Illuminate\Console\Command;

/**
 * Rappel des inventaires généraux planifiés dans Paramètres > Inventaire.
 *
 * Lancée chaque matin : prévient le jour même et, si le calendrier le
 * demande, la veille. Elle n'ouvre aucun inventaire — l'ouverture gèle le
 * magasin et reste le geste de l'économe.
 */
class RemindGeneralInventory extends Command
{
    protected $signature = 'inventaire:rappel';

    protected $description = 'Prévient les services la veille et le jour d’un inventaire général';

    /** Ceux qui comptent : l'économat et les chefs des services qui détiennent du stock. */
    public const DESTINATAIRES = ['econome', 'housekeeping_leader', 'restaurant_chief', 'shop_manager', 'manager'];

    public function handle(Notifier $notifier): int
    {
        $calendrier = InventorySchedule::current();
        $aujourdhui = now()->startOfDay();
        $demain = $aujourdhui->copy()->addDay();

        if ($calendrier->isInventoryDay($aujourdhui)) {
            $notifier->toRoles(self::DESTINATAIRES, new GeneralInventoryScheduled($aujourdhui, today: true));
            $this->info("Rappel envoyé : inventaire général aujourd'hui.");
        }

        if ($calendrier->remindDayBefore && $calendrier->isInventoryDay($demain)) {
            $notifier->toRoles(self::DESTINATAIRES, new GeneralInventoryScheduled($demain, today: false));
            $this->info('Rappel envoyé : inventaire général demain.');
        }

        return self::SUCCESS;
    }
}

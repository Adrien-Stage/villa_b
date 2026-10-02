<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un inventaire général est prévu demain ou aujourd'hui, selon le calendrier
 * des Paramètres. Destinée à l'économe et aux chefs des services qui comptent.
 */
class GeneralInventoryScheduled extends Notification
{
    use Queueable;

    public function __construct(public CarbonInterface $date, public bool $today)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function title(): string
    {
        return $this->today ? "Inventaire général aujourd'hui" : 'Inventaire général demain';
    }

    private function detail(): string
    {
        $jour = $this->date->copy()->locale('fr')->isoFormat('dddd D MMMM');

        return $this->today
            ? "Inventaire général ce {$jour} : imprimez les fiches de comptage de votre service."
            : "Inventaire général prévu {$jour} : organisez le comptage de votre service.";
    }

    private function url(): string
    {
        return route('economat.count_sheets.index');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => $this->title(),
            'message' => $this->detail(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->detail(),
            'url'   => $this->url(),
            // Un tag par date : le rappel du jour remplace celui de la veille.
            'tag'   => 'inventaire-general-' . $this->date->toDateString(),
        ];
    }
}

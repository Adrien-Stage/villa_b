<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Le dimanche, le chef de service est invité à programmer la semaine qui
 * vient, tant qu'il ne l'a pas envoyée.
 */
class ShiftPlanningReminder extends Notification
{
    use Queueable;

    public function __construct(public CarbonImmutable $lundi, public string $service, public int $departementId)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function semaine(): string
    {
        return 'du ' . $this->lundi->locale('fr')->isoFormat('D') . ' au ' . $this->lundi->addDays(6)->locale('fr')->isoFormat('D MMMM');
    }

    private function title(): string
    {
        return 'Planning à programmer';
    }

    private function detail(): string
    {
        return "Programmez les quarts de la semaine {$this->semaine()} pour {$this->service}, puis envoyez le planning à votre personnel.";
    }

    private function url(): string
    {
        return route('planning.index', ['semaine' => $this->lundi->toDateString(), 'departement' => $this->departementId]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->detail(),
            'url' => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->detail(),
            'url' => $this->url(),
            'tag' => 'planning-rappel-' . $this->lundi->toDateString() . '-' . $this->departementId,
            'icon' => rtrim((string) config('app.url'), '/') . '/favicon.ico',
        ];
    }
}

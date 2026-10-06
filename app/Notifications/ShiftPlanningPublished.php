<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Le chef de service a envoyé le planning : la personne reçoit ses quarts
 * de la semaine — ou apprend qu'elle n'en a plus.
 */
class ShiftPlanningPublished extends Notification
{
    use Queueable;

    /** @param list<array{jour: string, quart: string, horaire: string}> $quarts */
    public function __construct(public CarbonImmutable $lundi, public string $service, public array $quarts)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function semaine(): string
    {
        return 'semaine du ' . $this->lundi->locale('fr')->isoFormat('D MMMM');
    }

    private function title(): string
    {
        return 'Vos quarts — ' . $this->semaine();
    }

    private function detail(): string
    {
        if ($this->quarts === []) {
            return "Vous n'avez plus de quart prévu la {$this->semaine()} ({$this->service}).";
        }

        return implode(' · ', array_map(
            fn (array $q) => ucfirst($q['jour']) . " : {$q['quart']} ({$q['horaire']})",
            $this->quarts
        ));
    }

    private function url(): string
    {
        return route('planning.index', ['semaine' => $this->lundi->toDateString()]);
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
            'tag' => 'planning-' . $this->lundi->toDateString(),
            'icon' => rtrim((string) config('app.url'), '/') . '/favicon.ico',
        ];
    }
}

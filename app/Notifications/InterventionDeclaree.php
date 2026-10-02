<?php

namespace App\Notifications;

use App\Models\Intervention;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * L'administrateur ouvre ou clôt une intervention dans l'exploitation. Le
 * manager en est prévenu : le service informatique n'écrit pas dans ses
 * services à son insu.
 */
class InterventionDeclaree extends Notification
{
    use Queueable;

    public function __construct(public Intervention $intervention, public bool $ouverture = true) {}

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return route('interventions.index');
    }

    private function message(): string
    {
        $qui = $this->intervention->user?->name ?? "L'administrateur";
        $ou = implode(', ', $this->intervention->libellesPerimetres());

        return $this->ouverture
            ? "{$qui} intervient jusqu'à {$this->intervention->fin_prevue->format('H:i')} ({$ou}) : {$this->intervention->motif}"
            : "{$qui} a terminé son intervention ({$ou}).";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'intervention_id' => $this->intervention->id,
            'title' => $this->ouverture ? "Intervention de l'administrateur" : 'Intervention terminée',
            'message' => $this->message(),
            'url' => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->ouverture ? "Intervention de l'administrateur" : 'Intervention terminée',
            'body' => $this->message(),
            'url' => $this->url(),
            'tag' => 'intervention-'.$this->intervention->id,
        ];
    }
}

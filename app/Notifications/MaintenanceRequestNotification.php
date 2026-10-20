<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Une étape d'une demande d'intervention, annoncée à qui doit agir :
 * signalée (au service technique), affectée (au technicien), terminée
 * (au demandeur), rouverte (au technicien et au responsable), annulée (au
 * technicien qui l'avait reçue et au responsable).
 */
class MaintenanceRequestNotification extends Notification
{
    use Queueable;

    public const CREATED   = 'created';
    public const ASSIGNED  = 'assigned';
    public const TAKEN     = 'taken';
    public const SUBMITTED = 'submitted';
    public const COMPLETED = 'completed';
    public const REOPENED  = 'reopened';
    public const CANCELLED = 'cancelled';

    public function __construct(
        public MaintenanceRequest $demande,
        public string $etape,
        public ?string $note = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return rtrim((string) config('app.url'), '/') . '/technique/demandes/' . $this->demande->id;
    }

    private function titre(): string
    {
        $urgent = $this->demande->priority === MaintenanceRequest::PRIORITY_URGENT ? ' urgente' : '';

        return match ($this->etape) {
            self::CREATED   => "Demande d'intervention{$urgent}",
            self::ASSIGNED  => 'Intervention à faire',
            self::TAKEN     => 'Intervention prise par un technicien',
            self::SUBMITTED => 'Fiche d\'intervention à viser',
            self::COMPLETED => 'Intervention terminée',
            self::REOPENED  => 'Intervention rouverte',
            self::CANCELLED => 'Demande annulée',
            default         => "Demande d'intervention",
        };
    }

    private function message(): string
    {
        $d = $this->demande;
        $texte = "{$d->reference} — {$d->lieu()} : {$d->title}";

        return match ($this->etape) {
            self::CREATED   => $texte . ' (' . $d->specialtyLabel() . ', ' . mb_strtolower($d->priorityLabel()) . ')',
            self::TAKEN     => $texte . ($this->note ? ' — ' . $this->note : ' — Prise en charge directe'),
            self::SUBMITTED => $texte . ($this->note ? ' — ' . mb_substr($this->note, 0, 120) : ' — Fiche soumise pour visa'),
            self::COMPLETED => $texte . ($this->note ? ' — ' . mb_substr($this->note, 0, 120) : ''),
            self::REOPENED  => $texte . ($this->note ? ' — motif : ' . mb_substr($this->note, 0, 120) : ''),
            self::CANCELLED => $texte . ($this->note ? ' — ' . mb_substr($this->note, 0, 120) : ''),
            default         => $texte,
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'maintenance_request_id' => $this->demande->id,
            'title'                  => $this->titre(),
            'message'                => $this->message(),
            'url'                    => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->titre() . ' — ' . $this->demande->lieu(),
            'body'  => $this->demande->title,
            'url'   => $this->url(),
            'tag'   => 'dit-' . $this->demande->id,
        ];
    }
}

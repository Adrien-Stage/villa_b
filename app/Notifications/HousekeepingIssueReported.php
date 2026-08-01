<?php

namespace App\Notifications;

use App\Models\Room;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un agent bloque sur une chambre (fuite, casse, équipement manquant).
 * Destinée au chef de service : la chambre est immobilisée tant que le
 * problème n'est pas arbitré.
 */
class HousekeepingIssueReported extends Notification
{
    use Queueable;

    public function __construct(
        public Room $room,
        public string $issue,
        public ?string $reportedBy = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return rtrim((string) config('app.url'), '/') . '/housekeeping';
    }

    private function shortIssue(): string
    {
        return mb_strlen($this->issue) > 120
            ? mb_substr($this->issue, 0, 120) . '…'
            : $this->issue;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'room_id' => $this->room->id,
            'title'   => 'Problème signalé en chambre',
            'message' => "Chambre {$this->room->number}"
                . ($this->reportedBy ? " (signalé par {$this->reportedBy})" : '')
                . ' : ' . $this->shortIssue(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => "Problème — chambre {$this->room->number}",
            'body'  => $this->shortIssue(),
            'url'   => $this->url(),
            'tag'   => 'hk-issue-' . $this->room->id,
        ];
    }
}

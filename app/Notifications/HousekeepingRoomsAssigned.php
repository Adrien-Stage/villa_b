<?php

namespace App\Notifications;

use App\Models\HousekeepingTeam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Le chef de service confie des chambres à une équipe. Destinée aux membres
 * de cette équipe : c'est leur feuille de route du moment.
 */
class HousekeepingRoomsAssigned extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $roomNumbers  Numéros des chambres confiées.
     */
    public function __construct(
        public HousekeepingTeam $team,
        public array $roomNumbers,
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

    private function summary(): string
    {
        $count = count($this->roomNumbers);
        $list  = implode(', ', array_slice($this->roomNumbers, 0, 5));

        if ($count > 5) {
            $list .= ' et ' . ($count - 5) . ' autre' . ($count - 5 > 1 ? 's' : '');
        }

        return "{$count} chambre" . ($count > 1 ? 's' : '') . " : {$list}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->team->id,
            'title'   => 'Nouvelles chambres à nettoyer',
            'message' => "Votre équipe {$this->team->name} doit traiter " . $this->summary() . '.',
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Nouvelles chambres à nettoyer',
            'body'  => $this->summary(),
            'url'   => $this->url(),
            // Un tag par équipe : une nouvelle affectation remplace l'ancienne
            // notification au lieu d'empiler les alertes.
            'tag'   => 'hk-assign-' . $this->team->id,
        ];
    }
}

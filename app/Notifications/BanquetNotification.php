<?php

namespace App\Notifications;

use App\Models\RestaurantBanquet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'équipe du restaurant concerné (responsable et chef de cuisine)
 * lorsqu'un devis de banquet est créé ou confirmé (notamment depuis la réception).
 */
class BanquetNotification extends Notification
{
    use Queueable;

    public const EVENT_CREATED = 'created';

    public const EVENT_CONFIRMED = 'confirmed';

    public function __construct(
        public RestaurantBanquet $banquet,
        public string $event = self::EVENT_CREATED,
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function context(): array
    {
        $this->banquet->loadMissing(['pointOfSale', 'space', 'createdBy']);

        $date = $this->banquet->event_date?->format('d/m/Y') ?? '—';
        $horaire = $this->banquet->start_time
            ? ' à ' . $this->banquet->start_time . ($this->banquet->end_time ? '–' . $this->banquet->end_time : '')
            : '';
        $resto = $this->banquet->pointOfSale?->name ?? 'Restaurant';
        $salle = $this->banquet->space ? ' · ' . $this->banquet->space->name : '';
        $source = $this->banquet->libelleSource();
        $actor = $this->actorName ?: ($this->banquet->createdBy?->name ?? 'Un collaborateur');

        if ($this->event === self::EVENT_CONFIRMED) {
            $title = 'Banquet confirmé';
            $message = sprintf(
                'Banquet %s (%s — %s) confirmé pour le %s%s (%d couverts, %s%s) par %s.',
                $this->banquet->reference,
                $this->banquet->title,
                $this->banquet->client_name,
                $date,
                $horaire,
                $this->banquet->covers,
                $resto,
                $salle,
                $actor,
            );
        } else {
            $title = 'Nouveau devis de banquet';
            $message = sprintf(
                'Devis %s — %s (%s) le %s%s pour %d couverts (%s%s · %s) créé par %s.',
                $this->banquet->reference,
                $this->banquet->title,
                $this->banquet->client_name,
                $date,
                $horaire,
                $this->banquet->covers,
                $resto,
                $salle,
                $source,
                $actor,
            );
        }

        return [
            'title' => $title,
            'message' => $message,
            'actor' => $actor,
            'url' => '/restaurant/banquets/' . $this->banquet->id,
        ];
    }

    public function toArray(object $notifiable): array
    {
        $ctx = $this->context();

        return [
            'banquet_id' => $this->banquet->id,
            'banquet_reference' => $this->banquet->reference,
            'point_of_sale_id' => $this->banquet->point_of_sale_id,
            'event' => $this->event,
            'title' => $ctx['title'],
            'message' => $ctx['message'],
            'url' => $ctx['url'],
            'actor_name' => $ctx['actor'],
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        $ctx = $this->context();

        return [
            'title' => $ctx['title'],
            'body' => $ctx['message'],
            'url' => $ctx['url'],
            'tag' => 'banquet-' . $this->banquet->id,
            'icon' => '/favicon.ico',
        ];
    }
}

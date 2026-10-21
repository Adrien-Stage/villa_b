<?php

namespace App\Notifications;

use App\Models\MinibarArticle;
use App\Models\Room;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pour la gouvernante et le chef d'équipe housekeeping : un valet / femme de chambre
 * a signalé un produit défectueux ou avarié dans le mini-bar d'une chambre.
 * Le produit est déduit du stock du mini-bar sans facturation au client,
 * et doit être remplacé depuis le dépôt.
 */
class MinibarDefectiveItemReported extends Notification
{
    use Queueable;

    public function __construct(
        public Room $room,
        public MinibarArticle $article,
        public int $quantity,
        public string $reason,
        public ?string $notes = null,
        public ?User $reporter = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return "{$base}/housekeeping/{$this->room->id}/mini-bar";
    }

    private function message(): string
    {
        $par = $this->reporter ? " par {$this->reporter->name}" : '';
        $details = $this->notes ? " (« {$this->notes} »)" : '';

        return "Chambre {$this->room->number} : {$this->quantity} × {$this->article->name} défectueux signalé{$par} (Motif : {$this->reason}{$details}). Retiré du mini-bar pour remplacement.";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'room_id'    => $this->room->id,
            'title'      => "Produit défectueux au mini-bar (Ch. {$this->room->number})",
            'message'    => $this->message(),
            'url'        => $this->url(),
            'quantity'   => $this->quantity,
            'article_id' => $this->article->id,
            'reason'     => $this->reason,
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => "Produit défectueux au mini-bar (Ch. {$this->room->number})",
            'body'  => $this->message(),
            'url'   => $this->url(),
            'tag'   => "minibar-defective-{$this->room->id}-{$this->article->id}-" . time(),
        ];
    }
}

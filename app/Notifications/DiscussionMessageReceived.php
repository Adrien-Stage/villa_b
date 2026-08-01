<?php

namespace App\Notifications;

use App\Models\DiscussionConversation;
use App\Models\DiscussionMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Nouveau message dans une conversation interne. Destinée aux autres
 * participants : la messagerie ne sert à rien si personne ne sait qu'un
 * message est arrivé pendant qu'il travaillait ailleurs dans l'application.
 */
class DiscussionMessageReceived extends Notification
{
    use Queueable;

    public function __construct(
        public DiscussionConversation $conversation,
        public DiscussionMessage $message,
        public string $authorName
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return route('discussions.index', ['conversation' => $this->conversation->id]);
    }

    /**
     * En groupe on annonce le fil, en tête-à-tête l'expéditeur suffit — c'est
     * l'information qui décide si on ouvre tout de suite ou plus tard.
     */
    private function headline(): string
    {
        $title = trim((string) $this->conversation->title);

        if ($this->conversation->is_group && $title !== '') {
            return "{$this->authorName} · {$title}";
        }

        return $this->authorName;
    }

    private function extract(): string
    {
        $body = trim((string) $this->message->body);

        return mb_strlen($body) > 120 ? mb_substr($body, 0, 117) . '…' : $body;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'message_id'      => $this->message->id,
            'title'           => 'Nouveau message — ' . $this->headline(),
            'message'         => $this->extract(),
            'url'             => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->headline(),
            'body'  => $this->extract(),
            'url'   => $this->url(),
            // Un tag par conversation : plusieurs messages d'affilée dans le
            // même fil ne produisent qu'une bulle, mise à jour au dernier reçu.
            'tag'   => 'discussion-' . $this->conversation->id,
        ];
    }
}

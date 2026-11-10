<?php

namespace App\Notifications;

use App\Models\CashRegisterActivity;
use App\Services\CashRegisterCircuit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Avertit le titulaire d'une caisse qu'un autre collaborateur (en pratique le
 * chef de réception) vient d'effectuer une opération dans son tiroir.
 */
class CashRegisterThirdPartyActivity extends Notification
{
    use Queueable;

    public function __construct(public CashRegisterActivity $activity)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function context(): array
    {
        $this->activity->loadMissing(['user.roles', 'session']);

        $actorName = $this->activity->user?->name ?? 'Un collaborateur';
        $roleLabel = app(CashRegisterCircuit::class)->libelleRole($this->activity->user);
        $actorLabel = $roleLabel ? "{$actorName} ({$roleLabel})" : $actorName;
        $heure = ($this->activity->occurred_at ?? now())->format('H:i');

        $montantText = '';
        if ($this->activity->isFinancialCashFlow() && $this->activity->amount) {
            $mode = CashRegisterActivity::methodLabel($this->activity->payment_method);
            $montant = number_format(abs((int) $this->activity->amount) / 100, 0, ',', ' ') . ' FCFA';
            $montantText = $mode ? " ({$montant} · {$mode})" : " ({$montant})";
        }

        return [
            'actor_label' => $actorLabel,
            'message' => "{$actorLabel} a travaillé dans votre caisse Réception (#{$this->activity->cash_register_session_id}) à {$heure} : {$this->activity->description}{$montantText}.",
            'url' => rtrim((string) config('app.url'), '/') . '/bookings/cash-register',
        ];
    }

    public function toArray(object $notifiable): array
    {
        $ctx = $this->context();

        return [
            'title' => 'Activité dans votre caisse Réception',
            'message' => $ctx['message'],
            'url' => $ctx['url'],
            'session_id' => $this->activity->cash_register_session_id,
            'activity_id' => $this->activity->id,
            'actor_id' => $this->activity->user_id,
            'actor_name' => $this->activity->user?->name,
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        $ctx = $this->context();

        return [
            'title' => 'Activité dans votre caisse',
            'body' => $ctx['message'],
            'url' => $ctx['url'],
            'tag' => 'caisse-tiers-' . $this->activity->cash_register_session_id,
            'icon' => rtrim((string) config('app.url'), '/') . '/favicon.ico',
        ];
    }
}

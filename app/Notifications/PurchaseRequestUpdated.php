<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Étape franchie par une demande d'achat, annoncée à qui doit agir ensuite :
 *  - soumise : au responsable des achats, qui la vérifie ;
 *  - modifiée : au demandeur, avec ce qui a changé ;
 *  - approuvée : au demandeur, avec les quantités ajustées ;
 *  - à commander : au responsable des achats, qui en tire les bons ;
 *  - refusée : au demandeur, avec le motif.
 */
class PurchaseRequestUpdated extends Notification
{
    use Queueable;

    public const SOUMISE = 'soumise';
    public const APPROUVEE = 'approuvee';
    public const A_COMMANDER = 'a_commander';
    public const REFUSEE = 'refusee';
    public const MODIFIEE = 'modifiee';

    /**
     * @param  ?string  $changements  pour MODIFIEE : ce qui a changé (« Riz 50 → 40 kg ; Huile retirée »)
     * @param  ?string  $par          pour MODIFIEE : qui l'a changé
     */
    public function __construct(
        public PurchaseRequest $request,
        public string $etape,
        public ?string $changements = null,
        public ?string $par = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return route('economat.purchase_requests.show', $this->request);
    }

    private function headline(): string
    {
        return match ($this->etape) {
            self::SOUMISE     => "Demande d'achat à vérifier",
            self::MODIFIEE    => "Votre demande d'achat a été modifiée",
            self::APPROUVEE   => $this->request->aEteAjustee() ? "Demande d'achat approuvée, quantités ajustées" : "Demande d'achat approuvée",
            self::A_COMMANDER => "Demande d'achat à commander",
            self::REFUSEE     => "Demande d'achat refusée",
            default           => "Demande d'achat mise à jour",
        };
    }

    /** Montants stockés en centimes FCFA. */
    private function montant(): string
    {
        return number_format((float) $this->request->total_estimated_amount / 100, 0, ',', ' ') . ' FCFA';
    }

    private function quantite(float $valeur, ?string $unite): string
    {
        return rtrim(rtrim(number_format($valeur, 3, ',', ' '), '0'), ',') . ($unite ? " {$unite}" : '');
    }

    /** « Riz 50 → 40 kg ; Huile 20 → 0 L » : ce que l'approbation a changé. */
    private function ajustements(): string
    {
        return $this->request->lines
            ->filter(fn (PurchaseRequestLine $l) => $l->estAjustee())
            ->map(fn (PurchaseRequestLine $l) => ($l->item?->name ?? 'Article') . ' '
                . $this->quantite((float) $l->quantity_requested, null) . ' → '
                . $this->quantite((float) $l->quantity_approved, $l->item?->unit))
            ->implode(' ; ');
    }

    private function detail(): string
    {
        $numero = $this->request->number;
        $par = $this->request->reviewedBy?->name;

        return match ($this->etape) {
            self::SOUMISE => "{$numero} ({$this->request->departmentLabel()}, priorité " . mb_strtolower($this->request->priorityLabel()) . ") : "
                . "{$this->montant()} estimés, demandée par " . ($this->request->requestedBy?->name ?? 'un service') . '.',
            self::APPROUVEE => $this->request->aEteAjustee()
                ? "{$numero} approuvée" . ($par ? " par {$par}" : '') . " avec des quantités ajustées : {$this->ajustements()}."
                : "{$numero} approuvée" . ($par ? " par {$par}" : '') . ' telle que demandée. Elle passe aux achats.',
            self::A_COMMANDER => "{$numero} approuvée" . ($par ? " par {$par}" : '') . " — {$this->montant()} estimés. "
                . 'Générez le(s) bon(s) de commande.',
            self::REFUSEE => "{$numero} refusée" . ($par ? " par {$par}" : '') . " : {$this->request->rejection_reason}",
            self::MODIFIEE => "{$numero} modifiée" . ($this->par ? " par {$this->par}" : '') . " : {$this->changements}.",
            default => "{$numero} mise à jour.",
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'purchase_request_id' => $this->request->id,
            'title'   => $this->headline(),
            'message' => $this->detail(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->headline(),
            'body'  => $this->detail(),
            'url'   => $this->url(),
            // Un tag par demande : chaque étape remplace la précédente.
            'tag'   => 'pr-' . $this->request->id,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\ExternalIssue;
use App\Models\ExternalIssueLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sorties de matériel hors de l'établissement : l'économe les valide en les
 * enregistrant, ce qui sort les articles du magasin au coût moyen ; il peut
 * les annuler, ce qui les y fait revenir au même coût.
 */
class ExternalIssueService
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * @param  array{reason: string, issued_at?: ?string, beneficiary_name: string, beneficiary_organisation?: ?string,
     *               beneficiary_phone?: ?string, beneficiary_id_document?: ?string, expected_return_at?: ?string,
     *               notes?: ?string, lines: list<array{stock_item_id: int, quantity: float, notes?: ?string}>}  $data
     */
    public function create(array $data, User $econome): ExternalIssue
    {
        return DB::transaction(function () use ($data, $econome) {
            $sortie = ExternalIssue::create([
                'reason'                   => $data['reason'],
                'issued_at'                => !empty($data['issued_at']) ? Carbon::parse($data['issued_at']) : now(),
                'beneficiary_name'         => trim($data['beneficiary_name']),
                'beneficiary_organisation' => $this->texte($data['beneficiary_organisation'] ?? null),
                'beneficiary_phone'        => $this->texte($data['beneficiary_phone'] ?? null),
                'beneficiary_id_document'  => $this->texte($data['beneficiary_id_document'] ?? null),
                'expected_return_at'       => in_array($data['reason'], ExternalIssue::REASONS_AVEC_RETOUR, true)
                    ? ($data['expected_return_at'] ?? null)
                    : null,
                'notes'                    => $this->texte($data['notes'] ?? null),
                'issued_by'                => $econome->id,
                'issuer_signature'         => $econome->signatureName(),
                'tenant_id'                => $econome->tenant_id ?? Tenant::current()?->id,
            ]);

            $total = 0;

            foreach ($data['lines'] as $ligne) {
                $item = StockItem::findOrFail($ligne['stock_item_id']);
                $quantite = round((float) $ligne['quantity'], 3);

                // Le moteur refuse une sortie sans stock et pendant un inventaire.
                $mouvement = $this->stock->recordOut(
                    $item,
                    $quantite,
                    StockMovement::SOURCE_EXTERNAL_ISSUE,
                    $sortie->id,
                    "Sortie hors établissement {$sortie->number} — {$sortie->reasonLabel()} — {$sortie->beneficiaire}"
                );

                $cout = (int) $mouvement->unit_cost;
                $valeur = (int) round($quantite * $cout);

                ExternalIssueLine::create([
                    'external_issue_id' => $sortie->id,
                    'stock_item_id'     => $item->id,
                    'quantity'          => $quantite,
                    'unit_cost'         => $cout,
                    'total_cost'        => $valeur,
                    'notes'             => $this->texte($ligne['notes'] ?? null),
                ]);

                $total += $valeur;
            }

            $sortie->update(['total_value' => $total]);

            return $sortie->fresh(['lines.item', 'issuedBy']);
        });
    }

    /** Annule une sortie : le matériel revient en stock au coût auquel il était sorti. */
    public function cancel(ExternalIssue $sortie, User $econome, string $motif): ExternalIssue
    {
        return DB::transaction(function () use ($sortie, $econome, $motif) {
            // Verrou : une double annulation remettrait deux fois en stock.
            $sortie = ExternalIssue::query()->lockForUpdate()->findOrFail($sortie->id);

            if ($sortie->isCancelled()) {
                throw new RuntimeException("Le bon {$sortie->number} est déjà annulé.");
            }

            foreach ($sortie->lines()->with('item')->get() as $ligne) {
                $this->stock->reverseOut(
                    $ligne->item,
                    (float) $ligne->quantity,
                    (int) $ligne->unit_cost,
                    StockMovement::SOURCE_EXTERNAL_ISSUE,
                    $sortie->id,
                    "Annulation de la sortie {$sortie->number} — {$motif}"
                );
            }

            $sortie->update([
                'status'              => ExternalIssue::STATUS_CANCELLED,
                'cancelled_by'        => $econome->id,
                'cancelled_at'        => now(),
                'cancellation_reason' => $motif,
            ]);

            return $sortie->fresh(['lines.item', 'issuedBy', 'cancelledBy']);
        });
    }

    private function texte(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : $valeur;
    }
}

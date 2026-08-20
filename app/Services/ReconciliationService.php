<?php

namespace App\Services;

use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Le lettrage : rapprocher un règlement de ce qu'il solde.
 *
 * Une lettre regroupe des lignes d'un même compte et d'un même tiers dont la
 * somme est nulle. Ce qui reste non lettré est, par définition, ce qui reste
 * dû — c'est ce qui donne son sens à la balance âgée.
 *
 * **Un groupe lettré doit s'équilibrer.** Lettrer des lignes qui ne se
 * compensent pas ferait disparaître une créance encore vivante du suivi des
 * impayés : le service refuse.
 *
 * Montants en centimes FCFA.
 */
class ReconciliationService
{
    /**
     * Lettre des lignes ensemble.
     *
     * @param  array<int, int>  $lineIds
     * @return string La lettre attribuée.
     */
    public function reconcile(array $lineIds): string
    {
        $lignes = JournalEntryLine::query()->whereIn('id', $lineIds)->get();

        if ($lignes->count() < 2) {
            throw new RuntimeException('Un lettrage rapproche au moins deux lignes.');
        }

        $this->assertMemeTiers($lignes);
        $this->assertNonLettrees($lignes);
        $this->assertEquilibre($lignes);

        $lettre = $this->nextCode($lignes->first()->account_code);

        DB::transaction(function () use ($lignes, $lettre) {
            foreach ($lignes as $ligne) {
                $ligne->update([
                    'reconciliation_code' => $lettre,
                    'reconciled_at'       => now(),
                ]);
            }
        });

        return $lettre;
    }

    /**
     * Délettre un groupe : la créance redevient vivante.
     *
     * Contrairement à une écriture, un lettrage n'est pas un fait comptable —
     * c'est un rapprochement. Le défaire ne modifie aucun solde, donc rien
     * n'interdit de le reprendre.
     */
    public function unreconcile(string $code): int
    {
        return JournalEntryLine::query()
            ->where('reconciliation_code', $code)
            ->update(['reconciliation_code' => null, 'reconciled_at' => null]);
    }

    /**
     * Lettrage automatique sur un compte, tiers par tiers.
     *
     * Deux passes, de la plus sûre à la plus large :
     *
     *  1. **Montants exacts** — un règlement qui solde exactement une facture.
     *     C'est le cas le plus fréquent et le moins discutable.
     *  2. **Solde nul** — si, une fois les paires évidentes traitées, ce qui
     *     reste se compense exactement, le tiers est à jour : on lettre le
     *     reste en bloc.
     *
     * Ce qui ne tombe dans aucun des deux cas reste ouvert, et c'est voulu :
     * un règlement partiel doit rester visible au suivi des impayés.
     *
     * @return int Nombre de lettres attribuées.
     */
    public function autoReconcile(string $accountCode, ?Model $auxiliary = null): int
    {
        $lettres = 0;

        foreach ($this->tiersOuverts($accountCode, $auxiliary) as [$type, $id]) {
            $lignes = $this->lignesOuvertes($accountCode, $type, $id);

            $lettres += $this->lettrerPairesExactes($lignes);

            // Nouvelle lecture : les paires viennent d'être lettrées.
            $reste = $this->lignesOuvertes($accountCode, $type, $id);

            if ($reste->count() >= 2 && $this->solde($reste) === 0) {
                $this->reconcile($reste->pluck('id')->all());
                $lettres++;
            }
        }

        return $lettres;
    }

    /** Prochaine lettre disponible sur un compte : A001, A002… */
    public function nextCode(string $accountCode): string
    {
        $dernier = JournalEntryLine::query()
            ->where('account_code', $accountCode)
            ->whereNotNull('reconciliation_code')
            ->orderByDesc('reconciliation_code')
            ->value('reconciliation_code');

        $numero = $dernier ? ((int) substr($dernier, 1)) + 1 : 1;

        return 'A' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    // ── Rouages internes ────────────────────────────────────────────────────

    /**
     * Apparie les lignes de sens opposé et de montant identique.
     *
     * @param  Collection<int, JournalEntryLine>  $lignes
     */
    private function lettrerPairesExactes(Collection $lignes): int
    {
        $debits  = $lignes->where('debit', '>', 0)->values();
        $credits = $lignes->where('credit', '>', 0)->values();

        $creditsUtilises = [];
        $lettres = 0;

        foreach ($debits as $debit) {
            $correspondance = $credits->first(
                fn ($c) => !in_array($c->id, $creditsUtilises, true) && $c->credit === $debit->debit
            );

            if ($correspondance === null) {
                continue;
            }

            $creditsUtilises[] = $correspondance->id;
            $this->reconcile([$debit->id, $correspondance->id]);
            $lettres++;
        }

        return $lettres;
    }

    /**
     * Tiers ayant des lignes non lettrées sur un compte.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private function tiersOuverts(string $accountCode, ?Model $auxiliary): array
    {
        $query = JournalEntryLine::query()
            ->where('account_code', $accountCode)
            ->whereNull('reconciliation_code')
            ->whereNotNull('auxiliary_type')
            ->whereNotNull('auxiliary_id');

        if ($auxiliary !== null) {
            $query->where('auxiliary_type', $auxiliary::class)
                ->where('auxiliary_id', $auxiliary->getKey());
        }

        return $query
            ->select('auxiliary_type', 'auxiliary_id')
            ->distinct()
            ->get()
            ->map(fn ($l) => [$l->auxiliary_type, (int) $l->auxiliary_id])
            ->all();
    }

    /** @return Collection<int, JournalEntryLine> */
    private function lignesOuvertes(string $accountCode, string $type, int $id): Collection
    {
        return JournalEntryLine::query()
            ->where('account_code', $accountCode)
            ->where('auxiliary_type', $type)
            ->where('auxiliary_id', $id)
            ->whereNull('reconciliation_code')
            ->whereHas('entry', fn ($q) => $q->whereNotNull('posted_at'))
            ->orderBy('id')
            ->get();
    }

    /** @param  Collection<int, JournalEntryLine>  $lignes */
    private function solde(Collection $lignes): int
    {
        return (int) $lignes->sum('debit') - (int) $lignes->sum('credit');
    }

    /** @param  Collection<int, JournalEntryLine>  $lignes */
    private function assertMemeTiers(Collection $lignes): void
    {
        if ($lignes->pluck('account_code')->unique()->count() > 1) {
            throw new RuntimeException('Un lettrage ne rapproche que des lignes d’un même compte.');
        }

        $tiers = $lignes->map(fn ($l) => $l->auxiliary_type . '#' . $l->auxiliary_id)->unique();

        if ($tiers->count() > 1) {
            throw new RuntimeException('Un lettrage ne rapproche que des lignes d’un même tiers.');
        }
    }

    /** @param  Collection<int, JournalEntryLine>  $lignes */
    private function assertNonLettrees(Collection $lignes): void
    {
        $dejaLettree = $lignes->first(fn ($l) => $l->reconciliation_code !== null);

        if ($dejaLettree !== null) {
            throw new RuntimeException(
                "La ligne #{$dejaLettree->id} est déjà lettrée ({$dejaLettree->reconciliation_code}). "
                . "Délettrez-la d'abord."
            );
        }
    }

    /** @param  Collection<int, JournalEntryLine>  $lignes */
    private function assertEquilibre(Collection $lignes): void
    {
        $ecart = $this->solde($lignes);

        if ($ecart !== 0) {
            throw new RuntimeException(sprintf(
                'Lettrage déséquilibré : écart de %s FCFA. Les lignes lettrées doivent se compenser exactement — '
                . 'sinon un solde encore dû disparaîtrait du suivi des impayés.',
                number_format(abs($ecart) / 100, 0, ',', ' ')
            ));
        }
    }
}

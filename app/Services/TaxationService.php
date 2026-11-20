<?php

namespace App\Services;

use App\Models\TaxRate;
use App\Models\SupplierInvoice;
use App\Models\Tenant;
use App\Models\TouristTaxBracket;
use App\Support\TaxBreakdown;

/**
 * Le moteur fiscal : décomposition des montants et taxe de séjour.
 *
 * Deux principes structurent ce service.
 *
 *  - Les prix affichés sont des TTC. La réglementation impose d'annoncer au
 *    client un prix toutes taxes comprises, donc la TVA se calcule « en
 *    dedans » : on l'extrait du montant saisi, on ne l'ajoute jamais par
 *    dessus. Aucun tarif ne change quand la TVA entre en service — seule sa
 *    décomposition apparaît sur les documents.
 *
 *  - Une somme décomposée retombe toujours sur son total. On arrondit la
 *    base hors taxes, puis on DÉDUIT la taxe par différence. Arrondir les
 *    deux séparément ferait dériver la balance d'un centime par facture, et
 *    le grand livre cesserait de s'équilibrer.
 *
 * Tous les montants sont en centimes FCFA, tous les taux en points de base.
 */
class TaxationService
{
    /** Base de calcul de la taxe de séjour : par chambre ou par personne. */
    public const BASIS_PER_ROOM = 'room';
    public const BASIS_PER_PERSON = 'person';

    /**
     * Les retenues sont définies par des règles datées dans les paramètres.
     */
    private ?array $settings = null;
    private ?TaxRate $defaultRate = null;

    /**
     * Décompose un montant TTC selon un taux.
     *
     * @param  int  $ttc  Montant toutes taxes comprises, en centimes.
     * @param  TaxRate|string|null  $rate  Taux, code de taux, ou null pour le défaut.
     */
    public function breakdown(int $ttc, TaxRate|string|null $rate = null): TaxBreakdown
    {
        $rate = $this->resolveRate($rate);

        if ($rate === null || $rate->rate_basis_points === 0) {
            return TaxBreakdown::exempt($ttc, $rate?->code ?? TaxRate::CODE_EXEMPT);
        }

        $bp = $rate->rate_basis_points;

        // Extraction « en dedans » : ht = ttc / (1 + taux), en arithmétique
        // entière. Le diviseur vaut 10 000 + bp, soit 11 925 pour 19,25 %.
        $divisor = 10_000 + $bp;
        $sign = $ttc < 0 ? -1 : 1;
        $ht = $sign * intdiv(abs($ttc) * 10_000 + intdiv($divisor, 2), $divisor);

        // La taxe est le RESTE, jamais un second arrondi : ht + vat === ttc.
        $vat = $ttc - $ht;

        // Ventilation TVA / centimes additionnels, selon la même règle : on
        // arrondit une part, on déduit l'autre.
        $surtax = 0;
        if ($rate->surtax_basis_points > 0) {
            $surtax = $sign * intdiv(abs($vat) * $rate->surtax_basis_points + intdiv($bp, 2), $bp);
        }

        return new TaxBreakdown(
            ttc: $ttc,
            ht: $ht,
            vat: $vat,
            vatOnly: $vat - $surtax,
            surtax: $surtax,
            rateBasisPoints: $bp,
            rateCode: $rate->code,
        );
    }

    /**
     * Décompose une liste de montants TTC en garantissant que la somme des
     * bases retombe sur la base du total.
     *
     * Décomposer ligne à ligne puis additionner peut s'écarter d'un centime
     * du total décomposé en une fois. Sur une facture, c'est visible : le
     * total des lignes ne correspond plus au pied de page. On corrige donc
     * l'écart sur la dernière ligne, celle qui porte le plus gros montant.
     *
     * @param  array<int, int>  $amounts  Montants TTC, en centimes.
     * @return array{lines: array<int, TaxBreakdown>, total: TaxBreakdown}
     */
    public function breakdownLines(array $amounts, TaxRate|string|null $rate = null): array
    {
        $rate = $this->resolveRate($rate);
        $total = $this->breakdown(array_sum($amounts), $rate);

        $lines = array_map(fn (int $amount) => $this->breakdown($amount, $rate), $amounts);

        $drift = $total->ht - array_sum(array_map(fn (TaxBreakdown $l) => $l->ht, $lines));

        $surtaxDrift = $total->surtax - array_sum(array_map(fn (TaxBreakdown $line) => $line->surtax, $lines));
        if (($drift !== 0 || $surtaxDrift !== 0) && $lines !== []) {
            // Report de l'écart sur la ligne au plus gros TTC : c'est celle
            // où un centime se remarque le moins.
            $index = array_search(max($amounts), $amounts, true);
            $line = $lines[$index];

            $lines[$index] = new TaxBreakdown(
                ttc: $line->ttc,
                ht: $line->ht + $drift,
                vat: $line->vat - $drift,
                vatOnly: $line->vatOnly - $drift - $surtaxDrift,
                surtax: $line->surtax + $surtaxDrift,
                rateBasisPoints: $line->rateBasisPoints,
                rateCode: $line->rateCode,
            );
        }

        return ['lines' => $lines, 'total' => $total];
    }

    /**
     * Taxe de séjour due pour un séjour.
     *
     * Perçue pour le compte de la fiscalité locale : c'est une dette, jamais
     * un produit. Elle ne passe donc pas par la décomposition TVA ci-dessus
     * et s'ajoute au montant réclamé au client.
     *
     * @param  int  $nights   Nombre de nuitées.
     * @param  int  $persons  Occupants — utilisé seulement si la base est « par personne ».
     */
    public function touristTax(int $nights, int $persons = 1): int
    {
        if ($nights <= 0) {
            return 0;
        }

        $bracket = $this->touristTaxBracket();

        if ($bracket === null || $bracket->amount_per_night <= 0) {
            return 0;
        }

        $units = $this->touristTaxBasis() === self::BASIS_PER_PERSON
            ? $nights * max(1, $persons)
            : $nights;

        return $bracket->amount_per_night * $units;
    }

    /** Ligne du barème correspondant au classement déclaré de l'établissement. */
    public function touristTaxBracket(): ?TouristTaxBracket
    {
        return TouristTaxBracket::forClassification($this->setting('classification'));
    }

    /** La taxe de séjour est-elle activée pour cet établissement ? */
    public function touristTaxEnabled(): bool
    {
        return (bool) $this->setting('tourist_tax_enabled', false)
            && $this->touristTaxBracket() !== null;
    }

    /**
     * Base de calcul : par chambre (défaut) ou par personne.
     *
     * Le choix reste ouvert tant que l'expert-comptable n'a pas confirmé
     * l'assiette applicable — d'où un paramètre plutôt qu'une règle figée.
     */
    public function touristTaxBasis(): string
    {
        return $this->setting('tourist_tax_basis') === self::BASIS_PER_PERSON
            ? self::BASIS_PER_PERSON
            : self::BASIS_PER_ROOM;
    }

    /** Montant opérationnel CGI 2026, par personne hébergée et par nuitée. */
    public function lodgingTax(int $nights, int $guests): int
    {
        if (!$this->touristTaxEnabled()) {
            return 0;
        }

        return max(0, $nights) * max(1, $guests) * $this->touristTaxBracket()->amount_per_night;
    }

    /** Le taux appliqué par défaut aux ventes. */
    public function defaultRate(): ?TaxRate
    {
        return $this->defaultRate ??= TaxRate::default();
    }

    /** La TVA est-elle activée pour cet établissement ? */
    public function vatEnabled(): bool
    {
        return (bool) $this->setting('vat_enabled', false)
            && $this->defaultRate() !== null
            && $this->defaultRate()->rate_basis_points > 0;
    }

    /**
     * Taux à appliquer à une vente, en tenant compte des positions fiscales.
     * Un client exonéré (export, organisme international) bascule sur le
     * taux zéro sans que l'opérateur ait à y penser.
     */
    public function rateForSale(bool $exempt = false): ?TaxRate
    {
        if ($exempt || !$this->vatEnabled()) {
            return TaxRate::query()->where('code', TaxRate::CODE_EXEMPT)->first();
        }

        return $this->defaultRate();
    }

    // ── Retenue à la source ─────────────────────────────────────────────────

    /**
     * Retenue à la source due sur une facture fournisseur.
     *
     * L'établissement ne paie pas cette part au fournisseur : il la prélève et
     * la reverse à l'État. Ce n'est donc ni une charge ni un produit, mais une
     * dette — d'où le compte 442100 au crédit et un net à payer diminué
     * d'autant.
     *
     * **L'assiette est le hors taxes.** Retenir sur le TTC reviendrait à
     * prélever sur une TVA qui appartient déjà à l'État et que le fournisseur
     * ne fait que collecter : la retenue serait comptée deux fois.
     *
     * @param  int  $ht  Base hors taxes, en centimes.
     * @return array{type: string|null, basis_points: int, amount: int}
     */
    public function withholding(int $ht, ?string $type, ?string $date = null): array
    {
        $bp = $this->withholdingRate($type, $date);

        if ($type === null || $bp <= 0 || $ht <= 0) {
            return ['type' => null, 'basis_points' => 0, 'amount' => 0];
        }

        // Arrondi au centime le plus proche, en arithmétique entière.
        $amount = intdiv($ht * $bp + 5_000, 10_000);

        return ['type' => $type, 'basis_points' => $bp, 'amount' => $amount];
    }

    /** Taux applicable à une nature de retenue, en points de base. */
    public function withholdingRate(?string $type, ?string $date = null): int
    {
        return (int) ($this->withholdingRule($type, $date)['basis_points'] ?? 0);
    }

    /**
     * Taux en vigueur, toutes natures confondues.
     *
     * @return array<string, int>
     */
    public function withholdingRates(): array
    {
        $taux = [];

        foreach (array_keys(SupplierInvoice::WITHHOLDING_TYPES) as $type) {
            $taux[$type] = $this->withholdingRate($type);
        }

        return $taux;
    }

    /**
     * Les taux ont-ils été confirmés par le cabinet ?
     *
     * Tant que non, les écrans le signalent : appliquer une retenue au mauvais
     * taux expose à un redressement, et le silence coûterait plus cher qu'un
     * bandeau.
     */
    public function withholdingRatesConfirmed(): bool
    {
        return max($this->withholdingRates()) > 0;
    }

    public function withholdingRule(?string $type, ?string $date = null): ?array
    {
        if (!isset(SupplierInvoice::WITHHOLDING_TYPES[$type])) {
            return null;
        }
        $rule = $this->setting('withholding_rules', [])[$type] ?? [];
        $date ??= today()->toDateString();
        $bp = (int) ($rule['basis_points'] ?? 0);
        if ($bp <= 0 || $bp > 10_000 || blank($rule['legal_reference'] ?? null)
            || blank($rule['starts_on'] ?? null) || $date < $rule['starts_on']
            || (filled($rule['ends_on'] ?? null) && $date > $rule['ends_on'])) {
            return null;
        }

        return $rule;
    }

    private function resolveRate(TaxRate|string|null $rate): ?TaxRate
    {
        if ($rate instanceof TaxRate) {
            return $rate;
        }

        if (is_string($rate)) {
            return TaxRate::query()->where('code', $rate)->first();
        }

        return $this->vatEnabled() ? $this->defaultRate() : null;
    }

    /** Bloc « taxes » des réglages de l'établissement. */
    private function setting(string $key, mixed $default = null): mixed
    {
        if ($this->settings === null) {
            $all = Tenant::query()->value('settings') ?? [];
            $all = is_string($all) ? (json_decode($all, true) ?: []) : (array) $all;
            $this->settings = (array) ($all['taxes'] ?? []);
        }

        return $this->settings[$key] ?? $default;
    }
}

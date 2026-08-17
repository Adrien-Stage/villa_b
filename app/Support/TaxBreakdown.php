<?php

namespace App\Support;

/**
 * Décomposition d'un montant TTC : ce que la facture doit faire apparaître.
 *
 * Objet immuable et clos par construction : `ht + vat === ttc` est vrai par
 * construction, jamais par recalcul. C'est ce qui garantit qu'aucune facture
 * ne peut sortir avec un total qui ne tombe pas juste.
 *
 * Tous les montants sont en centimes FCFA.
 */
readonly class TaxBreakdown
{
    public function __construct(
        /** Montant toutes taxes comprises — celui affiché au client. */
        public int $ttc,
        /** Base hors taxes. */
        public int $ht,
        /** Taxe totale (TVA + centimes additionnels le cas échéant). */
        public int $vat,
        /** Part de TVA proprement dite, hors centimes additionnels. */
        public int $vatOnly,
        /** Part de centimes additionnels communaux. */
        public int $surtax,
        /** Taux appliqué, en points de base (19,25 % => 1925). */
        public int $rateBasisPoints,
        /** Code du taux appliqué, pour la piste d'audit. */
        public string $rateCode,
    ) {
    }

    /** Décomposition d'un montant exonéré : tout en base, rien en taxe. */
    public static function exempt(int $ttc, string $rateCode = 'EXONERE'): self
    {
        return new self($ttc, $ttc, 0, 0, 0, 0, $rateCode);
    }

    public function isExempt(): bool
    {
        return $this->vat === 0;
    }

    /** Taux en pourcentage, pour l'affichage uniquement. */
    public function ratePercentage(): float
    {
        return $this->rateBasisPoints / 100;
    }
}

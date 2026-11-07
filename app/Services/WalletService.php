<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Moteur réutilisable : aucune dépendance aux partenaires, paiements ou factures. */
class WalletService
{
    public function forOwner(Model $owner, string $currency = 'XAF'): Wallet
    {
        if (! $owner->exists || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \DomainException('Titulaire enregistré et devise à trois lettres requis.');
        }

        return Wallet::firstOrCreate([
            'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'currency' => $currency,
        ]);
    }

    public function credit(Wallet $wallet, int $amount, string $key, array $metadata = [], ?int $actorId = null): WalletEntry
    {
        $this->positive($amount);

        return $this->post($wallet, $amount, 'credit', $key, $metadata, $actorId);
    }

    public function debit(Wallet $wallet, int $amount, string $key, array $metadata = [], ?int $actorId = null): WalletEntry
    {
        $this->positive($amount);

        return $this->post($wallet, -$amount, 'debit', $key, $metadata, $actorId);
    }

    public function reverse(WalletEntry $entry, string $key, array $metadata, ?int $actorId = null): WalletEntry
    {
        $entry = WalletEntry::findOrFail($entry->id);
        if ($entry->reverses_id !== null || trim((string) ($metadata['reason'] ?? '')) === '') {
            throw new \DomainException('Indiquez un motif pour annuler un mouvement initial.');
        }

        return $this->post($entry->wallet, -$entry->amount, 'reversal', $key, $metadata, $actorId, $entry->id);
    }

    private function positive(int $amount): void
    {
        if ($amount <= 0) {
            throw new \DomainException('Le montant doit être strictement positif.');
        }
    }

    private function post(Wallet $wallet, int $amount, string $kind, string $key, array $metadata, ?int $actorId, ?int $reverses = null): WalletEntry
    {
        if (! Str::isUuid($key)) {
            throw new \DomainException('Identifiant de requête invalide.');
        }
        $canonical = function (array $values) use (&$canonical): array {
            ksort($values);
            foreach ($values as &$value) {
                if (is_array($value)) {
                    $value = $canonical($value);
                }
            }

            return $values;
        };
        $fingerprint = hash('sha256', json_encode([$wallet->id, $amount, $kind, $actorId, $reverses, $canonical($metadata)], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($wallet, $amount, $kind, $key, $metadata, $actorId, $reverses, $fingerprint) {
                $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
                if ($previous = WalletEntry::where('request_key', $key)->first()) {
                    if (! hash_equals($previous->fingerprint, $fingerprint)) {
                        throw new \DomainException('Cette requête a déjà été utilisée pour une autre opération.');
                    }

                    return $previous;
                }
                if ($reverses && WalletEntry::where('reverses_id', $reverses)->exists()) {
                    throw new \DomainException('Ce mouvement a déjà été annulé.');
                }
                if ($amount < 0 && $locked->balance < -$amount) {
                    throw new \DomainException('Solde du portefeuille insuffisant.');
                }
                if ($amount > 0 && $locked->balance > PHP_INT_MAX - $amount) {
                    throw new \DomainException('Le solde dépasse la limite du portefeuille.');
                }
                $balance = $locked->balance + $amount;
                $locked->forceFill(['balance' => $balance])->save();

                return WalletEntry::create([
                    'wallet_id' => $locked->id, 'amount' => $amount, 'balance_after' => $balance,
                    'kind' => $kind, 'request_key' => $key, 'fingerprint' => $fingerprint,
                    'reverses_id' => $reverses, 'metadata' => $metadata, 'created_by' => $actorId,
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // Deux portefeuilles peuvent recevoir simultanément la même clé.
            // La transaction perdante est déjà annulée : rendre un conflit métier.
            $previous = WalletEntry::where('request_key', $key)->first();
            if ($previous && hash_equals($previous->fingerprint, $fingerprint)) {
                return $previous;
            }
            throw new \DomainException('Cette requête ou cette annulation a déjà été enregistrée.', 0, $e);
        }
    }
}

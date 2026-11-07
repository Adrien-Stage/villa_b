<?php

/** À exécuter uniquement sur une base PostgreSQL de test jetable déjà migrée. */
use App\Models\Customer;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'pgsql' || getenv('WALLET_CONCURRENCY_ALLOWED') !== '1') {
    throw new RuntimeException('Réservé à une base PostgreSQL de test explicitement autorisée.');
}
$service = app(WalletService::class);
if (($argv[1] ?? '') === 'worker') {
    while (microtime(true) < (float) $argv[5]) {
        usleep(1000);
    }
    try {
        $entry = DB::transaction(function () use ($service, $argv) {
            $wallet = Wallet::findOrFail((int) $argv[2]);
            $entry = $argv[3] === 'debit'
                ? $service->debit($wallet, 8000, $argv[4])
                : $service->credit($wallet, 10000, $argv[4]);
            usleep(300000); // Maintient le verrou pour chevaucher les deux opérations.

            return $entry;
        });
        echo json_encode(['ok' => true, 'entry' => $entry->id]);
    } catch (DomainException $e) {
        echo json_encode(['ok' => false, 'reason' => $e->getMessage()]);
    }
    exit;
}
$run = function (Wallet|array $wallets, string $action, array $keys): array {
    $workers = [];
    $start = (string) (microtime(true) + 0.8);
    foreach ($keys as $index => $key) {
        $wallet = is_array($wallets) ? $wallets[$index] : $wallets;
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', (string) $wallet->id, $action, $key, $start],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Création du processus de test impossible.');
        }
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $error !== '') {
            throw new RuntimeException($error ?: $output);
        }
        $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }

    return $results;
};
$wallet = $service->forOwner(Customer::factory()->create());
$service->credit($wallet, 10000, (string) Str::uuid());
$results = $run($wallet, 'debit', [(string) Str::uuid(), (string) Str::uuid()]);
if (count(array_filter($results, fn ($r) => $r['ok'])) !== 1 || $wallet->fresh()->balance !== 2000 || $wallet->entries()->count() !== 2) {
    throw new RuntimeException('Échec : deux débits concurrents ont dépassé le solde ou été perdus.');
}
$second = $service->forOwner(Customer::factory()->create());
$key = (string) Str::uuid();
$replays = $run($second, 'credit', [$key, $key]);
if (! $replays[0]['ok'] || ! $replays[1]['ok'] || $replays[0]['entry'] !== $replays[1]['entry'] || $second->fresh()->balance !== 10000 || $second->entries()->count() !== 1) {
    throw new RuntimeException('Échec : le même crédit simultané a été compté plusieurs fois.');
}
$third = $service->forOwner(Customer::factory()->create());
$fourth = $service->forOwner(Customer::factory()->create());
$sharedKey = (string) Str::uuid();
$conflicts = $run([$third, $fourth], 'credit', [$sharedKey, $sharedKey]);
if (count(array_filter($conflicts, fn ($r) => $r['ok'])) !== 1 || $third->fresh()->balance + $fourth->fresh()->balance !== 10000
    || $third->entries()->count() + $fourth->entries()->count() !== 1) {
    throw new RuntimeException('Échec : une clé partagée entre portefeuilles a produit deux opérations ou une erreur non contrôlée.');
}
echo "OK PostgreSQL : un seul débit de 8000 sur 10000 ; solde 2000. Crédit simultané identique compté une fois. Clé partagée entre portefeuilles refusée sans écriture partielle.\n";

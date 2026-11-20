@extends('layouts.hotel')

@section('title', 'Échanges comptables')

@section('content')
<h1 class="text-2xl font-semibold text-primary mb-4">Échanges comptables</h1>
@include('accounting.ledger.partials.nav')

@php
    $resolver = app(\App\Services\PermissionResolver::class);
    $canImport = $resolver->allows(auth()->user(), 'accounting.ledger.exchange.import');
    $canMatch = $resolver->allows(auth()->user(), 'accounting.ledger.exchange.match');
    $fcfa = fn ($amount) => number_format($amount / 100, 2, ',', ' ') . ' FCFA';
@endphp
@foreach (array_filter([session('success'), session('error'), $errors->first()]) as $message)
    <p class="bg-accent/20 rounded-lg p-3 mb-4 text-sm" role="status">{{ $message }}</p>
@endforeach

@if ($canImport)
    @include('accounting.ledger.partials.offline-draft', ['draftScope' => hash_hmac('sha256', 'ledger-draft:' . auth()->id(), config('app.key'))])
    <section class="bg-white border border-secondary/20 rounded-xl p-4 mb-5">
        <h2 class="font-semibold mb-2">Importer un fichier</h2>
        <form action="{{ route('accounting.ledger.exchange.import') }}" method="post" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3" data-ledger-import>
            @csrf
            <label class="text-sm">Type de fichier
                <select name="kind" class="block rounded-lg border-secondary/30">
                    <option value="bank">Relevé bancaire ou Mobile Money</option>
                    <option value="journal">Pièces d’opérations diverses</option>
                    <option value="payroll">Écritures d’une paie validée</option>
                </select>
            </label>
            <label class="text-sm">Référence du relevé ou du lot
                <input name="reference" value="{{ old('reference') }}" maxlength="60" required class="block rounded-lg border-secondary/30">
            </label>
            <label class="text-sm">Compte du relevé
                <select name="account_code" class="block rounded-lg border-secondary/30">
                    <option value="">Sélectionner pour un relevé</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->code }}">{{ $account->code }} · {{ $account->label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Fichier CSV ou XLSX
                <input type="file" name="file" accept=".csv,.txt,.xlsx" required class="block">
            </label>
            <button class="bg-primary text-white text-sm rounded-lg py-2 px-4">Importer</button>
        </form>
        <details class="mt-3 text-sm">
            <summary class="cursor-pointer">Colonnes et unités</summary>
            <p class="mt-2 break-all">Relevé · <code>{{ implode(';', \App\Services\BankReconciliation::HEADER) }}</code></p>
            <p class="mt-1 break-all">Journal ou paie · <code>{{ implode(';', \App\Services\LedgerImportService::HEADER) }}</code></p>
            <p class="mt-2">Utilisez des dates AAAA-MM-JJ, des centimes de FCFA et des points-virgules pour le CSV. Dans le relevé, un encaissement est positif.</p>
        </details>
    </section>
@endif

@if ($resolver->allows(auth()->user(), 'accounting.ledger.exchange.payment'))
    <details class="bg-white border border-secondary/20 rounded-xl p-4 mb-5">
        <summary class="font-semibold cursor-pointer">Justificatifs et frais sur encaissement</summary>
        @foreach (['exchange' => 'Justifier un change', 'payout' => 'Comptabiliser un versement opérateur',
            'stamp' => 'Comptabiliser un timbre perçu séparément'] as $operation => $label)
            <form method="post" action="{{ route('accounting.ledger.exchange.payment') }}" class="border-t border-secondary/15 pt-3 mt-3">
                @csrf
                <input name="operation" value="{{ $operation }}" type="hidden">
                <h3 class="font-semibold text-sm mb-2">{{ $label }}</h3>
                <div class="flex flex-wrap items-end gap-2 text-sm">
                    <label>Référence du paiement
                        <input name="payment_reference" maxlength="80" required class="block rounded-lg border-secondary/30">
                    </label>
                    <label>Référence du justificatif
                        <input name="reference" maxlength="60" required class="block rounded-lg border-secondary/30">
                    </label>
                    <label>{{ $operation === 'exchange' ? 'Date du taux' : 'Date comptable' }}
                        <input name="date" type="date" value="{{ today()->toDateString() }}" required class="block rounded-lg border-secondary/30">
                    </label>
                    @if ($operation === 'exchange')
                        <label>Devise
                            <input name="currency" minlength="3" maxlength="3" placeholder="EUR" required class="block rounded-lg border-secondary/30">
                        </label>
                        <label>Montant dans la plus petite unité
                            <input name="original_minor" type="number" min="1" required class="block rounded-lg border-secondary/30">
                        </label>
                        <label>Décimales de la devise
                            <input name="minor_digits" type="number" value="2" min="0" max="3" required class="block rounded-lg border-secondary/30">
                        </label>
                        <label>FCFA par unité de devise
                            <input name="xaf_rate" inputmode="decimal" placeholder="655.957" required class="block rounded-lg border-secondary/30">
                        </label>
                    @else
                        <label>{{ $operation === 'payout' ? 'Net versé en FCFA' : 'Timbre encaissé en FCFA' }}
                            <input name="amount" type="number" min="{{ $operation === 'stamp' ? 1 : 0 }}" required class="block rounded-lg border-secondary/30">
                        </label>
                        @if ($operation === 'stamp')
                            <label>Texte applicable à la pièce
                                <input name="legal_reference" maxlength="180" required class="block rounded-lg border-secondary/30">
                            </label>
                        @endif
                    @endif
                    <button class="bg-primary text-white rounded-lg px-3 py-2">Enregistrer</button>
                </div>
            </form>
        @endforeach
    </details>
@endif

<section class="bg-white border border-secondary/20 rounded-xl p-4 mb-5">
    <h2 class="font-semibold mb-3">Pointage bancaire</h2>
    @if ($statement)
        <form class="flex gap-3 items-end mb-4">
            <label class="text-sm">Relevé
                <select name="statement" class="block rounded-lg border-secondary/30">
                    @foreach ($statements as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $statement->id)>{{ $option->account_code }} · {{ $option->reference }}</option>
                    @endforeach
                </select>
            </label>
            <button class="text-sm rounded-lg border border-secondary/30 py-2 px-4">Afficher</button>
        </form>
        @if ($line && $canMatch)
            <form action="{{ route('accounting.ledger.exchange.match', $line) }}" method="post" class="bg-accent/15 rounded-lg p-4 mb-4">
                @csrf
                <p class="text-sm font-semibold">{{ $line->reference }} · {{ $fcfa($line->amount) }}</p>
                @if ($line->journal_entry_line_id)
                    <button class="text-sm underline mt-2">Retirer le pointage</button>
                @elseif ($candidates->isEmpty())
                    <p class="text-sm mt-2">Aucune écriture disponible pour ce compte et ce montant dans les sept jours autour de la date du relevé.</p>
                @else
                    <label class="text-sm block mt-2">Écriture à pointer
                        <select name="line_id" required class="block max-w-full rounded-lg border-secondary/30">
                            <option value="">Sélectionner une écriture</option>
                            @foreach ($candidates as $candidate)
                                <option value="{{ $candidate->id }}">
                                    {{ $candidate->entry->number() }} · {{ $candidate->entry->entry_date->format('d/m/Y') }}
                                    · {{ $candidate->entry->reference ?? $candidate->entry->label }}
                                    @if ($suggestion->count() === 1 && $suggestion->first()->id === $candidate->id) · référence concordante @endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button class="text-sm bg-primary text-white rounded-lg py-2 px-3 mt-2">Confirmer le pointage</button>
                @endif
            </form>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead><tr class="border-b"><th class="p-2">Date</th><th>Référence</th><th>Libellé</th><th>Montant</th><th>Pointage</th></tr></thead>
                <tbody>
                @foreach ($lines as $item)
                    <tr class="border-b border-secondary/15">
                        <td class="p-2">{{ $item->booked_on->format('d/m/Y') }}</td>
                        <td>{{ $item->reference }}</td><td>{{ $item->label }}</td><td>{{ $fcfa($item->amount) }}</td>
                        <td class="py-2">
                            {{ $item->ledgerLine?->entry->number() ?? 'À pointer' }}
                            @if ($canMatch)
                                <a class="underline ml-2" href="{{ route('accounting.ledger.exchange.index', ['statement' => $statement->id, 'line' => $item->id, 'page' => $lines->currentPage()]) }}">Examiner</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $lines->links() }}</div>
    @else
        <p class="text-sm">Importez un relevé pour commencer le pointage.</p>
    @endif
</section>
@if ($imports->isNotEmpty())
    <section class="bg-white border border-secondary/20 rounded-xl p-4">
        <h2 class="font-semibold mb-2">Derniers lots comptabilisés</h2>
        <ul class="text-sm">
            @foreach ($imports as $batch)
                <li>{{ $batch->created_at->format('d/m/Y H:i') }} · {{ $batch->reference }} · {{ $batch->kind === 'payroll' ? 'Paie' : 'Opérations diverses' }}</li>
            @endforeach
        </ul>
    </section>
@endif
@endsection

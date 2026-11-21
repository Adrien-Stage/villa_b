@extends('layouts.hotel')

@section('title', 'Registre des immobilisations')

@section('content')
<h1 class="text-2xl font-semibold text-primary mb-4">Registre des immobilisations</h1>
@include('accounting.ledger.partials.nav')
@php
    $canWrite = app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'accounting.ledger.assets.creer');
    $money = fn (int $value) => number_format($value / 100, 2, ',', ' ') . ' FCFA';
@endphp
@foreach (array_filter([session('success'), session('error'), $errors->first()]) as $message)
    <p class="bg-accent/20 rounded-lg p-3 mb-4 text-sm" role="status">{{ $message }}</p>
@endforeach

@if ($canWrite)
    <details class="bg-white border border-secondary/20 rounded-xl p-4 mb-4" @if(old('action') === 'register') open @endif>
        <summary class="font-semibold cursor-pointer">Inscrire une immobilisation</summary>
        <form method="post" action="{{ route('accounting.ledger.assets.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
            @csrf
            <input type="hidden" name="action" value="register">
            <label class="text-sm">Référence d’inventaire
                <input name="reference" maxlength="60" required value="{{ old('reference') }}" class="block w-full rounded-lg border-secondary/30">
            </label>
            <label class="text-sm">Désignation
                <input name="label" maxlength="180" required value="{{ old('label') }}" class="block w-full rounded-lg border-secondary/30">
            </label>
            <label class="text-sm">Identifiant de la ligne de débit d’acquisition
                <input name="journal_entry_line_id" type="number" min="1" required value="{{ old('journal_entry_line_id', request('acquisition_line')) }}" class="block w-full rounded-lg border-secondary/30">
            </label>
            <label class="text-sm">Mise en service
                <input name="in_service_on" type="date" required value="{{ old('in_service_on') }}" class="block w-full rounded-lg border-secondary/30">
            </label>
            <label class="text-sm">Durée d’utilisation en mois (0 pour un actif non amortissable)
                <input name="useful_months" type="number" min="0" max="1200" required value="{{ old('useful_months') }}" class="block w-full rounded-lg border-secondary/30">
            </label>
            <label class="text-sm">Valeur résiduelle en FCFA
                <input name="residual_amount" type="number" min="0" required value="{{ old('residual_amount', 0) }}" class="block w-full rounded-lg border-secondary/30">
            </label>
            @foreach (['depreciation_account' => ['Compte d’amortissement', '28'], 'charge_account' => ['Compte de dotation', '68']] as $field => [$label, $prefix])
                <label class="text-sm">{{ $label }}
                    <select name="{{ $field }}" class="block w-full rounded-lg border-secondary/30" required>
                        @foreach ($accounts->filter(fn ($item) => str_starts_with($item->code, $prefix)) as $account)
                            <option value="{{ $account->code }}" @selected(old($field) === $account->code)>{{ $account->code }} · {{ $account->label }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <div><button class="bg-primary text-white rounded-lg px-4 py-2 text-sm">Inscrire au registre</button></div>
        </form>
    </details>
@endif

@if ($selected && !$selected->disposed_on && $canWrite)
    <section class="bg-white border border-secondary/20 rounded-xl p-4 mb-4">
        <h2 class="font-semibold">{{ $selected->reference }} · {{ $selected->label }}</h2>
        <form method="post" action="{{ route('accounting.ledger.assets.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <input type="hidden" name="action" value="depreciate">
            <input type="hidden" name="asset_id" value="{{ $selected->id }}">
            <label class="text-sm">Date de dotation
                <input type="date" name="date" value="{{ today()->toDateString() }}" required class="block rounded-lg border-secondary/30">
            </label>
            <button class="bg-primary text-white rounded-lg px-4 py-2 text-sm">Comptabiliser la dotation</button>
        </form>
        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-semibold">Enregistrer une sortie</summary>
            <form method="post" action="{{ route('accounting.ledger.assets.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="action" value="dispose">
                <input type="hidden" name="asset_id" value="{{ $selected->id }}">
                <label class="text-sm">Date
                    <input type="date" name="date" value="{{ today()->toDateString() }}" required class="block rounded-lg border-secondary/30">
                </label>
                <label class="text-sm">Pièce de cession ou de retrait
                    <input name="reference" maxlength="60" required class="block rounded-lg border-secondary/30">
                </label>
                <label class="text-sm">Prix encaissé en banque (FCFA)
                    <input type="number" name="proceeds" value="0" min="0" required class="block rounded-lg border-secondary/30">
                </label>
                <label class="text-sm">Dont TVA (FCFA)
                    <input type="number" name="vat" value="0" min="0" required class="block rounded-lg border-secondary/30">
                </label>
                <label class="text-sm"><input type="checkbox" name="ordinary" value="1"> Cession courante</label>
                <button class="bg-primary text-white rounded-lg px-4 py-2 text-sm">Comptabiliser la sortie</button>
            </form>
        </details>
    </section>
@endif

<div class="overflow-x-auto bg-white border border-secondary/20 rounded-xl p-4">
    <table class="w-full text-sm text-left">
        <thead><tr class="border-b"><th>Référence</th><th>Désignation</th><th>En service</th><th>Coût</th><th>Dotations</th><th>Situation</th></tr></thead>
        <tbody>
        @forelse ($assets as $asset)
            <tr class="border-b border-secondary/10">
                <td class="py-2">{{ $asset->reference }}</td><td>{{ $asset->label }}</td><td>{{ $asset->in_service_on->format('d/m/Y') }}</td>
                <td>{{ $money($asset->acquisitionLine->debit) }}</td><td>{{ $money($amortization[$asset->id]) }}</td>
                <td>
                    @if ($asset->disposed_on)
                        <a class="underline" href="{{ route('accounting.ledger.entry', $asset->disposal_entry_id) }}">Sorti le {{ $asset->disposed_on->format('d/m/Y') }}</a>
                    @else
                        <a class="underline" href="{{ route('accounting.ledger.assets.index', ['asset' => $asset->id, 'page' => $assets->currentPage()]) }}">Consulter</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-3">Aucune immobilisation inscrite.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $assets->links() }}
</div>
@endsection

@extends('layouts.hotel')

@section('title', 'Plan de comptes')

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Plan de comptes</h1>
    <p class="text-sm text-primary/60 mt-1">SYSCOHADA révisé — sous-ensemble hôtelier</p>
</div>

@include('accounting.ledger.partials.nav')

<form method="GET" class="flex flex-col sm:flex-row gap-2 mb-4">
    <x-live-search
        name="q"
        target="#accounts-container"
        placeholder="Rechercher un code ou un intitulé…"
        width="w-full sm:max-w-sm"
    />
    <select
        name="classe"
        onchange="this.form.submit()"
        class="rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
        <option value="">Toutes les classes</option>
        @foreach(\App\Models\Account::CLASS_LABELS as $num => $libelle)
            <option value="{{ $num }}" @selected($classe === $num)>Classe {{ $num }} — {{ $libelle }}</option>
        @endforeach
    </select>
    <button
        type="submit"
        class="px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark
            transition-colors shrink-0">
        Filtrer
    </button>
</form>

<div class="px-3 py-2 mb-4 rounded-lg bg-accent/20 border border-secondary/20 text-[11px] text-primary/65">
    <strong>Comptes collectifs.</strong> Les clients, fournisseurs et membres du personnel partagent chacun un compte
    unique. Le détail par tiers ne se traduit jamais par un compte dédié : il vit sur la ligne d'écriture, ce qui
    garde la balance lisible et l'audit praticable.
</div>

<div id="accounts-container">
@if (app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'accounting.ledger.accounts.creer'))
    @foreach (array_filter([session('success'), $errors->first()]) as $message)
        <p role="status" class="bg-accent/20 rounded-lg p-3 mb-4 text-sm">{{ $message }}</p>
    @endforeach
    <details class="bg-white border border-secondary/20 rounded-xl p-4 mb-4">
        <summary class="font-semibold cursor-pointer">Plan de comptes et centres analytiques</summary>
        <form action="{{ route('accounting.ledger.accounts.store') }}" method="post" class="mt-3 flex flex-wrap items-end gap-3 text-sm">
            @csrf
            <input type="hidden" name="kind" value="account">
            <label>Code SYSCOHADA
                <input name="code" inputmode="numeric" maxlength="20" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Libellé
                <input name="label" maxlength="180" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Usage
                <select name="is_postable" class="block rounded-lg border-secondary/30">
                    <option value="1">Compte imputable</option>
                    <option value="0">Regroupement</option>
                </select>
            </label>
            <label><input name="is_collective" type="checkbox" value="1"> Collectif</label>
            <button class="bg-primary text-white px-3 py-2 rounded-lg">Ajouter le compte</button>
        </form>
        <form action="{{ route('accounting.ledger.accounts.store') }}" method="post" class="mt-4 flex flex-wrap items-end gap-3 text-sm">
            @csrf
            <input type="hidden" name="kind" value="center">
            <label>Code du centre
                <input name="code" maxlength="40" pattern="[a-z][a-z0-9_]{0,39}" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Libellé du centre
                <input name="label" maxlength="100" required class="block rounded-lg border-secondary/30">
            </label>
            <button class="bg-primary text-white px-3 py-2 rounded-lg">Enregistrer le centre</button>
        </form>
        <form action="{{ route('accounting.ledger.accounts.store') }}" method="post" class="mt-4 flex flex-wrap items-end gap-3 text-sm">
            @csrf
            <input type="hidden" name="kind" value="rate">
            <label>Code du traitement TVA
                <input name="code" maxlength="20" pattern="[A-Z][A-Z0-9_]{1,19}" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Libellé
                <input name="label" maxlength="120" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Taux total en points de base
                <input name="rate_basis_points" type="number" min="0" max="10000" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Dont CAC en points de base
                <input name="surtax_basis_points" type="number" value="0" min="0" max="10000" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Référence juridique
                <input name="legal_reference" maxlength="180" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Début d’application
                <input name="effective_on" type="date" required class="block rounded-lg border-secondary/30">
            </label>
            <label>Fin d’application
                <input name="ends_on" type="date" class="block rounded-lg border-secondary/30">
            </label>
            <button class="bg-primary text-white px-3 py-2 rounded-lg">Ajouter le traitement TVA</button>
        </form>
    </details>
@endif
@forelse($comptes as $classeNum => $lignes)
    <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-secondary/15 bg-accent/10">
            <h2 class="text-sm font-semibold text-primary">
                Classe {{ $classeNum }} — {{ \App\Models\Account::CLASS_LABELS[$classeNum] ?? '' }}
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <tbody class="divide-y divide-secondary/10">
                    @foreach($lignes as $compte)
                        <tr class="hover:bg-accent/10 {{ $compte->is_postable ? '' : 'bg-accent/5' }}">
                            <td class="px-5 py-2 font-mono whitespace-nowrap {{ $compte->is_postable ? 'text-primary' : 'text-primary/45 font-semibold' }}">
                                {{ $compte->code }}
                            </td>
                            <td class="px-3 py-2 {{ $compte->is_postable ? 'text-primary/75' : 'text-primary/45 font-semibold uppercase text-[10px] tracking-wider' }}">
                                {{ $compte->label }}
                            </td>
                            <td class="px-5 py-2 text-right whitespace-nowrap">
                                @if($compte->is_collective)
                                    <span class="inline-flex px-1.5 py-0.5 rounded bg-secondary/15 text-primary
                                        text-[10px] font-semibold">collectif</span>
                                @endif
                                @unless($compte->is_postable)
                                    <span class="inline-flex px-1.5 py-0.5 rounded bg-accent/40 text-primary/60
                                        text-[10px] font-semibold">regroupement</span>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="bg-white rounded-xl border border-secondary/20 p-8 text-center text-primary/50 text-sm">
        Aucun compte ne correspond à cette recherche.
    </div>
@endforelse
</div>
@endsection

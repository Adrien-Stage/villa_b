@extends('layouts.hotel')

@section('title', 'Plan de comptes')

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Plan de comptes</h1>
    <p class="text-sm text-primary/60 mt-1">SYSCOHADA révisé — sous-ensemble hôtelier</p>
</div>

@include('accounting.ledger.partials.nav')

<form method="GET" class="flex flex-col sm:flex-row gap-2 mb-4">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Rechercher un code ou un intitulé…"
           class="w-full sm:max-w-sm rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
    <select name="classe" class="rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
        <option value="">Toutes les classes</option>
        @foreach(\App\Models\Account::CLASS_LABELS as $num => $libelle)
            <option value="{{ $num }}" @selected($classe === $num)>Classe {{ $num }} — {{ $libelle }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shrink-0">
        Filtrer
    </button>
</form>

<div class="px-3 py-2 mb-4 rounded-lg bg-accent/20 border border-secondary/20 text-[11px] text-primary/65">
    <strong>Comptes collectifs.</strong> Les clients, fournisseurs et membres du personnel partagent chacun un compte
    unique. Le détail par tiers ne se traduit jamais par un compte dédié : il vit sur la ligne d'écriture, ce qui
    garde la balance lisible et l'audit praticable.
</div>

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
                                    <span class="inline-flex px-1.5 py-0.5 rounded bg-secondary/15 text-primary text-[10px] font-semibold">collectif</span>
                                @endif
                                @unless($compte->is_postable)
                                    <span class="inline-flex px-1.5 py-0.5 rounded bg-accent/40 text-primary/60 text-[10px] font-semibold">regroupement</span>
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
@endsection

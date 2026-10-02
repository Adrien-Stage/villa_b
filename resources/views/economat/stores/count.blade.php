@extends('layouts.hotel')

@section('title', 'Inventaire ' . $count->reference . ' — ' . $count->store->name)

@section('content')
@php
    $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
    $editable = $count->isDraft() && $canCount;
@endphp
<div class="max-w-5xl mx-auto">
    <a href="{{ route('economat.stores.show', $count->store) }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> {{ $count->store->name }}
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Inventaire {{ $count->reference }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                {{ $count->store->name }} · {{ $count->count_date->format('d/m/Y') }} · {{ $count->statusLabel() }}
                @if($count->closedBy) · clôturé par {{ $count->closedBy->name }} @endif
            </p>
        </div>
        <div class="text-right">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/45">Consommation constatée</p>
            <p class="text-xl font-semibold text-primary">{{ $fcfa($count->consumptionValue()) }} <span class="text-sm font-normal text-primary/50">FCFA</span></p>
        </div>
    </div>

    @include('economat.partials.flash')

    @if($count->isDraft())
        <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg flex gap-2" role="status">
            <i data-lucide="lock" class="w-4 h-4 shrink-0"></i>
            <p>Comptage en cours : le dépôt ne reçoit plus de livraison. À la clôture, chaque ligne comptée cale le stock du dépôt ;
               ce qui manque devient la consommation du service. Une ligne laissée vide garde son stock théorique.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('economat.stores.counts.update', $count) }}">
        @csrf
        @method('PUT')
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-4">
            @if($lines->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-primary/40">Le dépôt n'avait rien en stock à l'ouverture.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50/70">
                            <tr>
                                <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Article</th>
                                <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Théorique</th>
                                <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Compté</th>
                                <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Écart</th>
                                <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Valeur</th>
                                <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Observation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-secondary/10">
                            @foreach($lines as $ligne)
                                <tr>
                                    <td class="px-5 py-3">
                                        <span class="font-medium text-primary">{{ $ligne->item?->name ?? '—' }}</span>
                                        <span class="block text-[10px] text-primary/40">{{ $ligne->item?->category?->name }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right text-primary/70 whitespace-nowrap">{{ $qte($ligne->theoretical_quantity) }} <span class="text-xs text-primary/40">{{ $ligne->item?->unit }}</span></td>
                                    <td class="px-5 py-3 text-right">
                                        @if($editable)
                                            <label class="sr-only" for="compte-{{ $ligne->id }}">Quantité comptée — {{ $ligne->item?->name }}</label>
                                            <input id="compte-{{ $ligne->id }}" type="number" step="0.001" min="0" name="lines[{{ $ligne->id }}][counted_quantity]"
                                                   value="{{ $ligne->counted_quantity !== null ? (float) $ligne->counted_quantity : '' }}"
                                                   class="w-24 text-right px-2 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                        @else
                                            {{ $ligne->isCounted() ? $qte($ligne->counted_quantity) : '—' }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono {{ $ligne->varianceQuantity() < 0 ? 'text-red-700' : ($ligne->varianceQuantity() > 0 ? 'text-green-700' : 'text-primary/40') }}">
                                        {{ $ligne->isCounted() ? (($ligne->varianceQuantity() > 0 ? '+' : '') . $qte($ligne->varianceQuantity())) : '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-right text-primary/70">{{ $ligne->isCounted() ? $fcfa($ligne->varianceValue()) : '—' }}</td>
                                    <td class="px-5 py-3">
                                        @if($editable)
                                            <label class="sr-only" for="note-{{ $ligne->id }}">Observation — {{ $ligne->item?->name }}</label>
                                            <input id="note-{{ $ligne->id }}" type="text" maxlength="255" name="lines[{{ $ligne->id }}][notes]" value="{{ $ligne->notes }}"
                                                   class="w-full min-w-32 px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                        @else
                                            <span class="text-xs text-primary/60">{{ $ligne->notes }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($editable && $lines->isNotEmpty())
            <div class="flex justify-end mb-4">
                <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">Enregistrer les comptages</button>
            </div>
        @endif
    </form>

    @if($count->isDraft() && $canClose)
        <div class="flex flex-wrap justify-end gap-2">
            <form method="POST" action="{{ route('economat.stores.counts.cancel', $count) }}" onsubmit="return confirm('Annuler cet inventaire ? Le stock du dépôt ne change pas.')">
                @csrf
                <button type="submit" class="px-4 py-2 border border-secondary/30 text-primary/70 text-sm rounded-lg hover:bg-accent/20">Annuler l'inventaire</button>
            </form>
            <form method="POST" action="{{ route('economat.stores.counts.close', $count) }}" onsubmit="return confirm('Clôturer ? Les écarts deviennent la consommation du service.')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">Clôturer et régulariser</button>
            </form>
        </div>
    @endif
</div>
@endsection

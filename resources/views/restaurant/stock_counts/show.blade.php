@extends('layouts.hotel')

@section('title', 'Inventaire ' . $count->reference)

@php
    $countedLines = $lines->filter->isCounted();
    $varianceValue = $count->isClosed()
        ? (int) $count->variance_value
        : (int) $countedLines->sum('variance_value');
    $missingValue = (int) $countedLines->where('variance_value', '<', 0)->sum('variance_value');
    $theoreticalValue = (float) $lines->sum(fn ($line) => (float) $line->theoretical_quantity * (float) $line->unit_cost);
    // Variance en % de la valeur du stock théorique : le secteur tolère 2 à 5 %.
    $variancePercent = $theoreticalValue > 0 ? abs($varianceValue) / $theoreticalValue * 100 : 0;
@endphp

@section('content')
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <div class="flex items-center gap-3">
            <a href="{{ route('restaurant.stock_counts.index') }}" class="text-primary/40 hover:text-primary transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
            <h1 class="text-2xl font-semibold text-primary font-heading">Inventaire {{ $count->reference }}</h1>
            @if($count->isClosed())
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-200">Clôturé</span>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">En cours</span>
            @endif
        </div>
        <p class="text-sm text-primary/60 mt-1">
            Ouvert {{ $count->created_at->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}
            @if($count->openedBy) par {{ $count->openedBy->name }} @endif
            @if($count->isClosed() && $count->closed_at)
                · clôturé {{ $count->closed_at->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}
                @if($count->closedBy) par {{ $count->closedBy->name }} @endif
            @endif
        </p>
    </div>

    @if($canManage && $count->isDraft())
        <form method="POST" action="{{ route('restaurant.stock_counts.close', $count) }}" class="shrink-0"
            onsubmit="return confirm('Clôturer l\'inventaire ? Le stock sera aligné sur les quantités comptées et les écarts seront figés.');">
            @csrf
            <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                Clôturer l'inventaire
            </button>
        </form>
    @endif
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Le verdict de l'inventaire --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-secondary/20 p-5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Lignes comptées</p>
        <p class="text-2xl font-heading font-bold text-primary mt-1">
            {{ $countedLines->count() }}<span class="text-sm text-primary/40"> / {{ $lines->count() }}</span>
        </p>
    </div>

    <div class="bg-white rounded-xl border border-secondary/20 p-5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Valeur du stock théorique</p>
        <p class="text-2xl font-heading font-bold text-primary mt-1">
            {{ number_format($theoreticalValue / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span>
        </p>
    </div>

    <div class="bg-white rounded-xl border {{ $varianceValue < 0 ? 'border-red-200' : 'border-secondary/20' }} p-5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Écart valorisé</p>
        <p class="text-2xl font-heading font-bold mt-1 {{ $varianceValue < 0 ? 'text-red-600' : ($varianceValue > 0 ? 'text-amber-600' : 'text-green-600') }}">
            {{ $varianceValue > 0 ? '+' : '' }}{{ number_format($varianceValue / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span>
        </p>
        @if($missingValue < 0)
            <p class="text-[11px] text-red-600/80 mt-1">
                dont {{ number_format(abs($missingValue) / 100, 0, ',', ' ') }} FCFA de manquants
            </p>
        @endif
    </div>

    <div class="bg-white rounded-xl border {{ $variancePercent > 5 ? 'border-red-200' : 'border-secondary/20' }} p-5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Variance</p>
        <p class="text-2xl font-heading font-bold mt-1 {{ $variancePercent > 5 ? 'text-red-600' : ($variancePercent > 2 ? 'text-amber-600' : 'text-green-600') }}">
            {{ number_format($variancePercent, 1, ',', ' ') }} <span class="text-sm">%</span>
        </p>
        <p class="text-[11px] text-primary/40 mt-1">
            @if($variancePercent > 5)
                Au-delà du seuil : gaspillage, sur-portionnage ou vol.
            @elseif($variancePercent > 2)
                Dans la fourchette haute du secteur (2–5 %).
            @else
                Sous contrôle (norme : 2–5 %).
            @endif
        </p>
    </div>
</div>

<form method="POST" action="{{ route('restaurant.stock_counts.update', $count) }}">
    @csrf
    @method('PUT')

    <div class="bg-white rounded-xl shadow-sm border border-secondary/20 overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 bg-gray-50/50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-primary">Feuille de comptage</h2>
            @if($count->isDraft())
                <p class="text-[11px] text-primary/40">Laisse vide un ingrédient non compté : il ne sera pas ajusté.</p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/20">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Ingrédient</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Stock théorique</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Compté</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Écart</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Valeur</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($lines as $line)
                        @php
                            $variance = (float) $line->variance_quantity;
                            $hasVariance = $line->isCounted() && abs($variance) > 0.0005;
                        @endphp
                        <tr class="{{ $hasVariance && $variance < 0 ? 'bg-red-50/40' : '' }}">
                            <td class="px-4 py-2.5">
                                <p class="text-sm font-medium text-primary">{{ $line->item?->name ?? 'Article supprimé' }}</p>
                                <p class="text-[11px] text-primary/40">
                                    {{ $line->item?->category?->name ?? 'Sans catégorie' }}
                                    · {{ number_format((float) $line->unit_cost / 100, 2, ',', ' ') }} FCFA / {{ $line->item?->unit }}
                                </p>
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm text-primary/70 whitespace-nowrap">
                                {{ rtrim(rtrim(number_format((float) $line->theoretical_quantity, 3, ',', ' '), '0'), ',') }}
                                <span class="text-[11px] text-primary/40">{{ $line->item?->unit }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @if($count->isDraft() && $canManage)
                                    <input type="number" name="lines[{{ $line->id }}][counted_quantity]"
                                        value="{{ $line->counted_quantity !== null ? rtrim(rtrim(number_format((float) $line->counted_quantity, 3, '.', ''), '0'), '.') : '' }}"
                                        min="0" step="0.001" placeholder="—"
                                        class="w-28 px-2 py-1.5 text-sm text-right border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary placeholder-primary/25">
                                @else
                                    <span class="text-sm font-medium text-primary">
                                        {{ $line->isCounted() ? rtrim(rtrim(number_format((float) $line->counted_quantity, 3, ',', ' '), '0'), ',') : '—' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm whitespace-nowrap">
                                @if(!$line->isCounted())
                                    <span class="text-xs text-primary/30">non compté</span>
                                @elseif(!$hasVariance)
                                    <span class="inline-flex items-center gap-1 text-green-600 text-xs font-medium">
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i> juste
                                    </span>
                                @else
                                    <span class="font-semibold {{ $variance < 0 ? 'text-red-600' : 'text-amber-600' }}">
                                        {{ $variance > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($variance, 3, ',', ' '), '0'), ',') }}
                                        <span class="text-[11px] font-normal">{{ $line->item?->unit }}</span>
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm whitespace-nowrap">
                                @if($hasVariance)
                                    <span class="font-semibold {{ $line->variance_value < 0 ? 'text-red-600' : 'text-amber-600' }}">
                                        {{ $line->variance_value > 0 ? '+' : '' }}{{ number_format($line->variance_value / 100, 0, ',', ' ') }} FCFA
                                    </span>
                                @else
                                    <span class="text-xs text-primary/30">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($count->isDraft() && $canManage)
                                    <input type="text" name="lines[{{ $line->id }}][notes]" value="{{ $line->notes }}"
                                        maxlength="255" placeholder="Casse, péremption..."
                                        class="w-full min-w-[10rem] px-2 py-1.5 text-xs border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary placeholder-primary/25">
                                @else
                                    <span class="text-xs text-primary/50">{{ $line->notes ?: '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($count->isDraft() && $canManage)
            <div class="px-5 py-4 border-t border-secondary/20 bg-gray-50 flex items-center justify-between gap-3">
                {{-- Le formulaire d'abandon vit hors de celui-ci : deux <form> ne s'imbriquent pas. --}}
                <button type="submit" form="abandon-count" class="text-xs text-red-600 hover:underline">
                    Abandonner l'inventaire
                </button>

                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Enregistrer le comptage
                </button>
            </div>
        @endif
    </div>
</form>

@if($count->isDraft() && $canManage)
    <form id="abandon-count" method="POST" action="{{ route('restaurant.stock_counts.destroy', $count) }}"
        onsubmit="return confirm('Abandonner cette feuille de comptage ? Aucun stock ne sera ajusté.');">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection

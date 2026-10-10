@extends('layouts.hotel')

@section('title', 'Découpe ' . $decoupe->number . ' — Économat')

@section('content')
@php
    use App\Support\Conditionnement;
    $fcfa = fn ($v) => number_format((int) round($v) / 100, 0, ',', ' ');
    $unite = $decoupe->item?->unit ?? '';
    $reparti = $decoupe->quantiteRepartie();
@endphp

<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        @if($decoupe->item)
            <a href="{{ route('economat.items.transformation.show', $decoupe->item) }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à la transformation de l'article
            </a>
        @endif
        <a href="{{ route('economat.cuts.print', $decoupe) }}" target="_blank"
           class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-secondary/30 rounded-lg text-primary text-xs font-semibold hover:bg-gray-50 shadow-sm">
            <i data-lucide="printer" class="w-4 h-4"></i> Imprimer le bon de découpe
        </a>
    </div>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Bon de découpe</p>
                <h1 class="text-2xl font-heading font-bold text-primary font-mono">{{ $decoupe->number }}</h1>
                <p class="text-sm text-primary/80 mt-1">
                    <strong>{{ Conditionnement::libelle((float) $decoupe->quantity, $unite) }}</strong> de {{ $decoupe->item?->name ?? '—' }}
                    → garde-manger de <strong>{{ $decoupe->restaurant?->name ?? '—' }}</strong>
                </p>
                <p class="text-xs text-primary/50 mt-0.5">Le {{ $decoupe->cut_at->format('d/m/Y à H:i') }} · par {{ $decoupe->cutBy?->name ?? '—' }}</p>
                @if($decoupe->notes)
                    <p class="mt-3 rounded-lg border border-secondary/15 bg-surface-light px-3 py-2 text-xs text-primary/80">{{ $decoupe->notes }}</p>
                @endif
            </div>
            <div class="text-right sm:border-l sm:border-secondary/15 sm:pl-6">
                <div class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Valeur prise</div>
                <div class="text-2xl font-mono font-bold text-primary mt-0.5">{{ $fcfa($decoupe->total_value) }} <span class="text-sm font-sans font-normal text-primary/60">FCFA</span></div>
                <div class="text-[11px] text-primary/45">au coût moyen de {{ $fcfa($decoupe->unit_cost) }} F / {{ $unite }}</div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-secondary/15 bg-gray-50/70">
            <h2 class="text-sm font-semibold text-primary">Portions entrées au garde-manger ({{ $decoupe->lines->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Portion</th>
                        <th class="px-4 py-2.5 text-right">Quantité</th>
                        <th class="px-4 py-2.5 text-right">Part</th>
                        <th class="px-4 py-2.5 text-right">Valeur</th>
                        <th class="px-4 py-2.5 text-right">Coût unitaire</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($decoupe->lines as $ligne)
                        @php $uniteCuisine = $ligne->pantryItem?->unit ?? $unite; @endphp
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-primary">{{ $ligne->label }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                {{ Conditionnement::libelle((float) $ligne->quantity, $unite) }}
                                @if(mb_strtolower($uniteCuisine) !== mb_strtolower($unite))
                                    <span class="block text-[10px] text-primary/50">= {{ Conditionnement::libelle((float) $ligne->pantry_quantity, $uniteCuisine) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-primary/70">{{ $reparti > 0 ? number_format(100 * (float) $ligne->quantity / $reparti, 1, ',', ' ') : 0 }} %</td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold">{{ $fcfa($ligne->value) }} F</td>
                            <td class="px-4 py-2.5 text-right font-mono text-primary/70">{{ $fcfa($ligne->unit_cost) }} F / {{ $uniteCuisine }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-secondary/20 bg-surface-light text-xs">
                    <tr class="font-semibold text-primary">
                        <td class="px-4 py-2.5">Total réparti</td>
                        <td class="px-4 py-2.5 text-right font-mono">{{ Conditionnement::libelle($reparti, $unite) }}</td>
                        <td></td>
                        <td class="px-4 py-2.5 text-right font-mono">{{ $fcfa($decoupe->lines->sum('value')) }} F</td>
                        <td></td>
                    </tr>
                    @if($decoupe->freinte() > 0)
                        <tr class="text-primary/60">
                            <td class="px-4 py-2" colspan="5">Freinte non répartie : {{ Conditionnement::libelle($decoupe->freinte(), $unite) }} — sa valeur est portée par les portions.</td>
                        </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.hotel')

@section('title', $store->name . ' — Dépôt de service')

@section('content')
<div class="max-w-5xl mx-auto">
    <a href="{{ route('economat.stores.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Dépôts de service
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">{{ $store->name }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">{{ $store->departmentLabel() }} @unless($store->is_active)· inactif @endunless</p>
        </div>
        <div class="text-right">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/45">Valeur en stock</p>
            <p class="text-xl font-semibold text-primary">{{ number_format($stocks->sum(fn ($s) => $s->stockValue()) / 100, 0, ',', ' ') }} <span class="text-sm font-normal text-primary/50">FCFA</span></p>
        </div>
    </div>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-secondary/15 bg-gray-50/80">
            <h2 class="text-sm font-semibold text-primary">Stock du dépôt</h2>
        </div>
        @if($stocks->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-primary/40">Rien en stock. Les livraisons de l'économat destinées à ce dépôt l'alimenteront.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Article</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Catégorie</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Stock</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Coût moyen</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Valeur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($stocks as $stock)
                            <tr>
                                <td class="px-5 py-3 font-medium text-primary">{{ $stock->item->name }}</td>
                                <td class="px-5 py-3 text-primary/60 text-xs">{{ $stock->item->category?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span class="font-medium text-primary">{{ rtrim(rtrim(number_format((float) $stock->current_stock, 3, ',', ' '), '0'), ',') }}</span>
                                    <span class="text-primary/40 text-xs">{{ $stock->item->unit }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ number_format($stock->average_cost / 100, 0, ',', ' ') }}</td>
                                <td class="px-5 py-3 text-right font-medium text-primary">{{ number_format($stock->stockValue() / 100, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-secondary/15 bg-gray-50/80">
            <h2 class="text-sm font-semibold text-primary">Derniers mouvements</h2>
        </div>
        @if($movements->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-primary/40">Aucun mouvement.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Date</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Article</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Type</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Quantité</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Motif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($movements as $mouvement)
                            <tr>
                                <td class="px-5 py-3 text-primary/70 text-xs whitespace-nowrap">{{ $mouvement->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3 text-primary">{{ $mouvement->item?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-primary/70 text-xs">{{ $mouvement->typeLabel() }}</td>
                                <td class="px-5 py-3 text-right font-mono {{ (float) $mouvement->quantity < 0 ? 'text-red-700' : 'text-green-700' }}">
                                    {{ (float) $mouvement->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $mouvement->quantity, 3, ',', ' '), '0'), ',') }}
                                </td>
                                <td class="px-5 py-3 text-primary/60 text-xs">{{ $mouvement->reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

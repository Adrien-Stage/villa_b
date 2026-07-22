@extends('layouts.hotel')

@section('title', $item->name . ' — Économat')

@section('content')
<div class="max-w-5xl mx-auto">
    <a href="{{ route('economat.items.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux articles
    </a>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 mb-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-heading font-semibold text-primary">{{ $item->name }}</h1>
                <p class="text-sm text-primary/50 mt-0.5">
                    {{ $item->category?->name ?? 'Sans catégorie' }}
                    @if($item->reference) · <span class="font-mono">{{ $item->reference }}</span>@endif
                    @if($item->supplier) · Fournisseur : {{ $item->supplier->name }}@endif
                </p>
            </div>
            @php $level = $item->stockLevel(); @endphp
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold
                {{ $level === 'out' ? 'bg-red-50 text-red-700' : ($level === 'low' ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700') }}">
                <span class="h-2 w-2 rounded-full {{ $level === 'out' ? 'bg-red-500' : ($level === 'low' ? 'bg-amber-500' : 'bg-green-500') }}"></span>
                {{ rtrim(rtrim(number_format($item->current_stock, 3, ',', ' '), '0'), ',') }} {{ $item->unit }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Seuil d'alerte</p>
                <p class="text-lg font-bold text-primary mt-1">{{ rtrim(rtrim(number_format($item->min_stock, 3, ',', ' '), '0'), ',') }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Coût moyen</p>
                <p class="text-lg font-bold text-primary mt-1">{{ number_format($item->average_cost / 100, 0, ',', ' ') }} <span class="text-xs text-primary/40">F</span></p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Dernier prix d'achat</p>
                <p class="text-lg font-bold text-primary mt-1">{{ number_format($item->last_purchase_price / 100, 0, ',', ' ') }} <span class="text-xs text-primary/40">F</span></p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Valeur du stock</p>
                <p class="text-lg font-bold text-primary mt-1">{{ number_format($item->stockValue() / 100, 0, ',', ' ') }} <span class="text-xs text-primary/40">F</span></p>
            </div>
        </div>

        @if($item->description)
            <p class="text-sm text-primary/60 mt-4">{{ $item->description }}</p>
        @endif
    </div>

    {{-- Journal des mouvements --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-secondary/20">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-primary/50"></i> Historique des mouvements
            </h2>
        </div>
        @if($movements->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-primary/40">Aucun mouvement enregistré pour cet article.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Date</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Type</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Quantité</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Stock après</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Motif</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Par</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($movements as $m)
                            <tr>
                                <td class="px-5 py-2.5 text-primary/50 text-xs whitespace-nowrap">{{ $m->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                        {{ $m->type === 'in' ? 'bg-green-50 text-green-700' : ($m->type === 'out' ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ $m->typeLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-right font-medium {{ $m->quantity > 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $m->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($m->quantity, 3, ',', ' '), '0'), ',') }}
                                </td>
                                <td class="px-5 py-2.5 text-right text-primary/70">{{ rtrim(rtrim(number_format($m->stock_after, 3, ',', ' '), '0'), ',') }}</td>
                                <td class="px-5 py-2.5 text-primary/50 text-xs">{{ $m->reason }}</td>
                                <td class="px-5 py-2.5 text-primary/50 text-xs">{{ $m->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

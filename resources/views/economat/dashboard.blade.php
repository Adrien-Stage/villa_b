@extends('layouts.hotel')

@section('title', 'Économat')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Économat</h1>
            <p class="text-sm text-primary/60 mt-0.5">Magasin central de l'établissement — stock, fournisseurs et demandes des départements.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('economat.orders.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i> Bon de commande
            </a>
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- Cartes d'indicateurs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-secondary/20 rounded-xl p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Articles actifs</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ number_format($stats['items_total'], 0, ',', ' ') }}</p>
        </div>
        <a href="{{ route('economat.items.index', ['filter' => 'alert']) }}" class="bg-white border rounded-xl p-4 transition-colors {{ $stats['below_min'] > 0 ? 'border-amber-300 hover:bg-amber-50' : 'border-secondary/20' }}">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Sous le seuil</p>
            <p class="text-2xl font-bold mt-1 {{ $stats['below_min'] > 0 ? 'text-amber-600' : 'text-primary' }}">{{ $stats['below_min'] }}</p>
            <p class="text-[10px] text-red-500 mt-0.5">{{ $stats['out_of_stock'] }} en rupture</p>
        </a>
        <a href="{{ route('economat.requisitions.index') }}" class="bg-white border rounded-xl p-4 transition-colors {{ $stats['pending_reqs'] > 0 ? 'border-blue-300 hover:bg-blue-50' : 'border-secondary/20' }}">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Demandes en attente</p>
            <p class="text-2xl font-bold mt-1 {{ $stats['pending_reqs'] > 0 ? 'text-blue-600' : 'text-primary' }}">{{ $stats['pending_reqs'] }}</p>
        </a>
        <div class="bg-white border border-secondary/20 rounded-xl p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50">Valeur du stock</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ number_format($stats['stock_value'] / 100, 0, ',', ' ') }}</p>
            <p class="text-[10px] text-primary/40 mt-0.5">FCFA · {{ $stats['open_orders'] }} bon(s) en cours</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Alertes de réapprovisionnement --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-secondary/20 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i> À réapprovisionner
                </h2>
                <span class="text-xs text-primary/40">{{ $alerts->count() }}</span>
            </div>
            @if($alerts->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-primary/40">Aucun article sous le seuil. Le stock est sain.</p>
            @else
                <div class="divide-y divide-secondary/10 max-h-96 overflow-y-auto">
                    @foreach($alerts as $item)
                        <a href="{{ route('economat.items.show', $item) }}" class="flex items-center justify-between px-5 py-3 hover:bg-accent/10 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm text-primary truncate">{{ $item->name }}</p>
                                <p class="text-[11px] text-primary/40">{{ $item->category?->name ?? 'Sans catégorie' }}</p>
                            </div>
                            <div class="text-right shrink-0 ml-3">
                                <p class="text-sm font-semibold {{ $item->isOutOfStock() ? 'text-red-600' : 'text-amber-600' }}">
                                    {{ rtrim(rtrim(number_format($item->current_stock, 3, ',', ' '), '0'), ',') }} {{ $item->unit }}
                                </p>
                                <p class="text-[10px] text-primary/40">seuil {{ rtrim(rtrim(number_format($item->min_stock, 3, ',', ' '), '0'), ',') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Demandes en attente --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-secondary/20 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                    <i data-lucide="inbox" class="w-4 h-4 text-blue-500"></i> Demandes à traiter
                </h2>
                <a href="{{ route('economat.requisitions.index') }}" class="text-xs text-primary/50 hover:underline">Tout voir</a>
            </div>
            @if($pendingRequisitions->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-primary/40">Aucune demande en attente.</p>
            @else
                <div class="divide-y divide-secondary/10 max-h-96 overflow-y-auto">
                    @foreach($pendingRequisitions as $req)
                        <a href="{{ route('economat.requisitions.show', $req) }}" class="flex items-center justify-between px-5 py-3 hover:bg-accent/10 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm text-primary">{{ $req->number }} <span class="text-primary/40">· {{ $req->departmentLabel() }}</span></p>
                                <p class="text-[11px] text-primary/40 truncate">{{ $req->requestedBy?->name ?? '—' }} · {{ $req->lines->count() }} article(s)</p>
                            </div>
                            <span class="text-[11px] text-primary/40 shrink-0 ml-3">{{ $req->created_at->diffForHumans() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Derniers mouvements --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mt-6">
        <div class="px-5 py-3 border-b border-secondary/20">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-primary/50"></i> Derniers mouvements
            </h2>
        </div>
        @if($recentMovements->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-primary/40">Aucun mouvement enregistré.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($recentMovements as $m)
                            <tr>
                                <td class="px-5 py-2.5 text-primary/50 text-xs whitespace-nowrap">{{ $m->occurred_at?->format('d/m H:i') }}</td>
                                <td class="px-5 py-2.5 text-primary">{{ $m->item?->name ?? '—' }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                        {{ $m->type === 'in' ? 'bg-green-50 text-green-700' : ($m->type === 'out' ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ $m->typeLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-right font-medium {{ $m->quantity > 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $m->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($m->quantity, 3, ',', ' '), '0'), ',') }}
                                </td>
                                <td class="px-5 py-2.5 text-primary/40 text-xs truncate max-w-xs">{{ $m->reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

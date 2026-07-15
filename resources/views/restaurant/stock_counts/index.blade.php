@extends('layouts.hotel')

@section('title', 'Inventaires')

@section('content')
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Inventaires</h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            Le stock affiché par le système est théorique : il découle des fiches techniques. L'inventaire physique
            le confronte au réel. L'écart mesure ce qui part en gaspillage, en sur-portionnage ou en vol.
        </p>
    </div>

    @if($canManage && !$openCount)
        <form method="POST" action="{{ route('restaurant.stock_counts.store') }}" class="shrink-0">
            @csrf
            <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                Ouvrir un inventaire
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

@if($openCount)
    <a href="{{ route('restaurant.stock_counts.show', $openCount) }}"
        class="mb-6 block rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 hover:bg-amber-100/60 transition-colors">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <i data-lucide="clipboard-list" class="w-4 h-4 text-amber-600 mt-0.5 shrink-0"></i>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-900">Inventaire {{ $openCount->reference }} en cours</p>
                    <p class="text-xs text-amber-800/80 mt-0.5">
                        Ouvert {{ $openCount->created_at->locale('fr')->diffForHumans() }}
                        @if($openCount->openedBy) par {{ $openCount->openedBy->name }} @endif
                        · saisis les quantités comptées puis clôture.
                    </p>
                </div>
            </div>
            <i data-lucide="arrow-right" class="w-4 h-4 text-amber-600 shrink-0"></i>
        </div>
    </a>
@endif

<div class="bg-white rounded-xl shadow-sm border border-secondary/20 overflow-hidden">
    @if($counts->isEmpty())
        <div class="px-5 py-12 text-center text-primary/40">
            <i data-lucide="clipboard-list" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
            <p class="text-sm">Aucun inventaire réalisé.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/20">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Référence</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">État</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Lignes</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Écart valorisé</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Clôturé par</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($counts as $count)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <p class="text-sm font-semibold text-primary">{{ $count->reference }}</p>
                                <p class="text-[11px] text-primary/40">
                                    {{ $count->created_at->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                @if($count->isClosed())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-200">Clôturé</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">En cours</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-primary/60">{{ $count->lines_count }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($count->isClosed())
                                    <span class="text-sm font-semibold {{ $count->variance_value < 0 ? 'text-red-600' : ($count->variance_value > 0 ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ $count->variance_value > 0 ? '+' : '' }}{{ number_format($count->variance_value / 100, 0, ',', ' ') }} FCFA
                                    </span>
                                @else
                                    <span class="text-xs text-primary/30">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-primary/60">
                                {{ $count->closedBy?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('restaurant.stock_counts.show', $count) }}"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                                    Ouvrir <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-4 border-t border-secondary/15">
            {{ $counts->links() }}
        </div>
    @endif
</div>
@endsection

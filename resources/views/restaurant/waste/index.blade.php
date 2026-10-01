@extends('layouts.hotel')

@section('title', 'Pertes & Gaspillage — Restaurant')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Pertes, Gaspillage & Déchets</h1>
        <p class="text-sm text-primary/50 mt-0.5">Comptabilité matière et traçabilité des sorties non vendues (cuisine, bar, restaurant)</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="{{ route('restaurant.consumption.index') }}" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="pie-chart" class="w-3.5 h-3.5"></i> Analyse Consommation
        </a>
        @can('creer', \App\Models\RestaurantWasteLog::class)
        <a href="{{ route('restaurant.waste.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Déclarer une perte
        </a>
        @else
            @if(app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'restaurant.waste.creer'))
            <a href="{{ route('restaurant.waste.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Déclarer une perte
            </a>
            @endif
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold mb-1">Erreur :</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- KPI Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Total Pertes (Période)</p>
            <p class="text-2xl font-heading font-semibold text-red-600 mt-1">
                {{ number_format($totalValuationCentimes / 100, 0, ',', ' ') }} <span class="text-sm font-normal text-primary/60">FCFA</span>
            </p>
        </div>
        <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center">
            <i data-lucide="trash-2" class="w-5 h-5"></i>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Fiches Déclarées</p>
            <p class="text-2xl font-heading font-semibold text-primary mt-1">{{ $totalDeclarations }}</p>
        </div>
        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
            <i data-lucide="file-text" class="w-5 h-5"></i>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15">
        <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold mb-2">Principaux Motifs</p>
        <div class="space-y-1 text-xs">
            @forelse($reasonsBreakdown->take(3) as $r)
                <div class="flex items-center justify-between text-primary/70">
                    <span>{{ $reasonLabels[$r->reason] ?? $r->reason }} ({{ $r->count }})</span>
                    <span class="font-semibold text-primary">{{ number_format($r->total_val / 100, 0, ',', ' ') }} FCFA</span>
                </div>
            @empty
                <p class="text-primary/40 italic">Aucune perte enregistrée</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Filtres --}}
<div class="bg-white rounded-xl shadow-sm border border-secondary/15 p-4 mb-6">
    <form method="GET" action="{{ route('restaurant.waste.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Du</label>
            <input type="date" name="start_date" value="{{ $startDate }}" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Au</label>
            <input type="date" name="end_date" value="{{ $endDate }}" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Motif</label>
            <select name="reason" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
                <option value="">Tous les motifs</option>
                @foreach($reasonLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(request('reason') === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Atelier / Rayon</label>
            <select name="department" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
                <option value="">Tous les ateliers</option>
                @foreach($departmentLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(request('department') === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Ingrédient</label>
            <select name="item_id" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
                <option value="">Tous les articles</option>
                @foreach($activeItems as $item)
                    <option value="{{ $item->id }}" @selected(request('item_id') == $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="w-full py-2 px-3 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity">
                Filtrer
            </button>
            <a href="{{ route('restaurant.waste.index') }}" class="py-2 px-3 border border-secondary/25 bg-secondary/10 text-primary text-xs rounded-lg hover:bg-secondary/20" title="Réinitialiser">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
            </a>
        </div>
    </form>
</div>

{{-- Tableau des déclarations --}}
<div class="bg-white rounded-xl shadow-sm border border-secondary/15 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-secondary/10 text-primary font-semibold border-b border-secondary/20">
                <tr>
                    <th class="py-3 px-4">Date & Réf.</th>
                    <th class="py-3 px-4">Article</th>
                    <th class="py-3 px-4 text-right">Quantité mise au rebut</th>
                    <th class="py-3 px-4 text-right">Coût Unitaire</th>
                    <th class="py-3 px-4 text-right">Valeur Perte</th>
                    <th class="py-3 px-4">Motif & Atelier</th>
                    <th class="py-3 px-4">Responsable</th>
                    <th class="py-3 px-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($logs as $log)
                    <tr class="hover:bg-accent/5 transition-colors">
                        <td class="py-3 px-4">
                            <span class="font-mono font-semibold text-primary">{{ $log->reference }}</span>
                            <div class="text-[11px] text-primary/50">{{ $log->occurred_at?->format('d/m/Y H:i') }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="font-medium text-primary">{{ $log->item?->name ?? 'Article inconnu' }}</span>
                            @if($log->item?->category)
                                <div class="text-[10px] text-primary/50">{{ $log->item->category->name }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-medium text-red-600">
                            -{{ rtrim(rtrim(number_format((float) $log->quantity, 3, ',', ' '), '0'), ',') }} {{ $log->item?->unit }}
                        </td>
                        <td class="py-3 px-4 text-right text-primary/70">
                            {{ number_format($log->unitCostFcfa(), 2, ',', ' ') }} FCFA
                        </td>
                        <td class="py-3 px-4 text-right font-semibold text-red-600">
                            {{ $log->formattedTotalCost() }}
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-red-100 text-red-800">
                                {{ $log->reasonLabel() }}
                            </span>
                            <div class="text-[10px] text-primary/50 mt-0.5">{{ $log->departmentLabel() }}</div>
                        </td>
                        <td class="py-3 px-4 text-primary/70">
                            <div>{{ $log->responsible_person ?? 'Non spécifié' }}</div>
                            <div class="text-[10px] text-primary/40">Saisi par {{ $log->recordedBy?->name ?? 'Système' }}</div>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('restaurant.waste.show', $log) }}" class="p-1.5 text-primary/70 hover:text-primary hover:bg-secondary/10 rounded-lg transition-colors" title="Voir le détail">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <a href="{{ route('restaurant.waste.print', $log) }}" target="_blank" class="p-1.5 text-primary/70 hover:text-primary hover:bg-secondary/10 rounded-lg transition-colors" title="Imprimer le PV de perte">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-primary/40">
                            <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            Aucune perte ou déchet enregistré sur la période sélectionnée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="p-4 border-t border-secondary/15">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection

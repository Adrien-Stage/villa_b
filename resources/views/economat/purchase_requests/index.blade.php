@extends('layouts.hotel')

@section('title', 'Demandes d\'achat — Économat')

@section('content')
@php
    $statusStyles = [
        'pending'   => 'bg-amber-50 text-amber-700 border-amber-200',
        'approved'  => 'bg-blue-50 text-blue-700 border-blue-200',
        'rejected'  => 'bg-red-50 text-red-700 border-red-200',
        'converted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
    ];

    $priorityBadges = [
        'low'    => 'bg-gray-100 text-gray-600',
        'normal' => 'bg-sky-50 text-sky-700',
        'urgent' => 'bg-rose-50 text-rose-700 font-bold',
    ];
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    {{-- En-tête --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Demandes d'achat (Approvisionnement)</h1>
            <p class="text-sm text-primary/60 mt-0.5">Besoins d'approvisionnement exprimés par les services et validation hiérarchique.</p>
        </div>
        @droit('economat.purchase_requests.creer')
            <a href="{{ route('economat.purchase_requests.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> Exprimer un besoin d'achat
            </a>
        @enddroit
    </div>

    @include('economat.partials.flash')

    {{-- Cartes de synthèse --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
            <span class="text-xs text-primary/50 font-medium">Total demandes</span>
            <div class="text-2xl font-bold font-mono text-primary mt-1">{{ $stats['total'] }}</div>
        </div>
        <div class="bg-white border border-amber-200 rounded-xl p-4 shadow-sm bg-amber-50/20">
            <span class="text-xs text-amber-700 font-medium">En attente d'avis</span>
            <div class="text-2xl font-bold font-mono text-amber-700 mt-1">{{ $stats['pending'] }}</div>
        </div>
        <div class="bg-white border border-blue-200 rounded-xl p-4 shadow-sm bg-blue-50/20">
            <span class="text-xs text-blue-700 font-medium">Validées / À commander</span>
            <div class="text-2xl font-bold font-mono text-blue-700 mt-1">{{ $stats['approved'] }}</div>
        </div>
        <div class="bg-white border border-emerald-200 rounded-xl p-4 shadow-sm bg-emerald-50/20">
            <span class="text-xs text-emerald-700 font-medium">Converties en bons</span>
            <div class="text-2xl font-bold font-mono text-emerald-700 mt-1">{{ $stats['converted'] }}</div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
        <form method="GET" action="{{ route('economat.purchase_requests.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[160px]">
                <select name="status" class="w-full px-3 py-1.5 text-sm border border-secondary/20 rounded-lg text-primary focus:outline-none focus:border-primary">
                    <option value="">Tous les statuts</option>
                    @foreach($statuses as $key => $lbl)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[160px]">
                <select name="department" class="w-full px-3 py-1.5 text-sm border border-secondary/20 rounded-lg text-primary focus:outline-none focus:border-primary">
                    <option value="">Tous les départements</option>
                    @foreach($departments as $key => $lbl)
                        <option value="{{ $key }}" @selected(request('department') === $key)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[140px]">
                <select name="priority" class="w-full px-3 py-1.5 text-sm border border-secondary/20 rounded-lg text-primary focus:outline-none focus:border-primary">
                    <option value="">Toutes priorités</option>
                    <option value="low" @selected(request('priority') === 'low')>Basse</option>
                    <option value="normal" @selected(request('priority') === 'normal')>Normale</option>
                    <option value="urgent" @selected(request('priority') === 'urgent')>Urgente</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-1.5 bg-secondary/10 hover:bg-secondary/20 text-primary text-sm font-medium rounded-lg transition-colors">
                Filtrer
            </button>
            @if(request()->hasAny(['status', 'department', 'priority']))
                <a href="{{ route('economat.purchase_requests.index') }}" class="px-3 py-1.5 text-xs text-primary/50 hover:text-primary">
                    Effacer
                </a>
            @endif
        </form>
    </div>

    {{-- Tableau des demandes --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        @if($requests->isEmpty())
            <div class="py-12 text-center text-sm text-primary/40">
                <i data-lucide="clipboard-list" class="w-10 h-10 mx-auto text-primary/20 mb-3"></i>
                Aucune demande d'achat trouvée.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70 border-b border-secondary/10">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Numéro</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Département</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Demandeur</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-primary/50">Priorité</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-primary/50">Statut</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Montant Est.</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($requests as $req)
                            <tr class="hover:bg-accent/5 cursor-pointer transition-colors" onclick="window.location='{{ route('economat.purchase_requests.show', $req) }}'">
                                <td class="px-5 py-3 font-mono font-bold text-primary">{{ $req->number }}</td>
                                <td class="px-5 py-3 text-primary/70">{{ $req->departmentLabel() }}</td>
                                <td class="px-5 py-3 text-primary/80">{{ $req->requestedBy?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $priorityBadges[$req->priority] ?? 'bg-gray-100' }}">
                                        {{ $req->priorityLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusStyles[$req->status] ?? 'bg-gray-100' }}">
                                        {{ $req->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-primary/70 font-mono">{{ $req->lines_count }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold text-primary">
                                    {{ number_format($req->total_estimated_amount / 100, 0, ',', ' ') }} F
                                </td>
                                <td class="px-5 py-3 text-xs text-primary/50">{{ $req->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-secondary/10">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

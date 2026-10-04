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
    <x-table :rows="$requests" empty="Aucune demande d'achat trouvée." empty-icon="clipboard-list" caption="Demandes d'achat">
        <x-slot:head>
            <x-table.col>Numéro</x-table.col>
            <x-table.col>Département</x-table.col>
            <x-table.col hide="xl">Demandeur</x-table.col>
            <x-table.col align="center" hide="lg">Priorité</x-table.col>
            <x-table.col align="center">Statut</x-table.col>
            <x-table.col align="right" hide="2xl">Articles</x-table.col>
            <x-table.col align="right">Montant est.</x-table.col>
            <x-table.col hide="xl">Date</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($requests as $req)
            <x-table.row :href="route('economat.purchase_requests.show', $req)">
                <x-table.cell nowrap>
                    <a href="{{ route('economat.purchase_requests.show', $req) }}" class="font-mono font-bold text-primary hover:underline">{{ $req->number }}</a>
                </x-table.cell>
                <x-table.cell class="text-primary/70">{{ $req->departmentLabel() }}</x-table.cell>
                <x-table.cell hide="xl" class="text-primary/80">{{ $req->requestedBy?->name ?? '—' }}</x-table.cell>
                <x-table.cell align="center" hide="lg" nowrap>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $priorityBadges[$req->priority] ?? 'bg-gray-100' }}">{{ $req->priorityLabel() }}</span>
                </x-table.cell>
                <x-table.cell align="center" nowrap>
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {{ $statusStyles[$req->status] ?? 'bg-gray-100' }}">{{ $req->statusLabel() }}</span>
                </x-table.cell>
                <x-table.cell align="right" hide="2xl" class="font-mono text-primary/70">{{ $req->lines_count }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono font-semibold">{{ number_format($req->total_estimated_amount / 100, 0, ',', ' ') }} F</x-table.cell>
                <x-table.cell hide="xl" nowrap class="text-xs text-primary/50">{{ $req->created_at->format('d/m/Y') }}</x-table.cell>
                <x-table.actions :label="'Actions pour la demande '.$req->number">
                    <x-table.action :href="route('economat.purchase_requests.show', $req)" icon="eye">Ouvrir</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection

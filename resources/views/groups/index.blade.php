@extends('layouts.hotel')

@section('title', 'Groupes')

@section('content')
<div x-data="{ showOpenRegisterModal: @json(!$isCashRegisterOpen) }">

{{-- En-tête --}}
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Réservations Groupe</h1>
        <p class="text-sm text-primary/50 mt-0.5">{{ $stats['total'] }} dossier{{ $stats['total'] > 1 ? 's' : '' }} au total</p>
    </div>
    @droit('groups.creer')
        @if($isCashRegisterOpen)
            <a href="{{ route('groups.create') }}"
               class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Nouveau groupe
            </a>
        @else
            <a href="{{ route('bookings.cash_register.open') }}"
               class="flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors">
                <i data-lucide="unlock" class="w-4 h-4"></i>
                Ouvrir la caisse
            </a>
        @endif
    @enddroit
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    @php
        $statCards = [
            ['key' => 'total',     'label' => 'Total dossiers', 'icon' => 'folder',     'color' => 'text-primary',      'bg' => 'bg-accent/30'],
            ['key' => 'pending',   'label' => 'En attente',     'icon' => 'clock',      'color' => 'text-yellow-600',   'bg' => 'bg-yellow-50'],
            ['key' => 'confirmed', 'label' => 'Confirmés',      'icon' => 'check-circle','color' => 'text-blue-600',    'bg' => 'bg-blue-50'],
            ['key' => 'in_house',  'label' => 'En séjour',      'icon' => 'hotel',      'color' => 'text-green-600',    'bg' => 'bg-green-50'],
        ];
    @endphp
    @foreach($statCards as $card)
        <div class="bg-white rounded-xl p-4 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg {{ $card['bg'] }} flex items-center justify-center flex-shrink-0">
                <i data-lucide="{{ $card['icon'] }}" class="w-4 h-4 {{ $card['color'] }}"></i>
            </div>
            <div>
                <p class="text-lg font-heading font-semibold text-primary leading-none">{{ $stats[$card['key']] }}</p>
                <p class="text-xs text-primary/50 mt-0.5">{{ $card['label'] }}</p>
            </div>
        </div>
    @endforeach
</div>

{{-- Barre outils --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-5">
    <div class="flex items-center gap-2">
        @php
            $filters = [
                ''          => 'Tous',
                'pending'   => 'En attente',
                'confirmed' => 'Confirmés',
                'in_house'  => 'En séjour',
                'completed' => 'Terminés',
                'cancelled' => 'Annulés',
            ];
        @endphp
        @foreach($filters as $value => $label)
            <a href="{{ route('groups.index', array_merge(request()->except('status', 'page'), $value ? ['status' => $value] : [])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                      {{ request('status', '') === $value
                          ? 'bg-primary text-white'
                          : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('groups.index') }}" class="relative">
        <input type="text" id="search-input" name="search"
               value="{{ request('search') }}"
               placeholder="Code, nom du groupe, contact..."
               autocomplete="off"
               class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-64 transition-all">
        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
    </form>
</div>

{{-- Table --}}
@php
    $statusColors = [
        'pending'   => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
        'in_house'  => 'bg-green-50 text-green-700 border-green-200',
        'completed' => 'bg-gray-50 text-gray-600 border-gray-200',
        'cancelled' => 'bg-red-50 text-red-600 border-red-200',
    ];
    $eventLabels = ['family' => 'Famille', 'corporate' => 'Corporate', 'wedding' => 'Mariage', 'tour_group' => 'Tour groupe'];
@endphp
<x-table :rows="$groups" empty="Aucun dossier groupe trouvé." empty-icon="users" caption="Dossiers groupe">
    <x-slot:head>
        <x-table.col>Code</x-table.col>
        <x-table.col>Groupe</x-table.col>
        <x-table.col hide="2xl">Contact</x-table.col>
        <x-table.col hide="xl">Période</x-table.col>
        <x-table.col align="right">Chambres</x-table.col>
        <x-table.col>Statut</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($groups as $group)
        @php
            $sc = $statusColors[$group->status] ?? 'bg-secondary/10 text-primary/60 border-secondary/20';
            $nuits = $group->start_date->diffInDays($group->end_date);
        @endphp
        <x-table.row :href="route('groups.show', $group)">
            <x-table.cell nowrap>
                <a href="{{ route('groups.show', $group) }}" class="font-mono text-sm font-medium text-primary hover:underline">{{ $group->group_code }}</a>
            </x-table.cell>
            <x-table.cell>
                <p class="max-w-60 truncate text-sm font-medium text-primary">{{ $group->group_name }}</p>
                @if($group->event_type)
                    <p class="text-xs text-primary/45">{{ $eventLabels[$group->event_type] ?? $group->event_type }}</p>
                @endif
            </x-table.cell>
            <x-table.cell hide="2xl">
                <p class="max-w-48 truncate text-xs text-primary/70">{{ $group->contactCustomer?->full_name ?? '—' }}</p>
            </x-table.cell>
            <x-table.cell hide="xl" nowrap>
                <p class="text-xs text-primary">{{ $group->start_date->locale('fr')->isoFormat('D MMM') }} → {{ $group->end_date->locale('fr')->isoFormat('D MMM YYYY') }}</p>
                <p class="text-xs text-primary/45">{{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }}</p>
            </x-table.cell>
            <x-table.cell align="right" class="font-medium">{{ $group->bookings_count }}</x-table.cell>
            <x-table.cell nowrap>
                <span class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize {{ $sc }}">{{ ucfirst($group->status) }}</span>
            </x-table.cell>
            <x-table.actions :label="'Actions pour le groupe '.$group->group_code">
                <x-table.action :href="route('groups.show', $group)" icon="eye">Ouvrir</x-table.action>
                @droit('groups.modifier')
                    <x-table.action :href="route('groups.edit', $group)" icon="pencil">Modifier</x-table.action>
                @enddroit
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>

<script>
let searchTimer;
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => this.closest('form').submit(), 400);
});
</script>

    {{-- Modal Caisse Fermée --}}
    <div x-show="showOpenRegisterModal" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        {{-- Overlay backdrop --}}
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showOpenRegisterModal = false"></div>

        {{-- Modal card wrapper --}}
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                {{-- Header/Icon section --}}
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-amber-50 sm:mx-0 sm:h-10 sm:w-10 border border-amber-100">
                            <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-base font-heading font-semibold leading-6 text-primary" id="modal-title">
                                Caisse de réception fermée
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-primary/70 leading-relaxed">
                                    Votre caisse est actuellement fermée. Vous devez l'ouvrir afin de pouvoir enregistrer de nouvelles réservations de groupe et traiter les paiements.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Action buttons --}}
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2 border-t border-slate-100">
                    <a href="{{ route('bookings.cash_register.open') }}" 
                       class="inline-flex w-full justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 transition-all sm:ml-3 sm:w-auto">
                        Ouvrir la caisse
                    </a>
                    <button type="button" 
                            @click="showOpenRegisterModal = false"
                            class="mt-3 inline-flex w-full justify-center rounded-lg bg-white border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition-all sm:mt-0 sm:w-auto">
                        Consulter uniquement
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
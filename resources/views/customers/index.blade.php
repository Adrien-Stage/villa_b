@extends('layouts.hotel')

@section('title', 'Clients')

@section('content')

{{-- En-tête --}}
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Clients</h1>
        <p class="text-sm text-primary/50 mt-0.5">
            {{ $stats['total'] }} client{{ $stats['total'] > 1 ? 's' : '' }} enregistrés
        </p>
    </div>
    <div class="flex items-center gap-3">
        @undroit('customers.export', 'customers.import')
            @droit('customers.export')
            <a href="{{ route('customers.export') }}" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors" title="Exporter les clients en CSV">
                <i data-lucide="download" class="w-4 h-4"></i> Exporter
            </a>
            @enddroit
            @droit('customers.import')
            <button type="button" onclick="document.getElementById('modal-import-customers').classList.remove('hidden')" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors" title="Importer des clients depuis un CSV">
                <i data-lucide="upload" class="w-4 h-4"></i> Importer
            </button>
            @enddroit
        @endundroit
        <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-secondary/20 shadow-sm">
            <i data-lucide="star" class="w-3.5 h-3.5 text-yellow-500"></i>
            <span class="text-xs font-medium text-primary">{{ $stats['vip'] }} VIP</span>
        </div>
        <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-secondary/20 shadow-sm">
            <i data-lucide="award" class="w-3.5 h-3.5 text-purple-500"></i>
            <span class="text-xs font-medium text-primary">{{ $stats['platinum'] }} Platinum</span>
        </div>
        <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg border border-secondary/20 shadow-sm">
            <i data-lucide="medal" class="w-3.5 h-3.5 text-yellow-600"></i>
            <span class="text-xs font-medium text-primary">{{ $stats['gold'] }} Gold</span>
        </div>
    </div>
</div>

<x-csv-import-errors />

{{-- Barre outils --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-5">
    <div class="flex items-center gap-2">
        @php
            $levels = [
                ''         => 'Tous',
                'platinum' => 'Platinum',
                'gold'     => 'Gold',
                'silver'   => 'Silver',
                'bronze'   => 'Bronze',
            ];
        @endphp
        @foreach($levels as $value => $label)
            <a href="{{ route('customers.index', array_merge(request()->except('level', 'page'), $value ? ['level' => $value] : [])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                      {{ request('level', '') === $value
                          ? 'bg-primary text-white'
                          : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ $label }}
            </a>
        @endforeach
        <a href="{{ route('customers.index', array_merge(request()->except('vip_only', 'page'), request()->boolean('vip_only') ? [] : ['vip_only' => 1])) }}"
           class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                  {{ request()->boolean('vip_only')
                      ? 'bg-yellow-400 text-yellow-900'
                      : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
            <i data-lucide="star" class="w-3 h-3"></i>
            VIP only
        </a>
    </div>

    <form method="GET" action="{{ route('customers.index') }}" class="relative">
        <input type="hidden" name="level" value="{{ request('level') }}">
        <input type="hidden" name="vip_only" value="{{ request('vip_only') }}">
        <input type="text"
               id="search-input"
               name="search"
               value="{{ request('search') }}"
               placeholder="Nom, email, téléphone..."
               autocomplete="off"
               class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-64 transition-all">
        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
    </form>
</div>

@php
    $levelColors = [
        'platinum' => 'bg-purple-50 text-purple-700 border-purple-200',
        'gold'     => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'silver'   => 'bg-gray-50 text-gray-600 border-gray-200',
        'bronze'   => 'bg-orange-50 text-orange-700 border-orange-200',
    ];
@endphp
<x-table :rows="$customers" empty="Aucun client trouvé." empty-icon="users" caption="Clients">
    <x-slot:head>
        <x-table.col>Client</x-table.col>
        <x-table.col hide="xl">Contact</x-table.col>
        <x-table.col hide="2xl">Fidélité</x-table.col>
        <x-table.col align="right">Séjours</x-table.col>
        <x-table.col align="right">Dépensé</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($customers as $customer)
        @php $lc = $levelColors[$customer->loyalty_level] ?? 'bg-secondary/10 text-primary/60 border-secondary/20'; @endphp
        <x-table.row :href="route('customers.show', $customer)">
            <x-table.cell>
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary" aria-hidden="true">
                        <span class="text-xs font-semibold text-white">{{ strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1)) }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('customers.show', $customer) }}" class="max-w-56 truncate text-sm font-medium text-primary hover:underline">{{ $customer->full_name }}</a>
                            @if($customer->is_vip)
                                <i data-lucide="star" class="h-3 w-3 shrink-0 text-yellow-500" aria-label="Client VIP"></i>
                            @endif
                            @if($customer->is_blacklisted)
                                <i data-lucide="ban" class="h-3 w-3 shrink-0 text-red-500" aria-label="Sur liste noire"></i>
                            @endif
                        </div>
                        <p class="text-xs text-primary/45">{{ $customer->nationality ?? '—' }}</p>
                    </div>
                </div>
            </x-table.cell>
            <x-table.cell hide="xl">
                <p class="max-w-56 truncate text-xs text-primary/70">{{ $customer->email ?? '—' }}</p>
                <p class="text-xs text-primary/45">{{ $customer->phone ?? '—' }}</p>
            </x-table.cell>
            <x-table.cell hide="2xl" nowrap>
                <span class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize {{ $lc }}">{{ $customer->loyalty_level }}</span>
                <p class="mt-0.5 text-xs text-primary/45">{{ number_format($customer->loyalty_points) }} pts</p>
            </x-table.cell>
            <x-table.cell align="right" nowrap>
                <p class="text-sm font-medium text-primary">{{ $customer->bookings_count }}</p>
                <p class="text-xs text-primary/45">{{ $customer->total_nights_stayed }} nuits</p>
            </x-table.cell>
            <x-table.cell align="right" nowrap>
                <span class="text-sm font-medium text-primary">{{ number_format($customer->total_spent / 100, 0, ',', ' ') }}</span>
                <span class="text-[10px] text-primary/45">FCFA</span>
            </x-table.cell>
            <x-table.actions :label="'Actions pour '.$customer->full_name">
                <x-table.action :href="route('customers.show', $customer)" icon="eye">Ouvrir</x-table.action>
                @droit('customers.modifier')
                    <x-table.action :href="route('customers.edit', $customer)" icon="pencil">Modifier</x-table.action>
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

@droit('customers.import')
    <x-csv-import-modal
        id="modal-import-customers"
        title="Importer des clients (CSV)"
        :action="route('customers.import')"
        :template="route('customers.export', ['template' => 1])"
        structure="prenom;nom;email;telephone;pays;nationalite;type_piece;numero_piece;date_naissance;adresse;ville;vip;blackliste;notes"
        submit-label="Importer les clients">
        <li><strong>prenom</strong> et <strong>nom</strong> obligatoires</li>
        <li><strong>email</strong> sert de clé anti-doublon (les emails déjà présents sont ignorés)</li>
        <li><strong>pays</strong> = code ISO (CM, FR…) · <strong>date_naissance</strong> = AAAA-MM-JJ · <strong>vip</strong>/<strong>blackliste</strong> = oui/non</li>
    </x-csv-import-modal>
@enddroit

@endsection
@extends('layouts.hotel')

@section('title', 'Clients')

@section('content')

{{-- En-tête --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Clients</h1>
        <p class="text-sm text-primary/50 mt-0.5">
            {{ $stats['total'] }} client{{ $stats['total'] > 1 ? 's' : '' }} enregistrés
        </p>
    </div>
    <div class="flex items-center gap-3">
        @role('reception','manager')
            <a href="{{ route('customers.export') }}" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors" title="Exporter les clients en CSV">
                <i data-lucide="download" class="w-4 h-4"></i> Exporter
            </a>
            <button type="button" onclick="document.getElementById('modal-import-customers').classList.remove('hidden')" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/30 transition-colors" title="Importer des clients depuis un CSV">
                <i data-lucide="upload" class="w-4 h-4"></i> Importer
            </button>
        @endrole
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
<div class="flex items-center justify-between gap-4 mb-5">
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

{{-- Table --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden">

    @if($customers->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-primary/30">
            <i data-lucide="users" class="w-10 h-10 mb-3 opacity-40"></i>
            <p class="text-sm">Aucun client trouvé</p>
        </div>
    @else
        {{-- En-tête tableau --}}
        <div class="grid grid-cols-12 gap-4 px-5 py-3 border-b border-secondary/10 bg-accent/20">
            <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40">Client</div>
            <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40">Contact</div>
            <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Fidélité</div>
            <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Séjours</div>
            <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40">Dépensé</div>
            <div class="col-span-1"></div>
        </div>

        {{-- Lignes --}}
        @foreach($customers as $customer)
            <a href="{{ route('customers.show', $customer) }}"
               class="grid grid-cols-12 gap-4 px-5 py-3.5 border-b border-secondary/10 hover:bg-accent/10 transition-colors items-center cursor-pointer">

                {{-- Client --}}
                <div class="col-span-3 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-semibold">
                            {{ strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1)) }}
                        </span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm font-medium text-primary truncate">{{ $customer->full_name }}</span>
                            @if($customer->is_vip)
                                <i data-lucide="star" class="w-3 h-3 text-yellow-500 flex-shrink-0"></i>
                            @endif
                            @if($customer->is_blacklisted)
                                <i data-lucide="ban" class="w-3 h-3 text-red-500 flex-shrink-0"></i>
                            @endif
                        </div>
                        <p class="text-xs text-primary/40">{{ $customer->nationality ?? '—' }}</p>
                    </div>
                </div>

                {{-- Contact --}}
                <div class="col-span-3">
                    <p class="text-xs text-primary/70 truncate">{{ $customer->email ?? '—' }}</p>
                    <p class="text-xs text-primary/40">{{ $customer->phone ?? '—' }}</p>
                </div>

                {{-- Fidélité --}}
                <div class="col-span-2">
                    @php
                        $levelColors = [
                            'platinum' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'gold'     => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                            'silver'   => 'bg-gray-50 text-gray-600 border-gray-200',
                            'bronze'   => 'bg-orange-50 text-orange-700 border-orange-200',
                        ];
                        $lc = $levelColors[$customer->loyalty_level] ?? 'bg-secondary/10 text-primary/60 border-secondary/20';
                    @endphp
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full border {{ $lc }} capitalize">
                        {{ $customer->loyalty_level }}
                    </span>
                    <p class="text-xs text-primary/40 mt-0.5">
                        {{ number_format($customer->loyalty_points) }} pts
                    </p>
                </div>

                {{-- Séjours --}}
                <div class="col-span-2">
                    <p class="text-sm font-medium text-primary">{{ $customer->bookings_count }}</p>
                    <p class="text-xs text-primary/40">{{ $customer->total_nights_stayed }} nuits</p>
                </div>

                {{-- Dépensé --}}
                <div class="col-span-1">
                    <p class="text-xs font-medium text-primary">
                        {{ number_format($customer->total_spent / 100, 0, ',', ' ') }}
                    </p>
                    <p class="text-[10px] text-primary/40">FCFA</p>
                </div>

                {{-- Chevron --}}
                <div class="col-span-1 flex justify-end">
                    <i data-lucide="chevron-right" class="w-4 h-4 text-primary/30 group-hover:text-primary"></i>
                </div>

            </a>
        @endforeach
    @endif
</div>

{{-- Pagination --}}
@if($customers->hasPages())
    <div class="mt-4">{{ $customers->links() }}</div>
@endif

<script>
let searchTimer;
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => this.closest('form').submit(), 400);
});
</script>

@role('reception','manager')
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
@endrole

@endsection
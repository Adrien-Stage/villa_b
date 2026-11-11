@extends('layouts.hotel')

@section('title', 'Banquets')

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $couleurs = ['devis' => 'bg-amber-50 text-amber-800', 'confirme' => 'bg-sky-50 text-sky-800', 'realise' => 'bg-violet-50 text-violet-800', 'solde' => 'bg-green-50 text-green-700', 'annule' => 'bg-gray-100 text-gray-500'];
    $couleursSource = [
        'sur_place' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'telephone' => 'bg-cyan-50 text-cyan-800 border-cyan-200',
        'email' => 'bg-slate-50 text-slate-700 border-slate-200',
        'restaurant' => 'bg-amber-50 text-amber-800 border-amber-200',
        'site_web' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    ];
@endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Banquets</h1>
        <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
            Événements réservés (accueil sur place, téléphone ou restaurant) : vérification des salles, devis, encaissement d'acompte, confirmation et solde.
        </p>
    </div>
    @droit('restaurant.banquets.creer')
        <button type="button" onclick="openBanquetCreateModal()"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 cursor-pointer">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Nouveau devis
        </button>
    @enddroit
</div>

@if(!empty($cashRegisterContext))
    @include('bookings.cash_register.partials.status_banner', ['cashRegisterContext' => $cashRegisterContext])
@endif

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

{{-- Vérificateur de disponibilité des salles en temps réel --}}
<section class="mb-6 rounded-xl border border-secondary/20 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div>
            <h2 class="text-sm font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="search-check" class="w-4 h-4 text-primary/70"></i>
                Vérificateur de disponibilité des salles
            </h2>
            <p class="text-xs text-primary/50">Contrôlez instantanément la disponibilité et la capacité avant d'établir un devis (client au comptoir ou au téléphone).</p>
        </div>
        @if($checkParams['active'] ?? false)
            <a href="{{ route('restaurant.banquets.index', array_filter(['vue' => $vue, 'source' => $sourceFilter ?: null, 'search' => $search ?: null])) }}"
               class="text-xs font-medium text-primary/60 hover:text-primary">Réinitialiser le contrôle</a>
        @endif
    </div>

    <form method="GET" action="{{ route('restaurant.banquets.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end">
        <input type="hidden" name="vue" value="{{ $vue }}">
        @if($sourceFilter)<input type="hidden" name="source" value="{{ $sourceFilter }}">@endif
        @if($search)<input type="hidden" name="search" value="{{ $search }}">@endif

        <label class="block">
            <span class="text-[11px] font-medium text-primary/70">Date souhaitée</span>
            <input type="date" name="check_date" value="{{ $checkParams['check_date'] ?? '' }}" required
                class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-2.5 py-2 text-xs outline-none focus:border-secondary">
        </label>
        <label class="block">
            <span class="text-[11px] font-medium text-primary/70">Début (optionnel)</span>
            <input type="time" name="check_start_time" value="{{ $checkParams['check_start_time'] ?? '' }}"
                class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-2.5 py-2 text-xs outline-none focus:border-secondary">
        </label>
        <label class="block">
            <span class="text-[11px] font-medium text-primary/70">Fin (optionnel)</span>
            <input type="time" name="check_end_time" value="{{ $checkParams['check_end_time'] ?? '' }}"
                class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-2.5 py-2 text-xs outline-none focus:border-secondary">
        </label>
        <label class="block">
            <span class="text-[11px] font-medium text-primary/70">Couverts attendus</span>
            <input type="number" name="check_covers" min="1" placeholder="Ex. 80" value="{{ $checkParams['check_covers'] ?? '' }}"
                class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-2.5 py-2 text-xs outline-none focus:border-secondary">
        </label>
        <label class="block">
            <span class="text-[11px] font-medium text-primary/70">Restaurant</span>
            <select name="check_pos_id" class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-2.5 py-2 text-xs outline-none focus:border-secondary">
                <option value="">Tous les restaurants</option>
                @foreach($restaurants as $r)
                    <option value="{{ $r->id }}" @selected((int) ($checkParams['check_pos_id'] ?? 0) === $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
        </label>
        <div>
            <button type="submit"
                class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-95 cursor-pointer">
                <i data-lucide="calendar-search" class="w-3.5 h-3.5"></i>
                Vérifier les salles
            </button>
        </div>
    </form>

    @if($checkParams['active'] ?? false)
        <div class="mt-4 pt-4 border-t border-secondary/15">
            @if($disponibilites->isEmpty())
                <p class="text-xs text-primary/55">Aucune salle active trouvée pour ce restaurant.</p>
            @else
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($disponibilites as $dispo)
                        @php
                            $space = $dispo['space'];
                            $stat = $dispo['status'];
                            $cardStyle = match ($stat) {
                                'libre' => 'border-emerald-200 bg-emerald-50/50',
                                'capacite_insuffisante' => 'border-amber-200 bg-amber-50/60',
                                default => 'border-red-200 bg-red-50/50',
                            };
                            $badgeStyle = match ($stat) {
                                'libre' => 'bg-emerald-100 text-emerald-800',
                                'capacite_insuffisante' => 'bg-amber-100 text-amber-800',
                                default => 'bg-red-100 text-red-700',
                            };
                            $badgeText = match ($stat) {
                                'libre' => 'Libre',
                                'capacite_insuffisante' => 'Capacité insuffisante',
                                default => 'Occupée',
                            };
                        @endphp
                        <div class="rounded-lg border p-3.5 {{ $cardStyle }} flex flex-col justify-between gap-2.5">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-primary">{{ $space->name }}</p>
                                        <p class="text-[11px] text-primary/60">
                                            {{ $space->pointOfSale?->name ?? 'Salle polyvalente' }}
                                            @if($space->capacity) · Capacité : {{ $space->capacity }} places @endif
                                        </p>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $badgeStyle }}">{{ $badgeText }}</span>
                                </div>

                                @if($stat === 'occupee')
                                    <ul class="mt-2 space-y-1 text-[11px] text-red-800">
                                        @foreach($dispo['conflicts'] as $conflit)
                                            <li>
                                                <a href="{{ route('restaurant.banquets.show', $conflit) }}" class="underline font-medium">
                                                    {{ $conflit->reference }} — {{ $conflit->title }}
                                                </a>
                                                ({{ $conflit->start_time && $conflit->end_time ? $conflit->start_time . '–' . $conflit->end_time : 'Journée' }} · {{ $conflit->libelleStatut() }})
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif($stat === 'capacite_insuffisante')
                                    <p class="mt-2 text-[11px] text-amber-800">
                                        Demande de {{ $checkParams['check_covers'] }} couverts supérieure à la capacité ({{ $space->capacity }} places).
                                    </p>
                                @endif
                            </div>

                            @if($stat === 'libre')
                                @droit('restaurant.banquets.creer')
                                    @php
                                        $prefill = [
                                            'point_of_sale_id' => $dispo['suggested_pos_id'],
                                            'space_id' => $space->id,
                                            'event_date' => $checkParams['check_date'],
                                            'start_time' => $checkParams['check_start_time'],
                                            'end_time' => $checkParams['check_end_time'],
                                            'covers' => $checkParams['check_covers'],
                                        ];
                                    @endphp
                                    <button type="button"
                                        data-prefill="{{ json_encode($prefill, JSON_THROW_ON_ERROR) }}"
                                        onclick="openBanquetCreateModal(JSON.parse(this.dataset.prefill))"
                                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:opacity-95 cursor-pointer">
                                        <i data-lucide="file-plus" class="w-3.5 h-3.5"></i>
                                        Créer un devis sur cette salle
                                    </button>
                                @enddroit
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</section>

{{-- Onglets période + filtres canal & recherche --}}
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        @foreach(['a_venir' => 'À venir', 'passes' => 'Passés', 'tous' => 'Tous'] as $cle => $libelle)
            <a href="{{ route('restaurant.banquets.index', array_filter(['vue' => $cle, 'source' => $sourceFilter ?: null, 'search' => $search ?: null])) }}"
                class="rounded-full px-3 py-1.5 text-xs font-medium {{ $vue === $cle ? 'bg-primary text-white' : 'border border-secondary/30 bg-white text-primary/60 hover:text-primary' }}">{{ $libelle }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('restaurant.banquets.index') }}" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="vue" value="{{ $vue }}">
        <select name="source" onchange="this.form.submit()"
            class="rounded-lg border border-secondary/30 bg-white px-2.5 py-1.5 text-xs text-primary outline-none focus:border-secondary">
            <option value="">Toutes sources</option>
            @foreach($sources as $code => $libelle)
                <option value="{{ $code }}" @selected($sourceFilter === $code)>{{ $libelle }}</option>
            @endforeach
        </select>
        <x-live-search
            name="search"
            target="#banquets-table-container"
            placeholder="Réf., événement, client..."
            width="w-64"
        />
    </form>
</div>

<div id="banquets-table-container">
<x-table :rows="$banquets" empty="Aucun banquet." empty-icon="party-popper" caption="Banquets">
    <x-slot:head>
        <x-table.col>Date</x-table.col>
        <x-table.col>Événement & Client</x-table.col>
        <x-table.col hide="xl">Restaurant · salle</x-table.col>
        <x-table.col align="right" hide="lg">Couverts</x-table.col>
        <x-table.col align="right">Total</x-table.col>
        <x-table.col align="right" hide="2xl">Encaissé</x-table.col>
        <x-table.col>Statut</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($banquets as $banquet)
        <x-table.row :href="route('restaurant.banquets.show', $banquet)" :muted="$banquet->status === 'annule'">
            <x-table.cell nowrap>
                <span class="font-semibold text-primary">{{ $banquet->event_date->format('d/m/Y') }}</span>
                @if($banquet->start_time)<span class="text-primary/50"> {{ $banquet->start_time }}</span>@endif
            </x-table.cell>
            <x-table.cell>
                <div class="flex flex-wrap items-center gap-1.5">
                    <a href="{{ route('restaurant.banquets.show', $banquet) }}" class="font-semibold text-primary hover:underline">{{ $banquet->title }}</a>
                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $couleursSource[$banquet->source] ?? 'bg-gray-50 text-gray-700 border-gray-200' }}">
                        {{ $banquet->libelleSource() }}
                    </span>
                    @if($banquet->booking)
                        <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                            CH {{ $banquet->booking->room?->number ?? $banquet->booking->booking_number }}
                        </span>
                    @endif
                </div>
                <p class="text-[11px] text-primary/45">
                    {{ $banquet->reference }} · {{ $banquet->client_name }}@if($banquet->client_phone) · {{ $banquet->client_phone }}@endif
                    @if(!empty($banquet->menu_items))
                        · Menu ({{ count($banquet->menu_items) }})
                    @endif
                </p>
            </x-table.cell>
            <x-table.cell hide="xl" class="text-primary/70">{{ $banquet->pointOfSale?->name }}@if($banquet->space) · {{ $banquet->space->name }}@endif</x-table.cell>
            <x-table.cell align="right" hide="lg">{{ $banquet->covers }}</x-table.cell>
            <x-table.cell align="right" nowrap class="font-semibold">{{ $fcfa($banquet->total_amount) }}</x-table.cell>
            <x-table.cell align="right" hide="2xl" nowrap>{{ $fcfa($banquet->total_encaisse) }}</x-table.cell>
            <x-table.cell nowrap>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $couleurs[$banquet->status] ?? '' }}">{{ $banquet->libelleStatut() }}</span>
            </x-table.cell>
            <x-table.actions :label="'Actions pour le banquet '.$banquet->reference">
                <x-table.action :href="route('restaurant.banquets.show', $banquet)" icon="eye">Ouvrir</x-table.action>
                <x-table.action :href="route('restaurant.banquets.print', $banquet)" icon="printer">Imprimer devis / reçu</x-table.action>
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
</div>

@droit('restaurant.banquets.creer')
    <x-modal id="banquet-create" title="Nouveau devis de banquet" max-width="max-w-3xl" formAction="{{ route('restaurant.banquets.store') }}">
        @include('restaurant.banquets._champs', ['b' => null])
        <x-slot:footer>
            <button type="button" onclick="document.getElementById('banquet-create').classList.add('hidden')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer le devis</button>
        </x-slot:footer>
    </x-modal>
    <script>
        function openBanquetCreateModal(prefill = null) {
            const modal = document.getElementById('banquet-create');
            if (!modal) return;
            modal.classList.remove('hidden');
            if (prefill) {
                window.dispatchEvent(new CustomEvent('banquet-prefill', {
                    detail: Object.assign({ target: 'create' }, prefill)
                }));
            }
            if (window.lucide) {
                lucide.createIcons();
            }
        }
    </script>
    @if($errors->any() && old('title') !== null && ! request()->route('banquet'))
        <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('banquet-create')?.classList.remove('hidden'));</script>
    @endif
@enddroit
@endsection

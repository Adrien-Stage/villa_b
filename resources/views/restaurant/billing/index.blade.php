@extends('layouts.hotel')

@section('title', 'Facturation restaurant')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Facturation restaurant</h1>
        <p class="text-sm text-primary/50 mt-0.5">Encaissement interne (manager, chef restaurant, caissier)</p>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

{{-- La caisse de celui qui encaisse : tout encaissement y passe. --}}
@droit('restaurant.cash_register.open.creer')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm
        {{ $caisse && $caisse->status === 'open' ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
        <span class="flex items-center gap-2">
            <i data-lucide="calculator" class="w-4 h-4"></i>
            @if(! $caisse)
                Aucune caisse ouverte : ouvrez la vôtre pour encaisser.
            @elseif($caisse->isPendingReview())
                Votre caisse est comptée : elle attend le contrôle de la comptabilité.
            @else
                Caisse {{ $caisse->pointOfSale?->name ?? 'du restaurant' }} ouverte depuis {{ $caisse->opened_at?->format('H:i') }}.
            @endif
        </span>
        @if(! $caisse)
            <a href="{{ route('restaurant.cash_register.open') }}" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white">Ouvrir ma caisse</a>
        @elseif($caisse->status === 'open')
            <a href="{{ route('restaurant.cash_register.close') }}" class="rounded-lg border border-green-300 bg-white px-3 py-1.5 text-xs font-semibold text-green-800">Compter ma caisse</a>
        @endif
    </div>
@enddroit

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-2">
        @php
            $pStatuses = [
                '' => 'Toutes',
                'unpaid' => 'Impayees',
                'paid' => 'Payees',
                'refunded' => 'Remboursees',
            ];
        @endphp

        @foreach($pStatuses as $value => $label)
            <a href="{{ route('restaurant.billing.index', array_merge(request()->except('payment_status','page'), $value ? ['payment_status' => $value] : [])) }}"
                class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ request('payment_status', '') === $value ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ $label }}
            </a>
        @endforeach

        {{-- Clients logés ayant consommé : commandes rattachées à un séjour.
             La réception ne voit qu'elles : le filtre est alors permanent. --}}
        @unless($residentsSeulement ?? false)
            <a href="{{ route('restaurant.billing.index', array_merge(request()->except('residents','page'), request()->boolean('residents') ? [] : ['residents' => 1])) }}"
                class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ request()->boolean('residents') ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                Résidents
            </a>
        @endunless
    </div>

    <form method="GET" action="{{ route('restaurant.billing.index') }}" class="flex items-center gap-2">
        <input type="hidden" name="payment_status" value="{{ request('payment_status') }}">
        @if(request()->boolean('residents'))
            <input type="hidden" name="residents" value="1">
        @endif

        <div class="relative">
            <input type="text"
                id="table-input"
                name="table"
                value="{{ request('table') }}"
                placeholder="Table..."
                autocomplete="off"
                class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-40 transition-all">
            <i data-lucide="hash" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
        </div>
    </form>
</div>

<x-table :rows="$orders" empty="Aucune commande. Elles apparaîtront ici pour encaissement." empty-icon="credit-card" caption="Notes à encaisser">
    <x-slot:head>
        <x-table.col>Commande</x-table.col>
        <x-table.col>Table</x-table.col>
        <x-table.col hide="xl">Statut</x-table.col>
        <x-table.col>Paiement</x-table.col>
        <x-table.col align="right">Total</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($orders as $order)
        <x-table.row :href="route('restaurant.billing.show', $order)">
            <x-table.cell nowrap>
                <a href="{{ route('restaurant.billing.show', $order) }}" class="text-sm font-semibold text-primary hover:underline">#{{ $order->id }}</a>
                <p class="mt-0.5 text-xs text-primary/45">{{ $order->items_count }} article{{ $order->items_count > 1 ? 's' : '' }} · {{ $order->placed_at?->format('d/m H:i') }}</p>
            </x-table.cell>
            <x-table.cell class="text-primary/70">
                {{ $order->table_number ?? '—' }}
                @if($order->booking)
                    {{-- Client logé : chambre et séjournant, pour rapprocher la note du folio. --}}
                    <p class="mt-0.5 text-xs text-primary/45">Ch. {{ $order->booking->room?->number ?? '—' }} · {{ $order->booking->customer?->full_name ?? '—' }}</p>
                @endif
            </x-table.cell>
            <x-table.cell hide="xl" nowrap>
                <span class="inline-flex items-center rounded-full border border-secondary/25 bg-white px-2 py-0.5 text-[11px] font-semibold text-primary">{{ \App\Models\RestaurantCustomerOrder::STATUS_LABELS[$order->status] ?? ucfirst($order->status) }}</span>
            </x-table.cell>
            <x-table.cell nowrap>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $order->payment_status === 'paid' ? 'border border-green-200 bg-green-50 text-green-700' : 'border border-red-200 bg-red-50 text-red-700' }}">{{ $order->payment_status === 'paid' ? 'Payée' : 'À encaisser' }}</span>
                @if($order->payment_method)
                    <p class="mt-0.5 text-[11px] text-primary/45">{{ str_replace('_', ' ', $order->payment_method) }}</p>
                @endif
            </x-table.cell>
            <x-table.cell align="right" nowrap class="font-semibold">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</x-table.cell>
            <x-table.actions :label="'Actions pour la note #'.$order->id">
                <x-table.action :href="route('restaurant.billing.show', $order)" icon="{{ $order->payment_status === 'paid' ? 'eye' : 'wallet' }}">{{ $order->payment_status === 'paid' ? 'Ouvrir' : 'Encaisser' }}</x-table.action>
                @if($order->payment_status === 'paid')
                    @droit('restaurant.billing.receipt')
                        <x-table.action :href="route('restaurant.billing.receipt', $order)" icon="printer" target="_blank">Reçu</x-table.action>
                    @enddroit
                @endif
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>

<script>
let tableTimer;
const tableInput = document.getElementById('table-input');
if (tableInput) {
    tableInput.addEventListener('input', function() {
        clearTimeout(tableTimer);
        tableTimer = setTimeout(() => this.closest('form').submit(), 400);
    });
}
</script>
@endsection


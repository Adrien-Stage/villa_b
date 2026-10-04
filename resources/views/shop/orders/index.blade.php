@extends('layouts.hotel')

@section('title', 'Commandes Boutique')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @php 
        $hasActiveSession = \App\Models\CashRegisterSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->exists(); 
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-primary">Commandes Boutique</h1>
            <p class="text-secondary mt-1">Historique des ventes</p>
        </div>
        <div class="flex items-center gap-3">
            @undroit('shop.cash_register.open', 'shop.cash_register.close', 'shop.orders.creer')
            @if(!$hasActiveSession)
                <a href="{{ route('shop.cash_register.open') }}"
                   class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i data-lucide="lock-open" class="w-4 h-4 inline mr-2"></i> Ouvrir la caisse
                </a>
            @else
                {{-- Celui qui a ouvert sa caisse la ferme lui-même --}}
                <a href="{{ route('shop.cash_register.close') }}"
                   class="bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-lg font-medium transition-colors" title="Fermer la caisse">
                    <i data-lucide="lock" class="w-4 h-4 inline"></i>
                </a>
                <a href="{{ route('shop.orders.create') }}"
                   class="bg-primary hover:bg-primary/90 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i data-lucide="plus" class="w-4 h-4 inline mr-2"></i> Nouvelle commande
                </a>
            @endif
            @endundroit
        </div>
    </div>

    @if ($message = session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
            <i data-lucide="check-circle" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif

    {{-- Barre outils --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-2">
            {{-- Badges statut paiement --}}
            @php
                $paymentStatuses = [
                    '' => 'Tous',
                    'unpaid' => 'Non payée',
                    'paid' => 'Payée',
                    'refunded' => 'Remboursée',
                ];
            @endphp
            @foreach($paymentStatuses as $value => $label)
                <a href="{{ route('shop.orders.index', array_merge(request()->except(['payment_status', 'page']), $value ? ['payment_status' => $value] : [])) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                          {{ request('payment_status', '') === $value
                              ? 'bg-primary text-white'
                              : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Recherche --}}
        <form method="GET" action="{{ route('shop.orders.index') }}" class="relative">
            <input type="hidden" name="payment_status" value="{{ request('payment_status') }}">
            <input type="text"
                   id="search-input"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Numéro commande, client..."
                   autocomplete="off"
                   class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-64 transition-all">
            <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
        </form>
    </div>

    <x-table :rows="$orders" empty="Aucune commande trouvée." empty-icon="shopping-cart" caption="Commandes de la boutique">
        <x-slot:head>
            <x-table.col>Commande</x-table.col>
            <x-table.col>Client</x-table.col>
            <x-table.col align="right">Montant</x-table.col>
            <x-table.col>Paiement</x-table.col>
            <x-table.col hide="lg">Date</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($orders as $order)
            <x-table.row :href="route('shop.orders.show', $order)">
                <x-table.cell nowrap>
                    <a href="{{ route('shop.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_number }}</a>
                    <p class="text-xs text-primary/50">{{ $order->total_items }} article(s)</p>
                </x-table.cell>
                <x-table.cell>
                    <p class="font-medium text-primary">{{ $order->customer_name }}</p>
                    @if($order->customer_phone)<p class="text-xs text-primary/50">{{ $order->customer_phone }}</p>@endif
                </x-table.cell>
                <x-table.cell align="right" nowrap class="font-medium">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</x-table.cell>
                <x-table.cell nowrap>
                    @if($order->payment_status === 'paid')
                        <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">Payée</span>
                    @elseif($order->payment_status === 'unpaid')
                        <span class="rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700">En attente</span>
                    @else
                        <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">Remboursée</span>
                    @endif
                </x-table.cell>
                <x-table.cell hide="lg" nowrap class="text-xs text-primary/60">{{ $order->created_at->locale('fr')->format('d M Y H:i') }}</x-table.cell>
                <x-table.actions :label="'Actions pour la commande '.$order->order_number">
                    <x-table.action :href="route('shop.orders.show', $order)" icon="eye">Ouvrir</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection

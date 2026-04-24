@extends('layouts.hotel')

@section('title', 'Commandes Boutique')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @php 
        $hasActiveSession = \App\Models\CashRegisterSession::where('user_id', auth()->id())
            ->where('tenant_id', auth()->user()->tenant->id)
            ->whereNull('closed_at')
            ->exists(); 
    @endphp

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-primary">Commandes Boutique</h1>
            <p class="text-secondary mt-1">Historique des ventes</p>
        </div>
        <div class="flex items-center gap-3">
            @role('shop_manager','shop_cashier')
            @if(!$hasActiveSession)
                <a href="{{ route('shop.cash_register.open') }}"
                   class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i data-lucide="lock-open" class="w-4 h-4 inline mr-2"></i> Ouvrir la caisse
                </a>
            @else
                <a href="{{ route('shop.cash_register.close') }}"
                   class="bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-lg font-medium transition-colors" title="Fermer la caisse">
                    <i data-lucide="lock" class="w-4 h-4 inline"></i>
                </a>
                <a href="{{ route('shop.orders.create') }}"
                   class="bg-primary hover:bg-primary/90 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                    <i data-lucide="plus" class="w-4 h-4 inline mr-2"></i> Nouvelle commande
                </a>
            @endif
            @endrole
        </div>
    </div>

    @if ($message = session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
            <i data-lucide="check-circle" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif

    {{-- Barre outils --}}
    <div class="flex items-center justify-between gap-4 mb-6">
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

    <!-- Tableau des commandes -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Commande</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Client</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Montant</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Paiement</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Date</th>
                        <th class="px-6 py-4 text-right text-sm font-semibold text-gray-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <p class="font-medium text-primary">{{ $order->order_number }}</p>
                                <p class="text-secondary text-sm">{{ $order->total_items }} article(s)</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-primary">{{ $order->customer_name }}</p>
                                @if ($order->customer_phone)
                                    <p class="text-secondary text-sm">{{ $order->customer_phone }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 font-medium text-primary">
                                {{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-6 py-4">
                                @if ($order->payment_status === 'paid')
                                    <span class="bg-green-50 text-green-700 px-3 py-1 rounded-full text-sm font-medium">Payée</span>
                                @elseif ($order->payment_status === 'unpaid')
                                    <span class="bg-yellow-50 text-yellow-700 px-3 py-1 rounded-full text-sm font-medium">En attente</span>
                                @else
                                    <span class="bg-red-50 text-red-700 px-3 py-1 rounded-full text-sm font-medium">Remboursée</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-secondary text-sm">
                                {{ $order->created_at->locale('fr')->format('d M Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('shop.orders.show', $order) }}"
                                   class="text-primary hover:text-primary/70 transition-colors">
                                    <i data-lucide="eye" class="w-4 h-4 inline"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-secondary">
                                <i data-lucide="shopping-cart" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                                <p>Aucune commande trouvée</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $orders->links() }}
    </div>
</div>
@endsection

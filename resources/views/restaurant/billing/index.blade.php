@extends('layouts.hotel')

@section('title', 'Facturation restaurant')

@section('content')
<div class="flex items-start justify-between mb-6">
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
    </div>

    <form method="GET" action="{{ route('restaurant.billing.index') }}" class="flex items-center gap-2">
        <input type="hidden" name="payment_status" value="{{ request('payment_status') }}">

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

<div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
    @if($orders->isEmpty())
        <div class="py-16 text-center text-primary/35">
            <i data-lucide="credit-card" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
            <p class="text-sm font-medium">Aucune commande</p>
            <p class="text-xs mt-1">Les commandes apparaitront ici pour encaissement.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/20">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Commande</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Table</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Statut</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Paiement</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Total</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($orders as $order)
                        <tr class="hover:bg-accent/10">
                            <td class="px-4 py-3">
                                <a href="{{ route('restaurant.billing.show', $order) }}" class="text-sm font-semibold text-primary hover:underline">
                                    #{{ $order->id }}
                                </a>
                                <p class="text-xs text-primary/45 mt-0.5">
                                    {{ $order->items_count }} item{{ $order->items_count > 1 ? 's' : '' }} · {{ $order->placed_at?->format('d/m H:i') }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-sm text-primary/70">
                                {{ $order->table_number ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white text-primary border border-secondary/25">
                                    {{ \App\Models\RestaurantCustomerOrder::STATUS_LABELS[$order->status] ?? ucfirst($order->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $order->payment_status === 'paid' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                    {{ strtoupper($order->payment_status ?? 'unpaid') }}
                                </span>
                                @if($order->payment_method)
                                    <p class="text-[11px] text-primary/45 mt-0.5">{{ str_replace('_', ' ', $order->payment_method) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-primary">
                                {{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('restaurant.billing.show', $order) }}"
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-4 border-t border-secondary/15">
            {{ $orders->links() }}
        </div>
    @endif
</div>

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


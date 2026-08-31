@extends('layouts.hotel')

@section('title', 'Bons de commande — Économat')

@section('content')
@php
    $statusStyles = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_received' => 'bg-amber-50 text-amber-700', 'received' => 'bg-green-50 text-green-700',
        'cancelled' => 'bg-red-50 text-red-700',
    ];
@endphp
<div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Bons de commande</h1>
            <p class="text-sm text-primary/60 mt-0.5">Commandes aux fournisseurs et suivi des réceptions.</p>
        </div>
        <a href="{{ route('economat.orders.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouveau bon
        </a>
    </div>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        @if($orders->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-primary/40">Aucun bon de commande.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Numéro</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Fournisseur</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Statut</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Total</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Créé</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($orders as $order)
                            <tr class="hover:bg-accent/5 cursor-pointer" onclick="window.location='{{ route('economat.orders.show', $order) }}'">
                                <td class="px-5 py-3 font-mono text-primary">{{ $order->number }}</td>
                                <td class="px-5 py-3 text-primary/70">{{ $order->supplier?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusStyles[$order->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $order->statusLabel() }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ $order->lines_count }}</td>
                                <td class="px-5 py-3 text-right font-medium text-primary">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} F</td>
                                <td class="px-5 py-3 text-xs text-primary/40">{{ $order->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
@endsection

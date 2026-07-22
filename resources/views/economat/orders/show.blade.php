@extends('layouts.hotel')

@section('title', $order->number . ' — Bon de commande')

@section('content')
@php
    $statusStyles = [
        'draft' => 'bg-gray-100 text-gray-600', 'sent' => 'bg-blue-50 text-blue-700',
        'partially_received' => 'bg-amber-50 text-amber-700', 'received' => 'bg-green-50 text-green-700',
        'cancelled' => 'bg-red-50 text-red-700',
    ];
@endphp
<div class="max-w-4xl mx-auto">
    <a href="{{ route('economat.orders.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux bons
    </a>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 mb-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-heading font-semibold text-primary font-mono">{{ $order->number }}</h1>
                <p class="text-sm text-primary/60 mt-1">
                    {{ $order->supplier->name }}
                    @if($order->supplier->email) · {{ $order->supplier->email }}@endif
                </p>
                <p class="text-xs text-primary/40 mt-0.5">
                    Créé le {{ $order->created_at->format('d/m/Y') }} par {{ $order->createdBy?->name ?? '—' }}
                    @if($order->expected_at) · Livraison souhaitée : {{ $order->expected_at->format('d/m/Y') }}@endif
                </p>
            </div>
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold {{ $statusStyles[$order->status] ?? 'bg-gray-100' }}">{{ $order->statusLabel() }}</span>
        </div>

        @if($order->status === 'sent' && $order->send_error)
            <p class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                L'email n'a pas pu être envoyé : {{ $order->send_error }}. Vous pouvez renvoyer le bon.
            </p>
        @elseif($order->sent_at)
            <p class="mt-3 text-xs text-primary/50">Envoyé le {{ $order->sent_at->format('d/m/Y H:i') }} à {{ $order->sent_to_email }}.</p>
        @endif

        {{-- Actions selon le statut --}}
        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-secondary/20">
            @if($order->canBeSent())
                <form method="POST" action="{{ route('economat.orders.send', $order) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">
                        <i data-lucide="send" class="w-4 h-4"></i> Envoyer au fournisseur
                    </button>
                </form>
            @elseif($order->status === 'sent' && $order->send_error)
                <form method="POST" action="{{ route('economat.orders.send', $order) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/10">
                        <i data-lucide="rotate-cw" class="w-4 h-4"></i> Renvoyer l'email
                    </button>
                </form>
            @endif

            @if($order->canBeCancelled())
                <form method="POST" action="{{ route('economat.orders.cancel', $order) }}" onsubmit="return confirm('Annuler ce bon ?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 border border-red-200 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50">Annuler le bon</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Lignes + réception --}}
    <form method="POST" action="{{ route('economat.orders.receive', $order) }}">
        @csrf
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-secondary/20">
                <h2 class="text-sm font-semibold text-primary">Articles</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Article</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Commandé</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Déjà reçu</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">P.U.</th>
                            @if($order->canBeReceived())
                                <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Reçu maintenant</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($order->lines as $line)
                            <tr>
                                <td class="px-5 py-3 text-primary">{{ $line->item?->name ?? '—' }} <span class="text-primary/40 text-xs">({{ $line->item?->unit }})</span></td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }}</td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ rtrim(rtrim(number_format($line->quantity_received, 3, ',', ' '), '0'), ',') }}</td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ number_format($line->unit_price / 100, 0, ',', ' ') }}</td>
                                @if($order->canBeReceived())
                                    <td class="px-5 py-3 text-right">
                                        @if($line->outstanding() > 0)
                                            <input type="number" step="0.001" min="0" max="{{ $line->outstanding() }}" name="received[{{ $line->id }}]"
                                                placeholder="{{ rtrim(rtrim(number_format($line->outstanding(), 3, ',', ' '), '0'), ',') }}"
                                                class="w-24 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary text-right">
                                        @else
                                            <span class="text-green-600 text-xs">Soldé</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50">
                            <td colspan="{{ $order->canBeReceived() ? 4 : 3 }}" class="px-5 py-3 text-right font-semibold text-primary">Total</td>
                            <td class="px-5 py-3 text-right font-bold text-primary">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} F</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if($order->canBeReceived())
                <div class="px-5 py-3 border-t border-secondary/20 bg-gray-50 flex justify-between items-center">
                    <p class="text-xs text-primary/50">Saisissez les quantités réellement livrées. L'entrée en stock met à jour le coût moyen.</p>
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">
                        <i data-lucide="package-check" class="w-4 h-4"></i> Valider la réception
                    </button>
                </div>
            @endif
        </div>
    </form>

    @if($order->notes)
        <p class="text-sm text-primary/60 mt-4 bg-white border border-secondary/20 rounded-xl p-4"><strong>Note :</strong> {{ $order->notes }}</p>
    @endif
</div>
@endsection

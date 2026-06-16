@extends('layouts.hotel')

@section('title', 'Détail commande')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:hidden">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-primary">{{ $order->order_number }}</h1>
            <p class="text-secondary mt-1">{{ $order->created_at->locale('fr')->format('d F Y H:i') }}</p>
        </div>
        <a href="{{ route('shop.orders.index') }}" class="text-primary hover:text-primary/70 transition-colors">
            <i data-lucide="arrow-left" class="w-5 h-5 inline mr-2"></i> Retour
        </a>
    </div>

    @if ($message = session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
            <i data-lucide="check-circle" class="w-5 h-5 inline mr-2"></i> {{ $message }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Détails -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Info client -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-primary mb-4">Client</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-secondary text-sm">Nom</p>
                        <p class="font-medium text-primary">{{ $order->customer_name ?: 'Client de passage' }}</p>
                    </div>
                    @if ($order->customer_phone)
                        <div>
                            <p class="text-secondary text-sm">Téléphone</p>
                            <p class="font-medium text-primary">{{ $order->customer_phone }}</p>
                        </div>
                    @endif
                    @if ($order->booking)
                        <div>
                            <p class="text-secondary text-sm">Réservation</p>
                            <p class="font-medium text-primary">
                                <a href="{{ route('bookings.show', $order->booking) }}" class="hover:text-primary/70">
                                    {{ $order->booking->customer?->first_name }} {{ $order->booking->customer?->last_name }} - Chambre {{ $order->booking->room?->number ?? '—' }}
                                </a>
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Articles -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-primary mb-4">Articles</h2>
                <div class="space-y-3">
                    @foreach ($order->items as $item)
                        <div class="flex justify-between py-3 border-b border-gray-200 last:border-b-0">
                            <div>
                                <p class="font-medium text-primary">{{ $item->product->name }}</p>
                                <p class="text-secondary text-sm">{{ $item->quantity }} × {{ number_format($item->unit_price / 100, 0, ',', ' ') }} FCFA</p>
                            </div>
                            <p class="font-semibold text-primary">
                                {{ number_format($item->item_total / 100, 0, ',', ' ') }} FCFA
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Notes -->
            @if ($order->notes)
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-primary mb-4">Notes</h2>
                    <p class="text-secondary">{{ $order->notes }}</p>
                </div>
            @endif
        </div>

        <!-- Résumé & Actions -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm p-6 sticky top-6 space-y-6">
                <!-- Résumé -->
                <div>
                    <h2 class="text-lg font-semibold text-primary mb-4">Résumé</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between text-secondary">
                            <span>Articles :</span>
                            <span>{{ $order->total_items }}</span>
                        </div>
                        <div class="flex justify-between text-secondary">
                            <span>Sous-total :</span>
                            <span>{{ number_format($order->subtotal / 100, 0, ',', ' ') }} FCFA</span>
                        </div>
                        @if($order->tax_amount > 0)
                        <div class="flex justify-between text-secondary">
                            <span>TVA :</span>
                            <span>{{ number_format($order->tax_amount / 100, 0, ',', ' ') }} FCFA</span>
                        </div>
                        @endif
                        <div class="border-t border-gray-200 pt-3 flex justify-between font-semibold">
                            <span class="text-primary">Total :</span>
                            <span class="text-lg text-primary">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</span>
                        </div>
                    </div>
                </div>

                <!-- Statut paiement -->
                <div class="border-t border-gray-200 pt-6">
                    <p class="text-secondary text-sm mb-2">Statut paiement</p>
                    @if ($order->payment_status === 'paid')
                        <span class="bg-green-50 text-green-700 px-4 py-2 rounded-full text-sm font-medium inline-block">Payée</span>
                        @if ($order->paid_at)
                            <p class="text-secondary text-sm mt-2">{{ $order->paid_at->locale('fr')->format('d F Y H:i') }}</p>
                        @endif
                    @elseif ($order->payment_status === 'unpaid')
                        <span class="bg-yellow-50 text-yellow-700 px-4 py-2 rounded-full text-sm font-medium inline-block">En attente</span>
                    @else
                        <span class="bg-red-50 text-red-700 px-4 py-2 rounded-full text-sm font-medium inline-block">Remboursée</span>
                    @endif
                </div>

                <!-- Méthode paiement -->
                @if ($order->payment_method)
                    <div>
                        <p class="text-secondary text-sm mb-2">Méthode</p>
                        <p class="font-medium text-primary">
                            @switch($order->payment_method)
                                @case('cash') Espèces @break
                                @case('mobile_money') Mobile Money @break
                                @case('card') Carte bancaire @break
                                @case('room_charge') Sur chambre @break
                                @default Autre
                            @endswitch
                        </p>
                    </div>
                @endif

                <!-- Actions -->
                <div class="border-t border-gray-200 pt-6 space-y-3">
                    @if ($order->payment_status === 'unpaid')
                        <form action="{{ route('shop.orders.paid', $order) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                <i data-lucide="check" class="w-4 h-4 inline mr-2"></i> Marquer payée
                            </button>
                        </form>
                    @endif

                    @if ($order->payment_status === 'paid')
                        <form action="{{ route('shop.orders.refund', $order) }}" method="POST" onsubmit="return confirm('Confirmer le remboursement ?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                <i data-lucide="undo" class="w-4 h-4 inline mr-2"></i> Rembourser
                            </button>
                        </form>
                    @endif

                    <button onclick="window.print()" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i data-lucide="printer" class="w-4 h-4 inline mr-2"></i> Imprimer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reçu d'Impression (format facture A4) -->
<div class="hidden print:block bg-white w-full h-full" id="invoice-print">
    {{-- En-tête facture --}}
    <div class="px-8 py-6 border-b border-secondary/10">
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 rounded-full overflow-hidden border border-secondary/20 flex-shrink-0">
                        <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h2 class="font-heading text-xl font-bold text-primary">Boutique</h2>
                        <p class="text-xs text-primary/50">Villa Boutanga</p>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-1">Ticket N°</p>
                <p class="text-sm font-bold text-primary">{{ current(explode('-', $order->order_number)) }}</p>
            </div>
        </div>
    </div>

    {{-- Infos client --}}
    <div class="px-8 py-5 border-b border-secondary/10 grid grid-cols-2 gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Acheteur</p>
            <p class="text-sm font-medium text-primary">{{ $order->customer_name ?: 'Client de passage' }}</p>
            @if ($order->customer_phone)
                <p class="text-xs text-primary/50">{{ $order->customer_phone }}</p>
            @endif
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Informations</p>
            @if ($order->booking)
                <p class="text-xs text-primary/70">
                    Hôte en séjour — Chambre {{ $order->booking->room?->number ?? '—' }}
                </p>
            @endif
            <p class="text-xs text-primary/70">
                Date: {{ $order->created_at->format('d/m/Y H:i') }}
            </p>
            <p class="text-xs text-primary/70">
                Vendeur: {{ $order->session->user->name ?? 'Staff' }}
            </p>
        </div>
    </div>

    {{-- Lignes de facturation --}}
    <div class="px-8 py-4">
        <div class="grid grid-cols-12 gap-4 py-2 border-b border-secondary/20 mb-1">
            <div class="col-span-6 text-xs font-semibold uppercase tracking-widest text-primary/40">Article</div>
            <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40 text-center">Qté</div>
            <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">{{ $order->tax_amount > 0 ? 'P.U. HT' : 'P.U.' }}</div>
            <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">Total</div>
        </div>

        @foreach ($order->items as $item)
            <div class="grid grid-cols-12 gap-4 py-3 border-b border-secondary/10 items-center">
                <div class="col-span-6">
                    <p class="text-sm text-primary">{{ $item->product->name }}</p>
                    <p class="text-xs text-primary/40">{{ $item->product->category->name ?? 'Article' }}</p>
                </div>
                <div class="col-span-1 text-xs text-primary/70 text-center">
                    {{ $item->quantity }}
                </div>
                <div class="col-span-2 text-xs text-primary/70 text-right">
                    {{ number_format($item->unit_price / 100, 0, ',', ' ') }} F
                </div>
                <div class="col-span-3 text-sm font-medium text-primary text-right">
                    {{ number_format($item->item_total / 100, 0, ',', ' ') }} F
                </div>
            </div>
        @endforeach
    </div>

    {{-- Totaux --}}
    <div class="px-8 py-5 border-t border-secondary/20 bg-accent/10">
        <div class="ml-auto w-64 space-y-2">
            <div class="flex justify-between text-xs text-primary/60">
                <span>{{ $order->tax_amount > 0 ? 'Sous-total HT' : 'Sous-total' }}</span>
                <span>{{ number_format($order->subtotal / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($order->tax_amount > 0)
            <div class="flex justify-between text-xs text-primary/60">
                <span>TVA (19,25%)</span>
                <span>{{ number_format($order->tax_amount / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="flex justify-between text-sm font-semibold text-primary pt-2 border-t border-secondary/20">
                <span>{{ $order->tax_amount > 0 ? 'Total TTC' : 'Total' }}</span>
                <span>{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($order->payment_status === 'paid')
                <div class="flex justify-between text-xs text-green-600 mt-2 font-medium">
                    <span class="flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Payé</span>
                    <span>{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</span>
                </div>
            @endif
        </div>
    </div>
    
    {{-- Mentions légales --}}
    <div class="px-8 py-4 border-t border-secondary/10 bg-accent/5">
        <p class="text-xs text-primary/40 text-center">
            {{ $order->tax_amount > 0 ? 'TVA 19,25% incluse — Facture Boutique' : 'Facture Boutique' }}
        </p>
        <p class="text-xs text-primary/30 text-center mt-1">
            Villa Boutanga · Bafoussam, Cameroun · Merci de votre visite
        </p>
    </div>
</div>

<style>
    @media print {
        body {
            background-color: white !important;
            margin: 0;
            padding: 0;
        }
    }
</style>
@endsection

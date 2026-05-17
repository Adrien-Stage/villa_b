<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recu restaurant #{{ $order->id }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            #invoice-print { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>

<body class="min-h-screen bg-accent/25 font-body text-primary py-8">
    <div class="max-w-3xl mx-auto px-4">
        <div class="no-print flex items-center justify-between gap-2 mb-6">
            <div>
                <a href="{{ route('restaurant.billing.show', $order) }}" class="inline-flex items-center gap-1 hover:text-primary transition-colors text-primary/50 text-sm font-medium mb-2 w-fit">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Retour à la facturation
                </a>
                <h1 class="font-heading text-2xl font-semibold text-primary">Impression du ticket</h1>
            </div>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Lancer l'impression
            </button>
        </div>

        <div class="rounded-xl border border-secondary/15 bg-white shadow-sm overflow-hidden window-print" id="invoice-print">
            {{-- En-tête facture --}}
            <div class="px-8 py-6 border-b border-secondary/10">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-full overflow-hidden border border-secondary/20 flex-shrink-0">
                                <img src="{{ asset('images/logo.png') }}"
                                    alt="Logo"
                                    class="w-full h-full object-cover">
                            </div>
                            <div>
                                <h2 class="font-heading text-xl font-bold text-primary">Restaurant</h2>
                                <p class="text-xs text-primary/50">Villa Boutanga</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-1">Ticket N°</p>
                        <p class="text-sm font-bold text-primary">{{ $order->id }}</p>
                    </div>
                </div>
            </div>

            {{-- Infos commande --}}
            <div class="px-8 py-5 border-b border-secondary/10 grid grid-cols-2 gap-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Informations</p>
                    <p class="text-sm font-medium text-primary">Table {{ $order->table_number }}</p>
                    @if ($order->booking)
                        @php
                            $residentName = $order->booking->customer?->full_name
                                ?? $order->booking->guests?->firstWhere('is_primary_guest', true)?->full_name
                                ?? $order->booking->guests?->first()?->full_name
                                ?? $order->customer_name
                                ?? 'Client';
                        @endphp
                        <p class="text-xs text-primary/70">
                            {{ $residentName }}@if($order->booking->room?->number) - chambre {{ $order->booking->room->number }}@endif
                        </p>
                    @else
                        <p class="text-xs text-primary/70">
                            {{ $order->customer_name ?? 'Client' }}
                        </p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Service</p>
                    <p class="text-xs text-primary/70">
                        Date: {{ $order->placed_at?->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>

            {{-- Lignes de facturation --}}
            <div class="px-8 py-4">
                <div class="grid grid-cols-12 gap-4 py-2 border-b border-secondary/20 mb-1">
                    <div class="col-span-6 text-xs font-semibold uppercase tracking-widest text-primary/40">Plat / Boisson</div>
                    <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40 text-center">Qté</div>
                    <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">P.U. HT</div>
                    <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">Total</div>
                </div>

                @foreach($order->items as $line)
                    <div class="grid grid-cols-12 gap-4 py-3 border-b border-secondary/10 items-center">
                        <div class="col-span-6">
                            <p class="text-sm text-primary">{{ $line->item_name }}</p>
                        </div>
                        <div class="col-span-1 text-xs text-primary/70 text-center">
                            {{ (int) $line->quantity }}
                        </div>
                        <div class="col-span-2 text-xs text-primary/70 text-right">
                            {{ number_format($line->unit_price / 100, 0, ',', ' ') }} F
                        </div>
                        <div class="col-span-3 text-sm font-medium text-primary text-right">
                            {{ number_format($line->total_price / 100, 0, ',', ' ') }} F
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Totaux --}}
            <div class="px-8 py-5 border-t border-secondary/20 bg-accent/10">
                <div class="ml-auto w-64 space-y-2">
                    <div class="flex justify-between text-sm font-semibold text-primary">
                        <span>Total TTC</span>
                        <span>{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</span>
                    </div>
                    @if($order->payment_status === 'paid')
                        <div class="flex justify-between text-xs text-green-600 mt-2 font-medium">
                            <span class="flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Payé le {{ $order->paid_at?->format('d/m/y') }}</span>
                            <span>{{ number_format($order->amount_paid / 100, 0, ',', ' ') }} FCFA</span>
                        </div>
                        <div class="flex justify-between text-[11px] text-primary/50 mt-1">
                            <span>Méthode</span>
                            <span class="uppercase">{{ str_replace('_', ' ', $order->payment_method) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Mentions légales --}}
            <div class="px-8 py-4 border-t border-secondary/10 bg-accent/5">
                <p class="text-xs text-primary/40 text-center">
                    TVA 19,25% incluse — Facture de Service
                </p>
                <p class="text-xs text-primary/30 text-center mt-1">
                    Villa Boutanga · Bafoussam, Cameroun · Merci et à bientôt
                </p>
            </div>
        </div>
    </div>

    <script>
    if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu POS Réception #{{ $sale->sale_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page {
            size: auto;
            margin: 0mm;
        }

        @media print {
            .no-print, header, aside, nav, button { display: none !important; }
            html, body { background: white !important; padding: 0 !important; margin: 0 !important; height: auto !important; }
            #receipt-print {
                border: none !important;
                box-shadow: none !important;
                max-width: 100% !important;
                width: 100% !important;
                padding: 8mm 12mm !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-accent/25 font-body text-primary py-8">
    <div class="max-w-2xl mx-auto px-4">
        
        {{-- Barre d'actions supérieure (non imprimable) --}}
        <div class="no-print flex items-center justify-between gap-3 mb-6">
            <div>
                <a href="{{ route('reception.pos.index') }}" class="inline-flex items-center gap-1.5 hover:text-primary transition-colors text-primary/60 text-sm font-medium mb-1">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Retour au POS Réception
                </a>
                <h1 class="font-heading text-xl font-bold text-primary">Reçu de vente validé</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('reception.pos.index') }}" class="px-4 py-2 bg-secondary/10 hover:bg-secondary/20 text-primary text-xs font-bold rounded-xl transition-colors">
                    Nouvelle vente
                </a>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Imprimer le reçu
                </button>
            </div>
        </div>

        {{-- Le Reçu / Facturette --}}
        <div class="rounded-2xl border border-secondary/15 bg-white shadow-sm overflow-hidden" id="receipt-print">
            
            @php
                $tenant = $sale->tenant ?? \App\Models\Tenant::first();
                $tenantName = $tenant?->name ?? 'Villa Boutanga';
                $tenantLogo = !empty($tenant->settings['logo']) ? asset('storage/' . $tenant->settings['logo']) : asset('images/logo.png');
                $tenantAddress = $tenant?->address ?? 'Bafoussam, Cameroun';
                $tenantPhone = $tenant?->phone ?? '';
            @endphp

            {{-- En-tête --}}
            <div class="p-6 border-b border-secondary/10">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-lg overflow-hidden border border-secondary/20 flex-shrink-0">
                            @if(!empty($tenant->settings['logo']))
                                <img src="{{ $tenantLogo }}" alt="Logo" class="w-full h-full object-cover">
                            @else
                                <i data-lucide="building" class="w-6 h-6"></i>
                            @endif
                        </div>
                        <div>
                            <h2 class="font-heading text-lg font-bold text-primary">{{ $tenantName }}</h2>
                            <p class="text-xs text-primary/60 font-medium">Réception & Hébergement</p>
                            <p class="text-[11px] text-primary/40">{{ $tenantAddress }}</p>
                        </div>
                    </div>

                    <div class="text-right">
                        <span class="inline-block text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full {{ $sale->payment_status === 'charged_to_room' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                            {{ $sale->paymentStatusLabel() }}
                        </span>
                        <p class="text-xs font-mono font-bold text-primary mt-1">{{ $sale->sale_number }}</p>
                        <p class="text-[11px] text-primary/50">{{ $sale->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>

            {{-- Coordonnées Client & Contexte --}}
            <div class="p-6 bg-accent/15 border-b border-secondary/10 text-xs">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-primary/50 block mb-0.5">Bénéficiaire</span>
                        <p class="font-bold text-primary text-sm">{{ $sale->customer_name }}</p>
                        @if($sale->room_number)
                            <p class="text-blue-900 font-semibold mt-0.5">
                                <i data-lucide="bed" class="w-3.5 h-3.5 inline mr-1"></i>
                                Chambre {{ $sale->room_number }} (Résident)
                            </p>
                        @endif
                        @if($sale->customer_phone)
                            <p class="text-primary/60 mt-0.5">{{ $sale->customer_phone }}</p>
                        @endif
                    </div>

                    <div class="text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-primary/50 block mb-0.5">Règlement</span>
                        <p class="font-bold text-primary">{{ $sale->paymentMethodLabel() }}</p>
                        <p class="text-[11px] text-primary/60 mt-0.5">Opérateur : {{ $sale->user?->name ?? 'Réception' }}</p>
                        @if($sale->cash_register_session_id)
                            <p class="text-[10px] text-primary/40">Caisse session #{{ $sale->cash_register_session_id }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Lignes du Panier --}}
            <div class="p-6">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-secondary/20 text-[10px] font-bold uppercase tracking-wider text-primary/50">
                            <th class="py-2">Désignation</th>
                            <th class="py-2 text-center">Qté</th>
                            <th class="py-2 text-right">Prix Unitaire</th>
                            <th class="py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($sale->items as $item)
                            <tr>
                                <td class="py-3 pr-2">
                                    <p class="font-bold text-primary">{{ $item->name }}</p>
                                    <span class="text-[10px] text-primary/50">{{ $item->categoryLabel() }}</span>
                                </td>
                                <td class="py-3 text-center font-medium text-primary">
                                    {{ $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}
                                </td>
                                <td class="py-3 text-right text-primary/70">
                                    {{ number_format($item->unit_price / 100, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="py-3 text-right font-bold text-primary">
                                    {{ number_format($item->total_price / 100, 0, ',', ' ') }} FCFA
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totaux --}}
            <div class="p-6 bg-accent/10 border-t border-secondary/10">
                <div class="max-w-xs ml-auto space-y-1.5 text-xs">
                    <div class="flex justify-between text-primary/70">
                        <span>Sous-total HT</span>
                        <span class="font-medium">{{ $sale->formattedSubtotal() }}</span>
                    </div>
                    <div class="flex justify-between text-primary/70">
                        <span>TVA (19,25% comprise)</span>
                        <span class="font-medium">{{ $sale->formattedTax() }}</span>
                    </div>
                    <div class="flex justify-between items-baseline pt-2 border-t border-secondary/20 text-sm font-bold text-primary">
                        <span>TOTAL TTC</span>
                        <span class="text-lg font-extrabold font-heading">{{ $sale->formattedTotal() }}</span>
                    </div>
                </div>
            </div>

            {{-- Pied de page / Mentions --}}
            <div class="p-6 border-t border-secondary/10 text-center text-xs text-primary/60">
                @if($sale->payment_status === 'charged_to_room')
                    <p class="font-medium text-blue-900 bg-blue-50 py-2 px-3 rounded-lg inline-block mb-3">
                        Cette somme a été débitée sur le folio de la chambre {{ $sale->room_number }}.
                    </p>
                @endif
                @if($sale->notes)
                    <p class="italic text-[11px] mb-2 text-primary/50">« {{ $sale->notes }} »</p>
                @endif
                <p class="text-[11px] text-primary/40">Merci de votre confiance et excellent séjour à {{ $tenantName }}.</p>
            </div>

        </div>

    </div>
</body>
</html>

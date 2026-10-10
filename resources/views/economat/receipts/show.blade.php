@extends('layouts.hotel')

@section('title', $receipt->number . ' — Bon d\'entrée en stock')

@section('content')
<style>
@import url('https://fonts.googleapis.com/css2?family=Qwigley&display=swap');
@font-face {
    font-family: 'Qwigley';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('{{ asset('fonts/Qwigley-Regular.woff2') }}') format('woff2'), url('{{ asset('fonts/Qwigley-Regular.ttf') }}') format('truetype');
}
.font-signature {
    font-family: 'Qwigley', cursive, 'Brush Script MT', sans-serif;
}
</style>

<div class="max-w-5xl mx-auto space-y-6">
    {{-- Barre de navigation supérieure --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('economat.receipts.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à la liste des bons d'entrée
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('economat.receipts.print', $receipt) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-4 h-4 text-primary/70"></i> Imprimer le Bon d'Entrée (PDF / Papier)
            </a>
            @if(\Illuminate\Support\Facades\Route::has('accounting.supplier-invoices.create'))
                <a href="{{ route('accounting.supplier-invoices.create', ['bon' => $receipt->purchase_order_id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="file-text" class="w-4 h-4"></i> Saisir la facture fournisseur
                </a>
            @endif
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- En-tête du Bon d'Entrée & Statut --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <span class="text-xs font-mono font-semibold text-primary/50 uppercase">Bon d'entrée officiel en magasin</span>
                <div class="flex items-center gap-3 mt-1">
                    <h1 class="text-2xl font-heading font-bold text-primary font-mono tracking-tight">{{ $receipt->number }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        {{ $receipt->statusLabel() }}
                    </span>
                </div>
                <p class="text-xs text-primary/50 mt-1">
                    Enregistré le <span class="font-medium text-primary/80">{{ $receipt->received_at->format('d/m/Y à H:i') }}</span>
                    par <span class="font-medium text-primary/80">{{ $receipt->receivedBy?->name ?? 'Économe' }}</span>
                </p>
            </div>

            <div class="text-right sm:border-l sm:border-secondary/15 sm:pl-6">
                <span class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Valeur admise en stock</span>
                <div class="text-2xl font-mono font-bold text-emerald-700 mt-0.5">
                    {{ number_format($receipt->total_amount / 100, 0, ',', ' ') }} <span class="text-sm font-sans font-normal text-emerald-600">FCFA</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Cartes jumelées : Données Fournisseur/Livraison & Signature Économe Réceptionnaire --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Données Fournisseur & Bordereau --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-secondary/10">
                <div class="flex items-center gap-2">
                    <i data-lucide="truck" class="w-4 h-4 text-primary/70"></i>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-primary/70">Livraison Fournisseur</h2>
                </div>
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                    <i data-lucide="check" class="w-3 h-3"></i> Réceptionné
                </span>
            </div>

            <div class="space-y-1.5 text-xs text-primary/80">
                <div>
                    <span class="text-primary/50">Fournisseur :</span>
                    <strong class="text-primary text-sm">{{ $receipt->supplier?->name }}</strong>
                </div>
                @if($receipt->delivery_note_number)
                    <div>
                        <span class="text-primary/50">N° BL Livreur :</span>
                        <strong class="font-mono text-primary">{{ $receipt->delivery_note_number }}</strong>
                    </div>
                @endif
                <div>
                    <span class="text-primary/50">Bon de commande d'origine :</span>
                    @if($receipt->purchaseOrder)
                        <a href="{{ route('economat.orders.show', $receipt->purchaseOrder) }}" class="font-mono font-bold text-primary hover:underline ml-1">
                            {{ $receipt->purchaseOrder->number }}
                        </a>
                        @if($receipt->purchaseOrder->isRegularisation())
                            <span class="block text-[11px] text-sky-800 mt-0.5">Réception directe, sans commande préalable : bon de régularisation.</span>
                        @endif
                    @else
                        <span class="text-primary/40">—</span>
                    @endif
                </div>
                @if($receipt->notes)
                    <div class="pt-2 text-primary/70 italic border-t border-secondary/10">
                        « {{ $receipt->notes }} »
                    </div>
                @endif
            </div>
        </div>

        {{-- Carte Signature Numérique de l'Économe --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between pb-2 border-b border-secondary/10">
                <div class="flex items-center gap-2">
                    <i data-lucide="pen-tool" class="w-4 h-4 text-emerald-700"></i>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-primary/70">Signature Économe</h2>
                </div>
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
                    <i data-lucide="shield-check" class="w-3 h-3"></i> Certifiée
                </span>
            </div>

            <div class="bg-gray-50/80 border border-dashed border-secondary/30 rounded-lg p-3 text-center my-auto">
                <div class="text-[10px] uppercase font-semibold text-primary/50 tracking-wider mb-1">Entrée en stock certifiée conforme</div>
                <div class="font-signature text-4xl text-primary font-normal leading-tight py-1 select-none">
                    {{ $receipt->receiverSignature() ?? ($receipt->receivedBy?->name ?? 'Économe') }}
                </div>
                <div class="text-[11px] text-primary/70 font-medium">
                    {{ $receipt->receivedBy?->name ?? 'Économe Réceptionnaire' }}
                </div>
                <div class="text-[10px] text-primary/40 mt-0.5">
                    Validé le {{ $receipt->received_at->format('d/m/Y à H:i') }}
                </div>
            </div>

            <p class="text-[11px] text-primary/50 mt-2 italic">
                Ce bon d'entrée engage l'incorporation physique des marchandises admises sous la responsabilité de l'économe signataire.
            </p>
        </div>
    </div>

    {{-- Alerte litige éventuel --}}
    @if($receipt->hasRejections())
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 space-y-1 shadow-sm">
            <div class="font-bold flex items-center gap-2 text-sm">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
                Litige de livraison constaté : Marchandises refusées
            </div>
            <p>
                <strong>{{ rtrim(rtrim(number_format($receipt->totalRejectedQuantity(), 3, ',', ' '), '0'), ',') }}</strong> unité(s) au total ont été rejetées lors du déchargement pour avarie ou non-conformité. Ces quantités ne sont pas intégrées aux stocks du magasin central.
            </p>
        </div>
    @endif

    {{-- Tableau contradictoire complet des articles --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-primary">Articles réceptionnés & Contrôle contradictoire</h2>
            <span class="text-xs text-primary/50">{{ $receipt->lines->count() }} article(s) pointé(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50/40 text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10">
                    <tr>
                        <th class="px-5 py-3 text-left">Article</th>
                        <th class="px-3 py-3 text-right">Commandé</th>
                        <th class="px-3 py-3 text-right">Livré (BL)</th>
                        <th class="px-3 py-3 text-right text-emerald-800">Accepté (Stock)</th>
                        <th class="px-3 py-3 text-right text-rose-800">Refusé</th>
                        <th class="px-3 py-3 text-right">P.U.</th>
                        <th class="px-5 py-3 text-right">Total admis</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($receipt->lines as $line)
                        <tr class="hover:bg-gray-50/40">
                            <td class="px-5 py-3">
                                <div class="font-medium text-primary">{{ $line->item?->name ?? '—' }}</div>
                                <div class="text-[11px] text-primary/40 flex items-center gap-1 mt-0.5">
                                    @if($line->item?->code)
                                        <span class="font-mono">{{ $line->item->code }}</span> ·
                                    @endif
                                    <span>Unité : {{ $line->item?->unit }}</span>
                                    @if($line->item?->category)
                                        · <span>{{ $line->item->category->name }}</span>
                                    @endif
                                </div>
                                @if($line->quantity_rejected > 0)
                                    <div class="text-xs text-rose-700 mt-1 font-medium flex items-center gap-1">
                                        <i data-lucide="alert-circle" class="w-3 h-3 text-rose-600"></i>
                                        <span>Refus : <strong>{{ $line->rejectionReasonLabel() }}</strong></span>
                                        @if($line->notes) <span class="text-primary/60">({{ $line->notes }})</span>@endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-primary/60">
                                {{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-primary/70">
                                {{ rtrim(rtrim(number_format($line->quantity_delivered, 3, ',', ' '), '0'), ',') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono font-bold text-emerald-800">
                                {{ rtrim(rtrim(number_format($line->quantity_accepted, 3, ',', ' '), '0'), ',') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono font-bold {{ $line->quantity_rejected > 0 ? 'text-rose-600' : 'text-primary/40' }}">
                                {{ rtrim(rtrim(number_format($line->quantity_rejected, 3, ',', ' '), '0'), ',') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-primary/70">
                                {{ number_format($line->unit_cost / 100, 0, ',', ' ') }} F
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-bold text-emerald-800">
                                {{ number_format($line->total_cost / 100, 0, ',', ' ') }} F
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50/80 border-t border-secondary/15 font-semibold text-primary">
                    <tr>
                        <td colspan="6" class="px-5 py-3.5 text-right font-bold text-primary">Valeur totale acceptée entrée en stock :</td>
                        <td class="px-5 py-3.5 text-right font-mono text-lg font-bold text-emerald-800">
                            {{ number_format($receipt->total_amount / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

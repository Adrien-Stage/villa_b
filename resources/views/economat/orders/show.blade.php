@extends('layouts.hotel')

@section('title', $order->number . ' — Bon de commande')

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
@php
    $statusStyles = [
        'draft' => 'bg-gray-100 text-gray-600 border-gray-200',
        'sent' => 'bg-blue-50 text-blue-700 border-blue-200',
        'partially_received' => 'bg-amber-50 text-amber-700 border-amber-200',
        'received' => 'bg-green-50 text-green-700 border-green-200',
        'cancelled' => 'bg-red-50 text-red-700 border-red-200',
    ];
@endphp
<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between">
        <a href="{{ route('economat.orders.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à la liste des bons de commande
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('economat.orders.print', $order) }}" target="_blank" class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5 text-primary/70"></i> Imprimer le Bon officiel (PDF / Papier)
            </a>
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- En-tête du bon & Statut --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-heading font-bold text-primary font-mono tracking-tight">{{ $order->number }}</h1>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {{ $statusStyles[$order->status] ?? 'bg-gray-100 text-gray-700 border-gray-200' }}">
                        {{ $order->statusLabel() }}
                    </span>
                </div>
                <p class="text-xs text-primary/50 mt-1">
                    Émis le <span class="font-medium text-primary/70">{{ $order->created_at->format('d/m/Y à H:i') }}</span>
                    par <span class="font-medium text-primary/80">{{ $order->createdBy?->name ?? 'Économe' }}</span>
                    @if($order->expected_at)
                        · Date limite souhaitée de livraison : <span class="font-medium text-primary/80">{{ $order->expected_at->format('d/m/Y') }}</span>
                    @endif
                </p>
            </div>

            <div class="text-right sm:border-l sm:border-secondary/15 sm:pl-6">
                <div class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Montant total engagé</div>
                <div class="text-2xl font-mono font-bold text-primary mt-0.5">
                    {{ number_format($order->total_amount / 100, 0, ',', ' ') }} <span class="text-sm font-sans font-normal text-primary/60">FCFA</span>
                </div>
            </div>
        </div>

        {{-- Alerte d'envoi ou confirmation --}}
        @if($order->send_error)
            {{-- L'échec reste dit même après la livraison : le bon n'a jamais été reçu par email. --}}
            <div class="mt-4 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                <div>
                    <strong>Échec de distribution par email :</strong> {{ $order->send_error }}.
                    @if($order->status === 'sent') Vous pouvez réitérer l'envoi ou imprimer directement le document. @endif
                </div>
            </div>
        @elseif($order->isRegularisation())
            <div class="mt-4 text-xs text-sky-900 bg-sky-50 border border-sky-200 rounded-lg p-3 flex items-start gap-2">
                <i data-lucide="file-check" class="w-4 h-4 text-sky-700 flex-shrink-0 mt-0.5"></i>
                <span><strong>Bon de régularisation.</strong> Établi le {{ $order->created_at->format('d/m/Y à H:i') }} pour une marchandise reçue sans commande préalable. Il porte ce qui a été gardé et reçoit la facture du fournisseur.</span>
            </div>
        @elseif($order->sent_at && $order->sent_to_email)
            <div class="mt-4 text-xs text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg p-3 flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 flex-shrink-0"></i>
                <span>Transmis avec succès au fournisseur par email le {{ $order->sent_at->format('d/m/Y à H:i') }} à <strong>{{ $order->sent_to_email }}</strong>.</span>
            </div>
        @elseif($order->sent_at)
            <div class="mt-4 text-xs text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg p-3 flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 flex-shrink-0"></i>
                <span>Transmis au fournisseur le {{ $order->sent_at->format('d/m/Y à H:i') }} — {{ mb_strtolower($order->transmissionLabel() ?? 'sans email') }}.</span>
            </div>
        @endif

        {{-- Actions opérationnelles --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mt-5 pt-4 border-t border-secondary/15">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('economat.orders.print', $order) }}" target="_blank" class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i data-lucide="printer" class="w-4 h-4"></i> Imprimer
                </a>

                @if($order->canBeReceived())
                    <a href="{{ route('economat.receipts.create', $order) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                        <i data-lucide="package-check" class="w-4 h-4"></i> Réceptionner la livraison (BL)
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($order->canBeSent())
                    {{-- Sans email, le bon part imprimé, au téléphone ou par WhatsApp :
                         l'économe dit comment, et la livraison pourra être réceptionnée. --}}
                    @droit('economat.orders.transmit')
                        <form method="POST" action="{{ route('economat.orders.transmit', $order) }}" class="flex items-center gap-2">
                            @csrf
                            <label for="moyen-transmission" class="sr-only">Moyen de transmission</label>
                            <select id="moyen-transmission" name="moyen" required class="px-2.5 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                                @foreach(\App\Models\PurchaseOrder::TRANSMISSIONS_MANUELLES as $code => $libelle)
                                    <option value="{{ $code }}">{{ $libelle }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 {{ $order->supplier?->canReceiveOrdersByEmail() ? 'border border-secondary/30 text-primary hover:bg-accent/10' : 'bg-primary text-white hover:bg-surface-dark shadow-sm' }} text-sm font-medium rounded-lg transition-colors">
                                <i data-lucide="hand" class="w-4 h-4"></i> Marquer comme transmis
                            </button>
                        </form>
                    @enddroit
                    @if($order->supplier?->canReceiveOrdersByEmail())
                        <form method="POST" action="{{ route('economat.orders.send', $order) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                                <i data-lucide="send" class="w-4 h-4"></i> Envoyer au fournisseur
                            </button>
                        </form>
                    @endif
                @elseif($order->status === 'sent' && $order->send_error)
                    <form method="POST" action="{{ route('economat.orders.send', $order) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/10">
                            <i data-lucide="rotate-cw" class="w-4 h-4"></i> Renvoyer l'email
                        </button>
                    </form>
                @endif

                @if($order->canBeCancelled())
                    <form method="POST" action="{{ route('economat.orders.cancel', $order) }}" onsubmit="return confirm('Êtes-vous certain de vouloir annuler ce bon de commande ?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-3.5 py-2 border border-red-200 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                            <i data-lucide="x-circle" class="w-4 h-4"></i> Annuler
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Cartes jumelées : Informations Fournisseur & Signature Numérique Émetteur --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Carte Fournisseur Unique --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-secondary/10">
                <div class="flex items-center gap-2">
                    <i data-lucide="truck" class="w-4 h-4 text-primary/70"></i>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-primary/70">Fournisseur Attitré</h2>
                </div>
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200/80 px-2 py-0.5 rounded-full">
                    <i data-lucide="lock" class="w-3 h-3"></i> Bon mono-fournisseur
                </span>
            </div>
            <div class="space-y-2">
                <div class="text-base font-bold text-primary">{{ $order->supplier->name }}</div>
                @if($order->supplier->code)
                    <div class="text-xs text-primary/50 font-mono">Code : {{ $order->supplier->code }}</div>
                @endif
                <div class="text-xs text-primary/70 flex items-center gap-2 pt-1">
                    <i data-lucide="mail" class="w-3.5 h-3.5 text-primary/40"></i>
                    <span>{{ $order->supplier->email ?? 'Aucun email renseigné' }}</span>
                </div>
                <div class="text-xs text-primary/70 flex items-center gap-2">
                    <i data-lucide="phone" class="w-3.5 h-3.5 text-primary/40"></i>
                    <span>{{ $order->supplier->phone ?? 'Aucun téléphone renseigné' }}</span>
                </div>
                @if($order->supplier->address)
                    <div class="text-xs text-primary/60 flex items-start gap-2">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-primary/40 mt-0.5"></i>
                        <span>{{ $order->supplier->address }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Carte Signature Numérique Officielle --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-secondary/10">
                <div class="flex items-center gap-2">
                    <i data-lucide="pen-tool" class="w-4 h-4 text-emerald-700"></i>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-primary/70">Signature Émetteur</h2>
                </div>
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200/80 px-2 py-0.5 rounded-full">
                    <i data-lucide="shield-check" class="w-3 h-3"></i> Certifiée
                </span>
            </div>

            <div class="bg-gray-50/70 border border-dashed border-secondary/30 rounded-lg p-3 text-center my-auto">
                <div class="text-[10px] uppercase font-semibold text-primary/50 tracking-wider mb-1">Signature manuscrite certifiée</div>
                <div class="font-signature text-4xl text-primary font-normal leading-tight py-1 select-none">
                    {{ $order->issuerSignature() ?? ($order->createdBy?->name ?? 'Économe') }}
                </div>
                <div class="text-[11px] text-primary/70 font-medium">
                    {{ $order->createdBy?->name ?? 'Économe' }}
                </div>
                <div class="text-[10px] text-primary/40 mt-0.5">
                    Émis numériquement le {{ $order->created_at->format('d/m/Y') }}
                </div>
            </div>

            <p class="text-[11px] text-primary/50 mt-3 italic">
                Ce bon de commande engage le réapprovisionnement de l'économat sous la responsabilité de l'émetteur authentifié.
            </p>
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
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Total</th>
                            @if($order->canBeReceived())
                                <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Reçu maintenant</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($order->lines as $line)
                            <tr>
                                <td class="px-5 py-3 text-primary">
                                    <div class="font-medium text-primary">{{ $line->item?->name ?? '—' }}</div>
                                    <div class="text-[11px] text-primary/40 flex items-center gap-1.5 mt-0.5">
                                        @if($line->item?->code)
                                            <span class="font-mono">{{ $line->item->code }}</span> ·
                                        @endif
                                        <span class="inline-block px-1.5 py-0.2 bg-gray-100 rounded text-[10px]">{{ $line->item?->unit ?? 'unité' }}</span>
                                        @if($line->item?->category)
                                            · <span>{{ $line->item->category->name }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right text-primary/70 font-mono">{{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }}</td>
                                <td class="px-5 py-3 text-right text-primary/70 font-mono">{{ rtrim(rtrim(number_format($line->quantity_received, 3, ',', ' '), '0'), ',') }}</td>
                                <td class="px-5 py-3 text-right text-primary/70 font-mono">{{ number_format($line->unit_price / 100, 0, ',', ' ') }} F</td>
                                <td class="px-5 py-3 text-right font-medium text-primary font-mono">{{ number_format($line->total() / 100, 0, ',', ' ') }} F</td>
                                @if($order->canBeReceived())
                                    <td class="px-5 py-3 text-right">
                                        @if($line->outstanding() > 0)
                                            <input type="number" step="0.001" min="0" max="{{ $line->outstanding() }}" name="received[{{ $line->id }}]"
                                                placeholder="0" aria-label="Quantité reçue — {{ $line->item?->name }}"
                                                class="w-24 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary text-right">
                                            {{-- Le reste dû s'affiche à côté, pas dans le champ : un chiffre grisé dans le champ se prend pour une saisie. --}}
                                            <span class="block text-[10px] text-primary/45 mt-0.5">reste {{ rtrim(rtrim(number_format($line->outstanding(), 3, ',', ' '), '0'), ',') }}</span>
                                        @else
                                            <span class="text-green-600 text-xs font-semibold">Soldé</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50">
                            <td colspan="{{ $order->canBeReceived() ? 5 : 4 }}" class="px-5 py-3 text-right font-semibold text-primary">Total Général</td>
                            <td class="px-5 py-3 text-right font-bold text-primary font-mono text-base">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</td>
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
        <p class="text-sm text-primary/60 mt-4 bg-white border border-secondary/20 rounded-xl p-4 shadow-sm"><strong>Note :</strong> {{ $order->notes }}</p>
    @endif

    {{-- Demande d'achat d'origine --}}
    @if($order->purchaseRequest)
        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-primary/50 uppercase">Demande d'achat d'origine</span>
                <div class="text-sm font-bold font-mono text-primary mt-0.5">{{ $order->purchaseRequest->number }}</div>
                <div class="text-xs text-primary/60">Service demandeur : {{ $order->purchaseRequest->departmentLabel() }}</div>
            </div>
            <a href="{{ route('economat.purchase_requests.show', $order->purchaseRequest) }}" class="px-3 py-1.5 bg-secondary/10 hover:bg-secondary/20 text-primary text-xs font-medium rounded-lg transition-colors">
                Voir la demande d'achat →
            </a>
        </div>
    @endif

    {{-- Bons de réception associés (Goods Receipts) --}}
    @if($order->receipts->isNotEmpty())
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-primary">Bons de réception établis ({{ $order->receipts->count() }})</h2>
                    <p class="text-xs text-primary/50">Livraisons physiques pointées et entrées en stock.</p>
                </div>
            </div>
            <div class="divide-y divide-secondary/10">
                @foreach($order->receipts as $rc)
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50/50 transition-colors">
                        <div>
                            <a href="{{ route('economat.receipts.show', $rc) }}" class="font-mono font-bold text-sm text-primary hover:underline">
                                {{ $rc->number }}
                            </a>
                            <span class="text-xs text-primary/50 ml-2">du {{ $rc->received_at->format('d/m/Y H:i') }}</span>
                            @if($rc->delivery_note_number)
                                <span class="text-xs text-primary/60 ml-2 font-mono">(BL: {{ $rc->delivery_note_number }})</span>
                            @endif
                            <div class="text-xs text-primary/40 mt-0.5">Pointé par {{ $rc->receivedBy?->name ?? 'Magasinier' }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-mono font-bold text-sm text-emerald-700">
                                {{ number_format($rc->total_amount / 100, 0, ',', ' ') }} FCFA
                            </span>
                            <a href="{{ route('economat.receipts.print', $rc) }}" target="_blank" class="p-1.5 border border-secondary/30 rounded-lg text-primary hover:bg-gray-100 transition-colors" title="Imprimer le bordereau">
                                <i data-lucide="printer" class="w-4 h-4"></i>
                            </a>
                            <a href="{{ route('economat.receipts.show', $rc) }}" class="px-2.5 py-1 bg-secondary/10 hover:bg-secondary/20 rounded-lg text-xs font-medium text-primary">
                                Détails
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Rapprochement comptable & Factures Fournisseurs --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-primary">Rapprochement Factures Fournisseurs</h2>
                <p class="text-xs text-primary/50">
                    Commandé {{ number_format($order->total_amount / 100, 0, ',', ' ') }} F
                    · reçu {{ number_format($order->receivedAmount() / 100, 0, ',', ' ') }} F
                    · facturé {{ number_format($order->invoicedAmount() / 100, 0, ',', ' ') }} F
                </p>
            </div>
            @php
                $invStatus = $order->invoicingStatus();
                $invoicedAmt = $order->invoicedAmount();
            @endphp
            <div>
                @if($invStatus === 'fully_invoiced')
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                        Facturation soldée
                    </span>
                @elseif($invStatus === 'partially_invoiced')
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        Partiellement facturé ({{ number_format($invoicedAmt / 100, 0, ',', ' ') }} / {{ number_format($order->total_amount / 100, 0, ',', ' ') }} F)
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                        Non facturé
                    </span>
                @endif
            </div>
        </div>

        @if($order->invoices->isNotEmpty())
            <div class="divide-y divide-secondary/10">
                @foreach($order->invoices as $inv)
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-gray-50/50">
                        <div>
                            <div class="font-mono font-bold text-sm text-primary">{{ $inv->number }}</div>
                            <div class="text-xs text-primary/50">Facture du {{ $inv->invoice_date->format('d/m/Y') }} · {{ $inv->label }}</div>
                            @if($inv->hasReceptionVariance())
                                <div class="text-[11px] text-amber-700 mt-0.5">Écart +{{ number_format($inv->reception_variance / 100, 0, ',', ' ') }} F sur le reçu — {{ $inv->variance_reason }}</div>
                            @endif
                        </div>
                        <div class="text-right">
                            <div class="font-mono font-bold text-sm text-primary">{{ number_format($inv->amount_ttc / 100, 0, ',', ' ') }} FCFA TTC</div>
                            <div class="text-[11px] text-primary/50 font-mono">Net à payer : {{ number_format($inv->net_payable / 100, 0, ',', ' ') }} F</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-6 text-center text-xs text-primary/50">
                Aucune facture fournisseur n'est encore rattachée à ce bon de commande.
            </div>
        @endif

        @droit('accounting.ledger.suppliers.creer')
            <div class="px-5 py-3 bg-gray-50 border-t border-secondary/15 flex justify-between items-center">
                <span class="text-xs text-primary/60">Une facture reçue du fournisseur pour cette commande ?</span>
                <a href="{{ route('accounting.ledger.suppliers.create', ['bon' => $order->id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Saisir la facture fournisseur
                </a>
            </div>
        @enddroit
    </div>
</div>
@endsection

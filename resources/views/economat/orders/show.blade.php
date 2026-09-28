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
        <div class="flex flex-wrap items-center justify-between gap-2 mt-4 pt-4 border-t border-secondary/20">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('economat.orders.print', $order) }}" target="_blank" class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i data-lucide="printer" class="w-4 h-4"></i> Imprimer le Bon (BC)
                </a>

                @if($order->canBeReceived())
                    <a href="{{ route('economat.receipts.create', $order) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                        <i data-lucide="package-check" class="w-4 h-4"></i> Réceptionner la livraison (BL)
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
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
                <p class="text-xs text-primary/50">Suivi comptable entre marchandises commandées, reçues et facturées.</p>
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

        @if(\Illuminate\Support\Facades\Route::has('accounting.supplier-invoices.create'))
            <div class="px-5 py-3 bg-gray-50 border-t border-secondary/15 flex justify-between items-center">
                <span class="text-xs text-primary/60">Une facture reçue du fournisseur pour cette commande ?</span>
                <a href="{{ route('accounting.supplier-invoices.create', ['bon' => $order->id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Saisir la facture fournisseur
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

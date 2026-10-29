{{--
    Suivi des livraisons d'un bon de commande : chaque livraison (un bon de
    réception), sa date, ce qui est entré, ses bordereaux ; puis ce qui reste
    attendu. Paramètres : $order (lines.item, receipts.lines.item,
    receipts.attachments chargés), $courant (bon affiché, facultatif).
--}}
@php
    $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
    $livraisons = $order->receipts
        ->where('status', '!=', \App\Models\GoodsReceipt::STATUS_CANCELLED)
        ->sortBy(fn ($r) => [$r->received_at === null ? 1 : 0, $r->received_at?->timestamp ?? 0, $r->id])
        ->values();
    $restes = $order->lines->filter(fn ($l) => $l->outstanding() > 0);
    $solde = in_array($order->status, [\App\Models\PurchaseOrder::STATUS_RECEIVED, \App\Models\PurchaseOrder::STATUS_CANCELLED], true);
    $courant = $courant ?? null;
@endphp

@if($livraisons->isNotEmpty())
    <section class="bg-white border border-secondary/20 rounded-xl shadow-sm overflow-hidden" aria-labelledby="titre-suivi-livraisons">
        <header class="px-5 py-3 border-b border-secondary/15 bg-gray-50/70 flex items-center justify-between gap-3">
            <h2 id="titre-suivi-livraisons" class="text-sm font-semibold text-primary">Suivi des livraisons — {{ $order->number }}</h2>
            <span class="text-[11px] font-semibold {{ $solde ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $order->status === \App\Models\PurchaseOrder::STATUS_RECEIVED ? 'Livraison complète' : ($restes->isNotEmpty() ? 'Livraison en cours' : $order->statusLabel()) }}
            </span>
        </header>
        <ol class="divide-y divide-secondary/10">
            @foreach($livraisons as $rang => $livraison)
                <li class="px-5 py-3 {{ $courant && $courant->id === $livraison->id ? 'bg-accent/10' : '' }}">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="text-sm text-primary">
                            <span class="text-primary/45">Livraison {{ $rang + 1 }} ·</span>
                            <a href="{{ route('economat.receipts.show', $livraison) }}" class="font-mono font-bold hover:underline">{{ $livraison->number }}</a>
                            @if($livraison->estEnAttente())
                                <span class="ml-1 rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-800">Attendue</span>
                            @elseif($livraison->deliveryLabel())
                                <span class="ml-1 text-[11px] text-primary/55">{{ $livraison->deliveryLabel() }}</span>
                            @endif
                        </div>
                        <span class="text-xs {{ $livraison->received_at ? 'text-primary/70' : 'text-amber-700' }}">
                            {{ $livraison->received_at ? 'Reçue le ' . $livraison->received_at->format('d/m/Y à H:i') : 'Date de réception à saisir à la livraison' }}
                        </span>
                    </div>
                    @unless($livraison->estEnAttente())
                        <p class="mt-1 text-xs text-primary/60">
                            {{ $livraison->lines->filter(fn ($l) => (float) $l->quantity_accepted > 0)->map(fn ($l) => ($l->item?->name ?? 'Article') . ' : ' . $qte($l->quantity_accepted) . ' ' . $l->item?->unit)->implode(' · ') ?: 'Rien d\'accepté' }}
                        </p>
                    @endunless
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                        @forelse($livraison->attachments as $bordereau)
                            @droit('economat.receipts.attachments.voir')
                                <a href="{{ route('economat.receipts.attachments.show', [$livraison, $bordereau]) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 rounded border border-secondary/25 px-2 py-0.5 text-primary hover:bg-accent/20">
                                    <i data-lucide="paperclip" class="h-3 w-3" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit($bordereau->original_name, 32) }}
                                </a>
                            @else
                                <span class="inline-flex items-center gap-1 text-primary/55"><i data-lucide="paperclip" class="h-3 w-3" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit($bordereau->original_name, 32) }}</span>
                            @enddroit
                        @empty
                            @unless($livraison->estEnAttente())
                                <span class="text-amber-700">Aucun bordereau joint</span>
                            @endunless
                        @endforelse
                    </div>
                </li>
            @endforeach
        </ol>
        @if($restes->isNotEmpty() && ! $solde)
            <footer class="px-5 py-2.5 border-t border-secondary/15 bg-amber-50/40 text-xs text-amber-900">
                Reste attendu : {{ $restes->map(fn ($l) => ($l->item?->name ?? 'Article') . ' ' . $qte($l->outstanding()) . ' ' . $l->item?->unit)->implode(' · ') }}
            </footer>
        @endif
    </section>
@endif

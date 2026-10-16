{{--
    Ce qui attend la réception en ce moment : de quoi savoir où reprendre.

    Attend : $resteAFaire (PassationService::resteAFaire)
--}}
@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $listes = [
        ['arrivees', 'Arrivées attendues', 'log-in', fn ($b) => 'arrivée prévue le ' . $b->check_in?->format('d/m')],
        ['departs', 'Départs à enregistrer', 'log-out', fn ($b) => 'départ prévu le ' . $b->check_out?->format('d/m')],
        ['soldes', 'Soldes à encaisser', 'banknote', fn ($b) => 'reste ' . $fcfa($b->balance_due)],
        ['aConfirmer', 'Réservations du site à confirmer', 'globe', fn ($b) => 'reçue le ' . $b->created_at?->format('d/m à H:i')],
        ['minibar', 'Relevés du mini-bar attendus', 'wine', fn ($b) => 'demandé à ' . $b->minibar_check_requested_at?->format('H:i')],
    ];
    $total = collect($resteAFaire)->sum(fn ($l) => $l->count());
@endphp

<section class="bg-white rounded-xl shadow-sm overflow-hidden" aria-labelledby="reste-a-faire">
    <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
        <h2 id="reste-a-faire" class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
            <i data-lucide="list-todo" class="w-4 h-4 text-primary/60"></i> Ce qui reste à faire
        </h2>
        <span class="text-xs text-primary/45">en ce moment</span>
    </div>
    @if($total === 0)
        <p class="px-5 py-6 text-sm text-primary/40">Rien en attente à la réception.</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 divide-y md:divide-y-0 divide-secondary/10">
            @foreach($listes as [$cle, $titre, $icone, $detail])
                @if($resteAFaire[$cle]->isNotEmpty())
                    <div class="px-5 py-3">
                        <h3 class="text-xs font-semibold text-primary flex items-center gap-1.5 mb-2">
                            <i data-lucide="{{ $icone }}" class="w-3.5 h-3.5 text-primary/50"></i> {{ $titre }} ({{ $resteAFaire[$cle]->count() }})
                        </h3>
                        <ul class="space-y-1 text-sm">
                            @foreach($resteAFaire[$cle] as $b)
                                <li>
                                    <a href="{{ route('bookings.show', $b) }}" class="font-medium text-primary hover:underline">ch. {{ $b->room?->number ?? '—' }}</a>
                                    <span class="text-primary/60">· {{ $b->customer?->full_name ?? $b->booking_number }}</span>
                                    <span class="block text-xs text-primary/45">{{ $detail($b) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</section>

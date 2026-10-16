{{--
    Le quart d'un réceptionniste : sa caisse (fond, encaissements, sorties,
    théorique, comptage) et ce qu'il a fait entre l'ouverture et la remise.

    Attend : $rapport (PassationService::rapport)
--}}
@php
    $fcfa = fn ($c) => $c === null ? '—' : number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $act = $rapport['activite'];
    $client = fn ($b) => $b?->customer?->full_name ?? '—';
@endphp

<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    {{-- ═══ Caisse ═══ --}}
    <section class="bg-white rounded-xl shadow-sm overflow-hidden" aria-labelledby="fiche-caisse">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between gap-3">
            <h2 id="fiche-caisse" class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                <i data-lucide="wallet" class="w-4 h-4 text-primary/60"></i> Caisse du quart
            </h2>
            <span class="text-xs text-primary/45">
                {{ $rapport['debut']?->format('d/m H:i') }} → {{ $rapport['fin']?->format('d/m H:i') }}
            </span>
        </div>
        <dl class="px-5 py-4 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-primary/60">Fond à l'ouverture</dt><dd class="tabular-nums">{{ $fcfa($rapport['fond']) }}</dd></div>
            @forelse($rapport['parMode'] as $mode)
                <div class="flex justify-between"><dt class="text-primary/60">+ {{ $mode['libelle'] }} ({{ $mode['nombre'] }})</dt><dd class="tabular-nums text-green-700">{{ $fcfa($mode['total']) }}</dd></div>
            @empty
                <div class="flex justify-between"><dt class="text-primary/60">Encaissements</dt><dd class="text-primary/40">aucun</dd></div>
            @endforelse
            @if($rapport['decaissements']->isNotEmpty())
                <div class="flex justify-between"><dt class="text-primary/60">− Sorties de caisse ({{ $rapport['decaissements']->count() }})</dt><dd class="tabular-nums text-red-600">{{ $fcfa($rapport['decaissements']->sum('amount')) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-secondary/15 pt-2 font-semibold text-primary">
                <dt>Espèces attendues dans le tiroir</dt><dd class="tabular-nums">{{ $fcfa($rapport['theorique']) }}</dd>
            </div>
            @if($rapport['compte'] !== null)
                <div class="flex justify-between"><dt class="text-primary/60">Compté à la remise</dt><dd class="tabular-nums font-semibold">{{ $fcfa($rapport['compte']) }}</dd></div>
                @if((int) $rapport['ecart'] !== 0)
                    <div class="flex justify-between text-red-700"><dt>Écart de caisse du quart</dt><dd class="tabular-nums font-semibold">{{ $fcfa($rapport['ecart']) }}</dd></div>
                @endif
            @endif
        </dl>

        <div class="border-t border-secondary/15">
            <h3 class="px-5 pt-3 text-[11px] font-semibold uppercase tracking-wider text-primary/45">Encaissements</h3>
            @if($rapport['paiements']->isEmpty())
                <p class="px-5 py-3 text-sm text-primary/40">Aucun encaissement pendant le quart.</p>
            @else
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($rapport['paiements'] as $p)
                            <tr class="{{ $p->status !== 'completed' ? 'text-primary/40 line-through' : '' }}">
                                <td class="px-5 py-2 tabular-nums text-primary/55 w-14">{{ $p->paid_at?->format('H:i') }}</td>
                                <td class="py-2">
                                    @if($p->booking)
                                        <a href="{{ route('bookings.show', $p->booking) }}" class="font-medium text-primary hover:underline">{{ $p->booking->booking_number }}</a>
                                        <span class="text-xs text-primary/50">· ch. {{ $p->booking->room?->number }} · {{ $client($p->booking) }}</span>
                                    @else
                                        <span class="text-primary/70">{{ $p->customer?->full_name ?? 'Vente directe' }}</span>
                                    @endif
                                    @if($p->notes)<span class="block text-xs text-primary/45">{{ \Illuminate\Support\Str::limit($p->notes, 80) }}</span>@endif
                                </td>
                                <td class="py-2 text-xs text-primary/60">{{ $p->methodLabel() }}</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ $fcfa($p->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if($rapport['decaissements']->isNotEmpty())
            <div class="border-t border-secondary/15">
                <h3 class="px-5 pt-3 text-[11px] font-semibold uppercase tracking-wider text-primary/45">Sorties de caisse</h3>
                <ul class="divide-y divide-secondary/10 text-sm">
                    @foreach($rapport['decaissements'] as $d)
                        <li class="px-5 py-2 flex justify-between gap-3">
                            <span><span class="tabular-nums text-primary/55">{{ $d->created_at?->format('H:i') }}</span> {{ $d->reason }}</span>
                            <span class="tabular-nums text-red-600">{{ $fcfa($d->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($rapport['ventesPos']->isNotEmpty())
            <div class="border-t border-secondary/15">
                <h3 class="px-5 pt-3 text-[11px] font-semibold uppercase tracking-wider text-primary/45">Ventes du POS réception</h3>
                <ul class="divide-y divide-secondary/10 text-sm">
                    @foreach($rapport['ventesPos'] as $v)
                        <li class="px-5 py-2 flex justify-between gap-3">
                            <span>
                                <span class="tabular-nums text-primary/55">{{ $v->created_at?->format('H:i') }}</span>
                                {{ $v->sale_number }} · {{ $v->customer_name ?? 'Client de passage' }}
                                <span class="text-xs text-primary/45">{{ $v->payment_type === 'room_charge' ? '· porté sur la chambre ' . $v->room_number : '' }}</span>
                            </span>
                            <span class="tabular-nums">{{ $fcfa($v->total_amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- ═══ Activité ═══ --}}
    <section class="bg-white rounded-xl shadow-sm overflow-hidden" aria-labelledby="fiche-activite">
        <div class="px-5 py-4 border-b border-secondary/20">
            <h2 id="fiche-activite" class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                <i data-lucide="clipboard-list" class="w-4 h-4 text-primary/60"></i> Ce que le quart a fait
            </h2>
        </div>
        @php
            $blocs = [
                ['Arrivées enregistrées', $act['arrivees'], fn ($b) => [$b->actual_check_in?->format('H:i'), $b]],
                ['Départs enregistrés', $act['departs'], fn ($b) => [$b->actual_check_out?->format('H:i'), $b]],
                ['Réservations créées', $act['reservations'], fn ($b) => [$b->created_at?->format('H:i'), $b]],
            ];
            $rien = collect($act)->every(fn ($c) => $c->isEmpty());
        @endphp
        @if($rien)
            <p class="px-5 py-6 text-sm text-primary/40">Aucune opération de séjour pendant le quart.</p>
        @endif
        @foreach($blocs as [$titre, $liste, $ligne])
            @if($liste->isNotEmpty())
                <div class="border-b border-secondary/10 last:border-b-0">
                    <h3 class="px-5 pt-3 text-[11px] font-semibold uppercase tracking-wider text-primary/45">{{ $titre }} ({{ $liste->count() }})</h3>
                    <ul class="divide-y divide-secondary/10 text-sm">
                        @foreach($liste as $b)
                            @php [$heure, $sejour] = $ligne($b); @endphp
                            <li class="px-5 py-2">
                                <span class="tabular-nums text-primary/55">{{ $heure }}</span>
                                <a href="{{ route('bookings.show', $sejour) }}" class="font-medium text-primary hover:underline">{{ $sejour->booking_number }}</a>
                                <span class="text-primary/60">· ch. {{ $sejour->room?->number ?? '—' }} · {{ $client($sejour) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
        @if($act['annulations']->isNotEmpty())
            <div class="border-b border-secondary/10">
                <h3 class="px-5 pt-3 text-[11px] font-semibold uppercase tracking-wider text-primary/45">Annulations ({{ $act['annulations']->count() }})</h3>
                <ul class="divide-y divide-secondary/10 text-sm">
                    @foreach($act['annulations'] as $a)
                        <li class="px-5 py-2">
                            <span class="tabular-nums text-primary/55">{{ $a->created_at?->format('H:i') }}</span>
                            {{ $a->booking?->booking_number }} · {{ $client($a->booking) }}
                            @if($a->reason_description)<span class="block text-xs text-primary/45">{{ \Illuminate\Support\Str::limit($a->reason_description, 90) }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if($act['prestations']->isNotEmpty())
            <div class="border-b border-secondary/10">
                <h3 class="px-5 pt-3 text-[11px] font-semibold uppercase tracking-wider text-primary/45">Prestations ajoutées aux folios ({{ $act['prestations']->count() }})</h3>
                <ul class="divide-y divide-secondary/10 text-sm">
                    @foreach($act['prestations'] as $f)
                        <li class="px-5 py-2 flex justify-between gap-3">
                            <span><span class="tabular-nums text-primary/55">{{ $f->occurred_at?->format('H:i') }}</span> ch. {{ $f->booking?->room?->number ?? '—' }} · {{ $f->description }}</span>
                            <span class="tabular-nums">{{ $fcfa($f->total_price) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if($act['minibar']->isNotEmpty())
            <div>
                <h3 class="px-5 pt-3 text-[11px] font-semibold uppercase tracking-wider text-primary/45">Relevés du mini-bar demandés ({{ $act['minibar']->count() }})</h3>
                <ul class="divide-y divide-secondary/10 text-sm">
                    @foreach($act['minibar'] as $b)
                        <li class="px-5 py-2"><span class="tabular-nums text-primary/55">{{ $b->minibar_check_requested_at?->format('H:i') }}</span> ch. {{ $b->room?->number }} · {{ $b->booking_number }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>

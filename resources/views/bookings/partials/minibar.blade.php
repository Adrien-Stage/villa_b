{{--
    Le mini-bar de la chambre, vu de la réception : relevés du séjour, contrôle
    à demander au départ, consommation constatée après le départ.
--}}
@php
    $chambreMinibar = $booking->room;
    $avecMinibar = $chambreMinibar && $chambreMinibar->aUnMiniBar();
    $relevesDuSejour = $avecMinibar
        ? \App\Models\MinibarCheck::query()->with('user', 'lines')->where('booking_id', $booking->id)->latest('checked_at')->latest('id')->get()
        : collect();
    $releveDepart = $relevesDuSejour->firstWhere('occasion', \App\Models\MinibarCheck::OCCASION_DEPART);
    $apresDepart = $relevesDuSejour->where('billing', \App\Models\MinibarCheck::BILLING_A_FACTURER);
    $enSejour = $booking->status->value === 'checked_in';
    $fcfaMinibar = fn (int $c) => number_format($c / 100, 0, ',', ' ') . ' FCFA';
@endphp

@if($avecMinibar && ($enSejour || $relevesDuSejour->isNotEmpty() || $chambreMinibar->minibar_due_booking_id === $booking->id))
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-secondary/20">
        <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
            <i data-lucide="wine" class="w-4 h-4 text-primary/60"></i> Mini-bar de la chambre {{ $chambreMinibar->number }}
        </h2>
        @if($enSejour && !$booking->minibar_check_requested_at)
            @droit('bookings.minibar.request')
                <form method="POST" action="{{ route('bookings.minibar.request', $booking) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 text-white text-xs font-semibold rounded-lg hover:bg-amber-700 transition-colors">
                        <i data-lucide="bell-ring" class="w-3.5 h-3.5"></i> Demander le contrôle (départ)
                    </button>
                </form>
            @enddroit
        @endif
    </div>
    <div class="px-5 py-4 space-y-2 text-sm">
        @if($enSejour && $booking->minibar_check_requested_at && !$releveDepart)
            <p class="text-amber-800">
                Contrôle demandé à {{ $booking->minibar_check_requested_at->format('H:i') }} : en attente du relevé du housekeeping.
                Vous pouvez clore la note sans l'attendre ; le relevé sera alors fait au ménage, et sa consommation restera à facturer.
            </p>
        @elseif($releveDepart)
            <p class="text-emerald-800">
                Relevé de départ fait à {{ $releveDepart->checked_at->format('H:i') }}@if($releveDepart->user) par {{ $releveDepart->user->name }}@endif :
                {{ $releveDepart->resume() }}{{ $releveDepart->total_amount > 0 ? ' — ' . $fcfaMinibar($releveDepart->total_amount) . ' au folio' : '' }}.
            </p>
        @elseif($enSejour)
            <p class="text-primary/60">Au départ, demandez le contrôle du mini-bar avant de clore la note : la consommation s'ajoutera au folio.</p>
        @endif

        @if($chambreMinibar->minibar_due_booking_id === $booking->id)
            <p class="text-red-700">Client parti sans relevé : le housekeeping le fera au ménage de la chambre.</p>
        @endif

        @foreach($apresDepart as $releve)
            <p class="text-red-700">
                Consommé après le départ (relevé du {{ $releve->checked_at->format('d/m à H:i') }}) : {{ $releve->resume() }} —
                <strong>{{ $fcfaMinibar($releve->total_amount) }} à facturer au client</strong>.
            </p>
        @endforeach

        @php $autres = $relevesDuSejour->where('occasion', \App\Models\MinibarCheck::OCCASION_SEJOUR); @endphp
        @if($autres->isNotEmpty())
            <details class="text-xs text-primary/60">
                <summary class="cursor-pointer">Relevés pendant le séjour ({{ $autres->count() }})</summary>
                <ul class="mt-2 space-y-1">
                    @foreach($autres as $releve)
                        <li>{{ $releve->checked_at->format('d/m H:i') }} · {{ $releve->user?->name }} · {{ $releve->resume() }}{{ $releve->total_amount > 0 ? ' — ' . $fcfaMinibar($releve->total_amount) : '' }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</div>
@endif

{{--
    Le restaurant dans lequel on travaille. N'apparaît que si la personne en
    voit plusieurs : la direction peut aussi les voir tous ensemble.
--}}
@php
    $contexte = app(\App\Services\RestaurantContext::class);
    $accessibles = $contexte->accessibles(auth()->user());
    $courant = $contexte->courant(auth()->user());
@endphp
@if($accessibles->count() > 1)
    <form method="POST" action="{{ route('restaurant.courant') }}" class="mb-4 flex flex-wrap items-center gap-2 rounded-xl bg-white px-4 py-2.5 shadow-sm">
        @csrf
        <i data-lucide="utensils-crossed" class="w-4 h-4 text-primary/60"></i>
        <label for="restaurant-courant" class="text-xs font-semibold text-primary/70">Restaurant</label>
        <select id="restaurant-courant" name="restaurant" onchange="this.form.submit()"
                class="rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs text-primary">
            <option value="tous" @selected($courant === null)>Tous les restaurants</option>
            @foreach($accessibles as $restaurant)
                <option value="{{ $restaurant->id }}" @selected($courant?->id === $restaurant->id)>{{ $restaurant->name }}</option>
            @endforeach
        </select>
        @if($contexte->residentsSeulement(auth()->user()))
            <span class="text-[11px] text-primary/50">Notes reportées sur un séjour seulement.</span>
        @endif
        <noscript><button type="submit" class="rounded-lg border border-secondary/30 px-2 py-1 text-xs">Changer</button></noscript>
    </form>
@elseif($accessibles->isEmpty() && $contexte->plusieurs())
    {{-- Le personnel d'un restaurant ne voit que les restaurants où il est affecté. --}}
    <p class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800">
        Vous n'êtes affecté à aucun restaurant : demandez à la direction de vous rattacher à votre équipe.
    </p>
@endif

{{--
    Restaurant d'affectation d'un membre du département Restauration. La
    liste n'apparaît que lorsque ce département est choisi : on y travaille
    dans un restaurant précis, et l'on n'y voit que celui-là.

    $contexte : « create » ou « edit_{id} », celui du formulaire.
    $departements : départements proposés par le formulaire.
    $personne : le compte modifié, ou null à la création.
--}}
@php
    $restaurantsHotel = app(\App\Services\RestaurantContext::class)->restaurants();
    $repris = old('form_type') === $contexte;
    $departementInitial = (int) ($repris ? old('department_id') : $personne?->department_id);
    $restauration = $departements->filter(fn ($d) => $d->estLaRestauration())->pluck('id')->all();
    $actuels = $personne ? $personne->restaurants->pluck('id')->all() : [];
    $choisi = (int) ($repris ? old('restaurant_id') : ($actuels[0] ?? ($restaurantsHotel->count() === 1 ? $restaurantsHotel->first()->id : 0)));
    $autres = $personne ? $restaurantsHotel->whereIn('id', $actuels)->pluck('name')->all() : [];
@endphp
@if($restaurantsHotel->isNotEmpty() && \App\Support\TenantModules::has('restaurant'))
    <div x-data="{ visible: @js(in_array($departementInitial, $restauration, true)) }"
         x-on:department-changed.window="if ($event.detail.context === @js($contexte)) visible = $event.detail.restauration"
         x-show="visible" x-cloak
         class="rounded-lg border border-secondary/25 bg-accent/10 p-3">
        <label for="restaurant-{{ $contexte }}" class="text-xs font-semibold text-primary">
            Restaurant d'affectation <span class="text-red-500" aria-hidden="true">*</span>
        </label>
        <select id="restaurant-{{ $contexte }}" name="restaurant_id" :disabled="! visible" :required="visible"
                class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none bg-white">
            @if($restaurantsHotel->count() > 1)
                <option value="">— Choisir le restaurant —</option>
            @endif
            @foreach($restaurantsHotel as $restaurant)
                <option value="{{ $restaurant->id }}" @selected($choisi === $restaurant->id)>{{ $restaurant->name }}</option>
            @endforeach
        </select>
        <p class="text-[11px] text-primary/50 mt-1">
            Il ne verra que ce restaurant. Les responsables présents dans plusieurs restaurants se composent depuis Paramètres › Restaurant.
        </p>
        @if(count($autres) > 1)
            <p class="text-[11px] text-primary/60 mt-1">
                Travaille aujourd'hui à {{ implode(', ', $autres) }} : choisir l'un d'eux ne change rien, en choisir un autre l'y mute.
            </p>
        @endif
        @error('restaurant_id')
            @if($repris)<p class="text-[11px] text-red-600 mt-1" role="alert">{{ $message }}</p>@endif
        @enderror
    </div>
@endif

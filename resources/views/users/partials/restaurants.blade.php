{{--
    Restaurants où la personne travaille. N'apparaît que si l'hôtel en a
    plusieurs : chacun ne voit que les restaurants où il est affecté.
    $choisis : identifiants cochés.
--}}
@php $restaurantsHotel = app(\App\Services\RestaurantContext::class)->restaurants(); @endphp
@if($restaurantsHotel->count() > 1)
    <fieldset>
        <input type="hidden" name="restaurants_present" value="1">
        <legend class="text-xs text-primary/60">Restaurants</legend>
        <p class="text-[11px] text-primary/40 mb-2">Pour le personnel de restaurant : il ne verra que les restaurants cochés. La direction les voit tous.</p>
        <div class="flex flex-wrap gap-4">
            @foreach($restaurantsHotel as $restaurant)
                <label class="flex items-center gap-2 text-sm text-primary">
                    <input type="checkbox" name="restaurants[]" value="{{ $restaurant->id }}" @checked(in_array($restaurant->id, array_map('intval', $choisis), true)) class="rounded border-secondary/30">
                    {{ $restaurant->name }}
                </label>
            @endforeach
        </div>
    </fieldset>
@endif

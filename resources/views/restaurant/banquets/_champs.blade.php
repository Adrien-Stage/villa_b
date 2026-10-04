{{-- Champs d'un banquet, au devis comme à la modification. $b : le banquet, ou null. --}}
@php
    $fcfa = fn ($c) => $c === null ? null : (int) round(((int) $c) / 100);
    $restaurantChoisi = (int) old('point_of_sale_id', $b?->point_of_sale_id ?? $restaurantParDefaut?->id);
@endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2"
     x-data="{ restaurant: {{ $restaurantChoisi }}, salles: @js($salles), couverts: {{ (int) old('covers', $b?->covers ?? 0) }}, prix: {{ (int) old('price_per_cover', $fcfa($b?->price_per_cover) ?? 0) }}, supplements: {{ (int) old('extras_amount', $fcfa($b?->extras_amount) ?? 0) }} }">
    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Événement</span>
        <input type="text" name="title" required maxlength="255" value="{{ old('title', $b?->title) }}" placeholder="Ex. Mariage Ngo — dîner"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>

    <label class="block">
        <span class="text-xs text-primary/60">Restaurant</span>
        <select name="point_of_sale_id" x-model.number="restaurant" required
            class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
            @foreach($restaurants as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Salle</span>
        <select name="space_id" class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
            <option value="">À préciser</option>
            <template x-for="s in salles.filter(s => s.point_of_sale_id === null || s.point_of_sale_id === restaurant)" :key="s.id">
                <option :value="s.id" x-text="s.name + (s.capacity ? ' (' + s.capacity + ' places)' : '')" :selected="s.id === {{ (int) old('space_id', $b?->space_id) }}"></option>
            </template>
        </select>
    </label>

    <label class="block">
        <span class="text-xs text-primary/60">Client</span>
        <input type="text" name="client_name" required maxlength="255" value="{{ old('client_name', $b?->client_name) }}"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Téléphone</span>
        <input type="text" name="client_phone" maxlength="40" value="{{ old('client_phone', $b?->client_phone) }}"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Email</span>
        <input type="email" name="client_email" maxlength="255" value="{{ old('client_email', $b?->client_email) }}"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>

    <label class="block">
        <span class="text-xs text-primary/60">Date</span>
        <input type="date" name="event_date" required value="{{ old('event_date', $b?->event_date?->toDateString()) }}"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <div class="grid grid-cols-2 gap-2">
        <label class="block">
            <span class="text-xs text-primary/60">Début</span>
            <input type="time" name="start_time" value="{{ old('start_time', $b?->start_time) }}"
                class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
        </label>
        <label class="block">
            <span class="text-xs text-primary/60">Fin</span>
            <input type="time" name="end_time" value="{{ old('end_time', $b?->end_time) }}"
                class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
        </label>
    </div>

    <label class="block">
        <span class="text-xs text-primary/60">Couverts</span>
        <input type="number" name="covers" required min="1" x-model.number="couverts"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Prix par couvert (FCFA)</span>
        <input type="number" name="price_per_cover" required min="0" x-model.number="prix"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Suppléments (FCFA)</span>
        <input type="number" name="extras_amount" min="0" x-model.number="supplements"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Acompte demandé (FCFA)</span>
        <input type="number" name="deposit_required" min="0" value="{{ old('deposit_required', $fcfa($b?->deposit_required) ?? 0) }}"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <p class="sm:col-span-2 text-right text-xs text-primary/60">
        Total : <strong class="text-primary" x-text="((couverts || 0) * (prix || 0) + (supplements || 0)).toLocaleString('fr-FR') + ' FCFA'"></strong>
    </p>

    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Menu</span>
        <textarea name="menu" rows="4" maxlength="5000" class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">{{ old('menu', $b?->menu) }}</textarea>
    </label>
    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Notes</span>
        <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">{{ old('notes', $b?->notes) }}</textarea>
    </label>
</div>

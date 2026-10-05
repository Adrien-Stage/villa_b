{{--
    Champs d'un restaurant, à la création comme à la modification.
    $r : le restaurant, ou null pour en créer un. Les valeurs saisies ne
    reviennent qu'au formulaire qui les a envoyées : chaque restaurant a le
    sien sur la même page.
--}}
@php
    $marque = $r ? (string) $r->id : 'nouveau';
    $repris = old('form_restaurant') === $marque;
    $valeur = fn (string $champ, $defaut) => $repris ? old($champ, $defaut) : $defaut;
@endphp
<input type="hidden" name="form_restaurant" value="{{ $marque }}">
@if($repris && $errors->any())
    {{-- Le formulaire se rouvre sur sa saisie : il dit aussi pourquoi. --}}
    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Nom</span>
        <input type="text" name="name" value="{{ $valeur('name', $r?->name) }}" required maxlength="100" placeholder="Ex. Le Kotibe"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Code</span>
        <input type="text" name="code" value="{{ $valeur('code', $r?->code) }}" required maxlength="16" placeholder="KOT"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm uppercase outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Préfixe des notes</span>
        <input type="text" name="series_prefix" value="{{ $valeur('series_prefix', $r?->series_prefix) }}" maxlength="8" placeholder="KOT-"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
</div>

<fieldset>
    <legend class="text-xs font-semibold text-primary">Services du restaurant</legend>
    <p class="mt-0.5 text-[11px] text-primary/45">Seuls les services activés s'ouvrent pour ce restaurant. Une salle a besoin d'une cuisine ou d'un bar.</p>
    @php $servicesChoisis = $valeur('services', $r ? array_keys(array_filter($servicesRestaurant, fn ($l, $s) => $r->offre($s), ARRAY_FILTER_USE_BOTH)) : array_keys($servicesRestaurant)); @endphp
    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach($servicesRestaurant as $cle => $libelle)
            <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-secondary/20 px-3 py-2 hover:bg-accent/10 has-[:checked]:border-secondary/50 has-[:checked]:bg-accent/15">
                <input type="checkbox" name="services[]" value="{{ $cle }}" @checked(in_array($cle, (array) $servicesChoisis, true)) class="mt-0.5 rounded border-secondary/30">
                <span>
                    <span class="block text-sm font-semibold text-primary">{{ $libelle }}</span>
                    <span class="block text-[11px] text-primary/50">{{ \App\Models\PointOfSale::DESCRIPTIONS_SERVICES[$cle] }}</span>
                </span>
            </label>
        @endforeach
    </div>
</fieldset>

<fieldset>
    <legend class="text-xs text-primary/60">Modes de service</legend>
    <div class="mt-1.5 flex flex-wrap gap-4">
        @php $modesChoisis = $valeur('service_modes', $r?->service_modes ?: ['carte']); @endphp
        @foreach($modes as $cle => $libelle)
            <label class="flex items-center gap-2 text-sm text-primary">
                <input type="checkbox" name="service_modes[]" value="{{ $cle }}" @checked(in_array($cle, (array) $modesChoisis, true)) class="rounded border-secondary/30">
                {{ $libelle }}
            </label>
        @endforeach
    </div>
    <p class="mt-1 text-[11px] text-primary/45">Au buffet : formule au couvert sur la carte, ou services de buffet ouverts pour un repas avec un prix d'entrée.</p>
</fieldset>

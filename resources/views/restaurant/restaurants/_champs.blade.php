{{-- Champs d'un restaurant, à la création comme à la modification. --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Nom</span>
        <input type="text" name="name" value="{{ old('name', $r?->name) }}" required maxlength="100" placeholder="Ex. Le Kotibe"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Code</span>
        <input type="text" name="code" value="{{ old('code', $r?->code) }}" required maxlength="16" placeholder="KOT"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm uppercase outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Préfixe des notes</span>
        <input type="text" name="series_prefix" value="{{ old('series_prefix', $r?->series_prefix) }}" maxlength="8" placeholder="KOT-"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
</div>
<fieldset>
    <legend class="text-xs text-primary/60">Modes de service</legend>
    <div class="mt-1.5 flex flex-wrap gap-4">
        @php $choisis = old('service_modes', $r?->service_modes ?: ['carte']); @endphp
        @foreach($modes as $cle => $libelle)
            <label class="flex items-center gap-2 text-sm text-primary">
                <input type="checkbox" name="service_modes[]" value="{{ $cle }}" @checked(in_array($cle, $choisis, true)) class="rounded border-secondary/30">
                {{ $libelle }}
            </label>
        @endforeach
    </div>
    <p class="mt-1 text-[11px] text-primary/45">Au buffet : formule au couvert sur la carte, ou services de buffet ouverts pour un repas avec un prix d'entrée.</p>
</fieldset>

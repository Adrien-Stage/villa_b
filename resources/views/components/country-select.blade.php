@props([
    'name'     => 'country',
    'value'    => null,
    'label'    => 'Pays de résidence',
    'required' => false,
])

{{--
    Saisie du pays d'un client. Volontairement sans valeur par défaut : le pays
    de résidence est la donnée qui alimente l'analyse des marchés émetteurs,
    pré-sélectionner un pays reviendrait à fabriquer de la donnée fausse dès que
    la réception valide sans regarder.
--}}
<div>
    <label class="block text-xs font-semibold text-primary/50 mb-1.5">
        {{ $label }}@if($required) *@endif
    </label>

    <select name="{{ $name }}"
            @if($required) required @endif
            {{ $attributes->merge(['class' => 'w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary bg-white']) }}>
        <option value="">— Sélectionner un pays —</option>
        @foreach(\App\Support\Countries::options() as $iso => $countryName)
            <option value="{{ $iso }}" @selected(old($name, $value) === $iso)>{{ $countryName }}</option>
        @endforeach
    </select>

    @error($name)<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
</div>

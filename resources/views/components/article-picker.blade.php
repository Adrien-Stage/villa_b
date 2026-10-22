{{--
    Choix d'un article par sa saisie : on tape le nom (ou la référence), la
    liste propose ce qui correspond. Remplace les menus déroulants d'articles
    (resources/js/article-picker.js).

    La liste vient soit de PHP, soit d'une expression Alpine du parent :
    - items       : liste PHP [['id' => …, 'name' => …, 'reference' => …, 'unit' => …], …]
    - source      : expression Alpine qui rend la liste (« articles », « portionsDu(restaurant) »)

    Le champ envoyé :
    - name        : nom du champ caché (formulaire ordinaire)
    - name-bind   : expression Alpine du nom (ligne d'une liste : `lines[${idx}][stock_item_id]`)
    - value       : identifiant choisi au départ (formulaire ordinaire)
    - model       : expression Alpine liée par x-model (« line.itemId »)
    - on-select   : expression Alpine jouée après un choix ($event.detail.article)

    L'affichage : libelle / detail (fonctions JS « a => … »), placeholder,
    required, input-id / id-bind, aria-label, size (sm | md).
--}}
@props([
    'items' => null,
    'source' => null,
    'name' => null,
    'nameBind' => null,
    'value' => '',
    'model' => null,
    'onSelect' => null,
    'libelle' => null,
    'detail' => null,
    'placeholder' => 'Tapez le nom ou la référence de l’article…',
    'required' => false,
    'inputId' => null,
    'idBind' => null,
    'ariaLabel' => null,
    'size' => 'md',
])

@php
    $liste = $source !== null ? "() => ({$source})" : '() => ' . \Illuminate\Support\Js::from(collect($items ?? [])->values()->all());
    $config = '{ source: ' . $liste
        . ', libelle: ' . ($libelle ?: 'null')
        . ', detail: ' . ($detail ?: 'null')
        . ', initial: ' . \Illuminate\Support\Js::from($value === null ? '' : $value)
        . ', obligatoire: ' . ($required ? 'true' : 'false') . ' }';
    $champ = $size === 'sm'
        ? 'w-full pl-7 pr-7 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary'
        : 'w-full pl-9 pr-8 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary';
@endphp

<div x-data="articlePicker({{ $config }})"
     @if($model) x-modelable="choisi" x-model="{{ $model }}" @endif
     @if($onSelect) @article-choisi="{{ $onSelect }}" @endif
     {{ $attributes->merge(['class' => 'relative']) }}>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center {{ $size === 'sm' ? 'pl-2' : 'pl-3' }} text-primary/35" aria-hidden="true">
            <svg class="{{ $size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
        </span>
        <input type="text" x-ref="saisie" x-model="saisie" autocomplete="off" spellcheck="false"
               @input="taper()" @focus="ouvrir()" @click="ouvert || ouvrir()" @keydown="clavier($event)"
               @blur="setTimeout(() => { if (document.activeElement !== $refs.saisie) fermer() }, 120)"
               role="combobox" aria-autocomplete="list" :aria-expanded="ouvert ? 'true' : 'false'" :aria-controls="$id('articles')"
               :aria-activedescendant="ouvert && resultats()[actif] ? $id('article', actif) : null"
               @if($inputId) id="{{ $inputId }}" @endif
               @if($idBind) :id="{{ $idBind }}" @endif
               @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
               @if($required) required @endif
               placeholder="{{ $placeholder }}"
               class="{{ $champ }}">
        <button type="button" x-show="saisie !== ''" x-cloak @mousedown.prevent @click="vider()"
                class="absolute inset-y-0 right-0 flex items-center {{ $size === 'sm' ? 'pr-2' : 'pr-2.5' }} text-primary/35 hover:text-primary"
                aria-label="Effacer l’article">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <input type="hidden" x-ref="valeur" @if($name) name="{{ $name }}" @endif @if($nameBind) :name="{{ $nameBind }}" @endif :value="choisi ?? ''">

    {{-- Hors de la fenêtre ou du tableau qui la contient : rien ne la coupe. --}}
    <template x-teleport="body">
        <ul x-show="ouvert" x-cloak x-ref="liste" role="listbox" :id="$id('articles')"
            :style="`position: fixed; top: ${position.top}px; left: ${position.left}px; width: ${position.width}px;`"
            class="z-[70] max-h-72 overflow-auto rounded-lg border border-secondary/25 bg-white py-1 text-sm shadow-xl">
            <template x-for="(a, i) in resultats()" :key="a.id">
                <li role="option" :id="$id('article', i)" :data-rang="i" :aria-selected="i === actif ? 'true' : 'false'"
                    @mousedown.prevent @click="choisir(a)" @mouseenter="actif = i"
                    :class="i === actif ? 'bg-accent/40' : ''"
                    class="cursor-pointer px-3 py-1.5">
                    <span class="block truncate font-medium text-primary" x-text="libelleDe(a)"></span>
                    <span x-show="detailDe(a)" class="block truncate text-xs text-primary/55" x-text="detailDe(a)"></span>
                </li>
            </template>
            <li x-show="resultats().length === 0" class="px-3 py-2 text-xs text-primary/50">Aucun article ne correspond à « <span x-text="saisie"></span> ».</li>
        </ul>
    </template>
</div>

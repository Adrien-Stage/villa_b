{{--
    En-tête de colonne.
    - align : left (défaut), right, center
    - hide : masque la colonne quand le tableau est plus étroit que ce palier
      (sm 672 px … 3xl 1280 px) ; la cellule correspondante porte le même
      « hide ».
    - actions : colonne des actions de ligne (sans libellé visible).
--}}
@props(['align' => 'left', 'hide' => null, 'actions' => false])

@php
    $alignements = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'];
    // Paliers de largeur du tableau (container query), proches des points de
    // rupture d'écran : sm 672 px, md 768, lg 896, xl 1024, 2xl 1152, 3xl 1280.
    // Classes écrites en entier : Tailwind ne voit que ce qui est écrit.
    $masques = [
        'sm' => 'hidden @2xl:table-cell', 'md' => 'hidden @3xl:table-cell', 'lg' => 'hidden @4xl:table-cell',
        'xl' => 'hidden @5xl:table-cell', '2xl' => 'hidden @6xl:table-cell', '3xl' => 'hidden @7xl:table-cell',
    ];
@endphp

<th scope="col" {{ $attributes->class([
    'whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-primary/55',
    $alignements[$actions ? 'right' : $align] ?? 'text-left',
    $masques[$hide] ?? '' => $hide !== null,
    'sticky right-0 w-px bg-[color-mix(in_oklab,var(--color-accent)_20%,white)]' => $actions,
]) }}>
    @if($actions)
        <span class="sr-only">{{ $slot->isEmpty() ? 'Actions' : $slot }}</span>
    @else
        {{ $slot }}
    @endif
</th>

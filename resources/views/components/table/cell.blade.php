{{--
    Cellule. align : left (défaut), right, center ; hide : comme la colonne ;
    nowrap : sans retour à la ligne (montants, dates).
--}}
@props(['align' => 'left', 'hide' => null, 'nowrap' => false])

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

<td {{ $attributes->class([
    'px-4 py-3 align-middle text-primary',
    $alignements[$align] ?? 'text-left',
    'whitespace-nowrap' => $nowrap,
    $masques[$hide] ?? '' => $hide !== null,
]) }}>
    {{ $slot }}
</td>

{{--
    Cellule. align : left (défaut), right, center ; hide : comme la colonne ;
    nowrap : sans retour à la ligne (montants, dates).
--}}
@props(['align' => 'left', 'hide' => null, 'nowrap' => false])

@php
    $alignements = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'];
    $masques = [
        'sm' => 'hidden @sm:table-cell', 'md' => 'hidden @md:table-cell', 'lg' => 'hidden @lg:table-cell',
        'xl' => 'hidden @xl:table-cell', '2xl' => 'hidden @2xl:table-cell', '3xl' => 'hidden @3xl:table-cell',
        '4xl' => 'hidden @4xl:table-cell',
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

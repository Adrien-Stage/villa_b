{{--
    En-tête de colonne.
    - align : left (défaut), right, center
    - hide : masque la colonne quand le tableau est plus étroit que cette
      taille (sm … 3xl) ; la cellule correspondante porte le même « hide ».
    - actions : colonne des actions de ligne (sans libellé visible).
--}}
@props(['align' => 'left', 'hide' => null, 'actions' => false])

@php
    $alignements = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'];
    $masques = [
        'sm' => 'hidden @sm:table-cell', 'md' => 'hidden @md:table-cell', 'lg' => 'hidden @lg:table-cell',
        'xl' => 'hidden @xl:table-cell', '2xl' => 'hidden @2xl:table-cell', '3xl' => 'hidden @3xl:table-cell',
        '4xl' => 'hidden @4xl:table-cell',
    ];
@endphp

<th scope="col" {{ $attributes->class([
    'whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-primary/55',
    $alignements[$actions ? 'right' : $align] ?? 'text-left',
    $masques[$hide] ?? '' => $hide !== null,
    'w-px' => $actions,
]) }}>
    @if($actions)
        <span class="sr-only">{{ $slot->isEmpty() ? 'Actions' : $slot }}</span>
    @else
        {{ $slot }}
    @endif
</th>

{{--
    Actions d'une ligne. En boutons quand le tableau a la place, dans un menu
    ⋮ sinon (prop « inline » du tableau). Le contenu — des <x-table.action> —
    est rendu dans les deux présentations ; une seule est visible.
--}}
@aware(['inline' => 'xl'])
@props(['label' => 'Actions'])

@php
    // Mêmes paliers que « hide » (sm 672 px … 3xl 1280 px). Classes écrites
    // en entier : Tailwind ne voit que ce qui est écrit.
    $presentations = [
        'always' => ['flex', 'hidden'],
        'never' => ['hidden', 'inline-block'],
        'sm' => ['hidden @2xl:flex', 'inline-block @2xl:hidden'],
        'md' => ['hidden @3xl:flex', 'inline-block @3xl:hidden'],
        'lg' => ['hidden @4xl:flex', 'inline-block @4xl:hidden'],
        'xl' => ['hidden @5xl:flex', 'inline-block @5xl:hidden'],
        '2xl' => ['hidden @6xl:flex', 'inline-block @6xl:hidden'],
        '3xl' => ['hidden @7xl:flex', 'inline-block @7xl:hidden'],
    ];
    [$enLigne, $enMenu] = $presentations[$inline] ?? $presentations['xl'];
@endphp

{{-- Collée à droite : quand le tableau défile (téléphone), ses actions restent à portée. --}}
<td {{ $attributes->class(['sticky right-0 w-px whitespace-nowrap bg-white px-4 py-2 text-right align-middle transition-colors group-hover:bg-[color-mix(in_oklab,var(--color-accent)_10%,white)]']) }}>
    <div class="dt-inline {{ $enLigne }} items-center justify-end gap-2">
        {{ $slot }}
    </div>

    <x-menu-actions :label="$label" class="{{ $enMenu }}">{{ $slot }}</x-menu-actions>
</td>

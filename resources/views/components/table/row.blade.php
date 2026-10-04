{{--
    Ligne du tableau.
    - muted : estompe la ligne (élément inactif, annulé).
    - href : la ligne entière ouvre cette adresse au clic, sauf sur un lien,
      un bouton ou un champ qu'elle contient. Pour le clavier, la première
      cellule porte aussi un vrai lien vers la même adresse.
--}}
@props(['muted' => false, 'href' => null])

<tr {{ $attributes->class([
    'group transition-colors hover:bg-accent/10',
    'cursor-pointer' => $href !== null,
    'opacity-60' => $muted,
]) }}
    @if($href)
        data-href="{{ $href }}"
        onclick="if (! event.target.closest('a, button, input, select, textarea, label, form')) window.location.href = this.dataset.href"
    @endif>
    {{ $slot }}
</tr>

{{--
    Une action de ligne : un lien (href), un formulaire (action, method), ou
    un bouton (onclick et autres attributs passés tels quels).

    - icon : icône Lucide ; tone : default, danger, success
    - confirm : question posée avant d'agir
    - fields : champs cachés du formulaire, [nom => valeur]
    - form-class : classes du formulaire (expect-popup…)
    Son aspect suit la présentation : bouton en ligne, entrée du menu ⋮.
--}}
@props([
    'href' => null,
    'action' => null,
    'method' => 'POST',
    'icon' => null,
    'tone' => 'default',
    'confirm' => null,
    'fields' => [],
    'target' => null,
    'formClass' => null,
])

@php
    $verbe = strtoupper($method);
    $contenu = ($icon ? '<i data-lucide="'.e($icon).'" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"></i>' : '').'<span>'.$slot.'</span>';
@endphp

@if($action)
    <form method="POST" action="{{ $action }}" class="dt-action-form {{ $formClass }}"
        @if($confirm) onsubmit="return confirm(@js($confirm))" @endif>
        @csrf
        @if($verbe !== 'POST')
            @method($verbe)
        @endif
        @foreach($fields as $nom => $valeur)
            <input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">
        @endforeach
        <button type="submit" {{ $attributes->class(['dt-action'])->merge(['data-tone' => $tone]) }}>{!! $contenu !!}</button>
    </form>
@elseif($href)
    <a href="{{ $href }}" @if($target) target="{{ $target }}" @endif
        @if($confirm) onclick="return confirm(@js($confirm))" @endif
        {{ $attributes->class(['dt-action'])->merge(['data-tone' => $tone]) }}>{!! $contenu !!}</a>
@else
    <button type="button"
        @if($confirm) onclick="if (!confirm(@js($confirm))) { event.stopImmediatePropagation(); return false; }" @endif
        {{ $attributes->class(['dt-action'])->merge(['data-tone' => $tone]) }}>{!! $contenu !!}</button>
@endif

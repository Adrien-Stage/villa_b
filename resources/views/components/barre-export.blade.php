@props([
    // Route qui sert l'export. Les filtres courants sont repris tels quels,
    // pour que le papier rende ce que l'écran affiche.
    'route',
    'parametres' => [],
])

@php
    $formats = \App\Services\DocumentExporter::FORMATS;
    $icones  = ['impression' => 'printer', 'pdf' => 'file-text', 'excel' => 'sheet', 'word' => 'file-type'];
    $filtres = array_filter(array_merge(request()->query(), $parametres),
        fn ($v, $k) => $k !== 'format' && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);
@endphp

{{--
    Barre d'export commune. Chaque écran qui produit une liste la pose telle
    quelle : les quatre sorties restent au même endroit, dans le même ordre, et
    l'utilisateur n'a pas à réapprendre l'interface d'un module à l'autre.
--}}
<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-lg border border-secondary/30 bg-white p-1']) }}>
    @foreach($formats as $cle => $libelle)
        <a href="{{ route($route, array_merge($filtres, ['format' => $cle])) }}"
           @if($cle === \App\Services\DocumentExporter::FORMAT_IMPRESSION) target="_blank" rel="noopener" @endif
           title="{{ $libelle }}"
           class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-primary/70 transition-colors hover:bg-accent/30 hover:text-primary">
            <i data-lucide="{{ $icones[$cle] ?? 'download' }}" class="h-3.5 w-3.5"></i>
            <span class="hidden sm:inline">{{ $libelle }}</span>
        </a>
    @endforeach
</div>

@php
    $lignes = $document->lesLignes();
    $colonnes = $document->lesColonnes();
    $totaux = $document->lesTotaux();
@endphp

{{-- Aperçu à l'écran : le même document que celui qui s'imprime. --}}
<x-table
    :rows="$lignes"
    inline="never"
    empty="Rien pour ces filtres."
    empty-icon="file-search"
    :caption="$document->titre">
    <x-slot:toolbar>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-primary">{{ $document->titre }}</p>
            @if($document->leSousTitre())<p class="text-xs text-primary/55">{{ $document->leSousTitre() }}</p>@endif
            <p class="mt-1 flex flex-wrap gap-1.5">
                @foreach($document->lesFiltres() as $libelle => $valeurFiltre)
                    <span class="rounded-md border border-secondary/20 bg-accent/15 px-2 py-0.5 text-[11px] text-primary/70"><b class="font-semibold text-primary">{{ $libelle }}</b> : {{ $valeurFiltre }}</span>
                @endforeach
            </p>
        </div>
        <span class="text-xs text-primary/50">{{ $lignes->count() }} ligne(s)</span>
    </x-slot:toolbar>
    <x-slot:head>
        @foreach($colonnes as $colonne)
            <x-table.col :align="$colonne->alignementDroite() ? 'right' : 'left'">{{ $colonne->libelle }}</x-table.col>
        @endforeach
    </x-slot:head>
    @foreach($lignes->take($apercu) as $ligne)
        <x-table.row>
            @foreach($colonnes as $colonne)
                <x-table.cell :align="$colonne->alignementDroite() ? 'right' : 'left'" :nowrap="$colonne->alignementDroite()"
                              class="{{ $loop->first ? 'font-medium text-primary' : 'text-primary/75' }}">{{ $colonne->formater($document->valeur($ligne, $colonne), $devise) }}</x-table.cell>
            @endforeach
        </x-table.row>
    @endforeach
    @if($totaux !== [] && $lignes->isNotEmpty())
        <x-slot:foot>
            <tr class="border-t-2 border-primary/20 bg-accent/15 font-semibold text-primary">
                @foreach($colonnes as $colonne)
                    <td class="px-4 py-2.5 text-sm {{ $colonne->alignementDroite() ? 'text-right whitespace-nowrap' : '' }}">
                        @if($loop->first && ! array_key_exists($colonne->cle, $totaux))
                            Total
                        @elseif(array_key_exists($colonne->cle, $totaux))
                            {{ $colonne->formater($totaux[$colonne->cle], $devise) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        </x-slot:foot>
    @endif
</x-table>

@if($lignes->count() > $apercu)
    <p class="mt-2 text-xs text-primary/55">L'écran montre les {{ $apercu }} premières lignes ; l'impression et les exports les contiennent toutes.</p>
@endif
@if($document->laNote())
    <p class="mt-3 rounded-lg border border-secondary/20 bg-accent/10 px-4 py-3 text-xs
        text-primary/70">{{ $document->laNote() }}</p>
@endif

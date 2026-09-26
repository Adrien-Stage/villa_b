{{--
    Gabarit commun à tous les documents imprimables de l'application.

    Le même fichier sert l'aperçu à l'écran, l'impression navigateur et le PDF.
    Trois rendus d'un seul gabarit : un en-tête corrigé ici se corrige partout,
    et l'établissement ne sort pas des papiers qui ne se ressemblent pas.

    Styles en ligne, sans Tailwind : dompdf ne connaît ni les feuilles
    compilées ni les variables CSS, et un document doit s'imprimer à
    l'identique depuis le navigateur comme depuis le serveur.
--}}
@php
    $etab    = $document->enTeteEtablissement();
    $devise  = $etab['devise'] ?? 'FCFA';
    $colonnes = $document->lesColonnes();
    $pourPdf = $pourPdf ?? false;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $document->titre }}</title>
    <style>
        @page {
            margin: {{ $pourPdf ? '12mm 12mm 16mm' : '0mm !important' }};
        }

        * { font-family: DejaVu Sans, Arial, sans-serif; box-sizing: border-box; }
        body { margin: 0; color: #2b1a10; font-size: 11px; }

        .entete { display: table; width: 100%; border-bottom: 2px solid #391F0E; padding-bottom: 10px; margin-bottom: 14px; }
        .entete .bloc { display: table-cell; vertical-align: top; }
        .entete .logo { width: 68px; }
        .entete .logo img { max-width: 60px; max-height: 60px; }
        .etab-nom { font-size: 15px; font-weight: bold; color: #391F0E; letter-spacing: .3px; }
        .etab-ligne { font-size: 9.5px; color: #6b5744; margin-top: 2px; }
        .entete .droite { text-align: right; font-size: 9px; color: #8a7461; }

        h1 { font-size: 17px; margin: 0 0 3px; color: #391F0E; }
        .sous-titre { font-size: 10.5px; color: #6b5744; margin: 0 0 2px; }
        .periode { font-size: 10px; color: #8a7461; }

        .filtres { margin: 10px 0 12px; }
        .filtre { display: inline-block; border: 1px solid #e0d3c2; background: #faf5ee; border-radius: 3px;
                  padding: 2px 7px; margin: 0 4px 4px 0; font-size: 9px; color: #6b5744; }
        .filtre b { color: #391F0E; font-weight: 600; }

        table.donnees { width: 100%; border-collapse: collapse; }
        table.donnees thead th { background: #391F0E; color: #EED4A3; text-align: left; padding: 6px 8px;
                                 font-size: 9px; text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
        table.donnees tbody td { padding: 5px 8px; border-bottom: 1px solid #ece3d6; font-size: 10px; }
        table.donnees tbody tr:nth-child(even) td { background: #fdfaf6; }
        table.donnees tfoot td { padding: 7px 8px; border-top: 2px solid #391F0E; font-weight: bold;
                                 font-size: 10.5px; color: #391F0E; background: #faf5ee; }
        .droite { text-align: right; }
        .vide { padding: 28px; text-align: center; color: #a3917e; font-size: 11px; font-style: italic; }

        .note { margin-top: 12px; font-size: 9px; color: #8a7461; line-height: 1.5; }
        .pied { margin-top: 18px; padding-top: 8px; border-top: 1px solid #e0d3c2;
                font-size: 8.5px; color: #a3917e; display: table; width: 100%; }
        .pied .g { display: table-cell; }
        .pied .d { display: table-cell; text-align: right; }

        @media print {
            .sans-impression { display: none !important; }
            body { font-size: 10.5px; }
            table.donnees thead { display: table-header-group; }
            table.donnees tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="entete">
    @if(!empty($etab['logo']) && is_readable($etab['logo']))
        <div class="bloc logo">
            <img src="{{ $pourPdf ? $etab['logo'] : asset('storage/' . (auth()->user()?->tenant?->settings['logo'] ?? '')) }}"
                 alt="{{ $etab['nom'] ?? '' }}">
        </div>
    @endif

    <div class="bloc">
        <div class="etab-nom">{{ $etab['nom'] ?? 'Établissement' }}</div>
        @if(!empty($etab['adresse']))
            <div class="etab-ligne">{{ $etab['adresse'] }}</div>
        @endif
        @if(!empty($etab['tel']) || !empty($etab['email']))
            <div class="etab-ligne">{{ collect([$etab['tel'] ?? null, $etab['email'] ?? null])->filter()->implode(' · ') }}</div>
        @endif
    </div>

    <div class="bloc droite">
        Édité le {{ now()->format('d/m/Y à H:i') }}<br>
        par {{ auth()->user()?->name ?? '—' }}
    </div>
</div>

<h1>{{ $document->titre }}</h1>
@if($document->leSousTitre())
    <p class="sous-titre">{{ $document->leSousTitre() }}</p>
@endif
@if($document->laPeriode())
    <p class="periode">{{ $document->laPeriode() }}</p>
@endif

@if($document->lesFiltres())
    <div class="filtres">
        @foreach($document->lesFiltres() as $libelle => $valeur)
            <span class="filtre"><b>{{ $libelle }}</b> : {{ $valeur }}</span>
        @endforeach
    </div>
@endif

@if($document->estVide())
    <div class="vide">Aucune donnée pour ces critères.</div>
@else
    <table class="donnees">
        <thead>
            <tr>
                @foreach($colonnes as $colonne)
                    <th class="{{ $colonne->alignementDroite() ? 'droite' : '' }}">{{ $colonne->libelle }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($document->lesLignes() as $ligne)
                <tr>
                    @foreach($colonnes as $colonne)
                        <td class="{{ $colonne->alignementDroite() ? 'droite' : '' }}">
                            {{ $colonne->formater($document->valeur($ligne, $colonne), $devise) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>

        @if($document->lesTotaux())
            <tfoot>
                <tr>
                    @foreach($colonnes as $index => $colonne)
                        <td class="{{ $colonne->alignementDroite() ? 'droite' : '' }}">
                            @if($index === 0)
                                Total — {{ $document->lesLignes()->count() }} ligne(s)
                            @elseif(array_key_exists($colonne->cle, $document->lesTotaux()))
                                {{ $colonne->formater($document->lesTotaux()[$colonne->cle], $devise) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
@endif

@if($document->laNote())
    <p class="note">{{ $document->laNote() }}</p>
@endif

<div class="pied">
    <div class="g">{{ $etab['nom'] ?? '' }} — {{ $document->titre }}</div>
    <div class="d">{{ $document->lesLignes()->count() }} ligne(s)</div>
</div>

</body>
</html>

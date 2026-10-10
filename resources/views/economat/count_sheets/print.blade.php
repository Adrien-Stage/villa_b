<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiches de comptage — {{ $tenant?->name ?? 'Établissement' }}</title>
    <style>
        @include('partials.impression', ['haut' => '10mm', 'bas' => '12mm', 'cotes' => '10mm'])

        * { box-sizing: border-box; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; }

        body { margin: 0; color: #1a1a1a; font-size: 10px; line-height: 1.35; background: #f1f5f9; }

        .toolbar {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            max-width: 820px; margin: 16px auto; padding: 10px 16px;
            background: #1e293b; color: #fff; border-radius: 8px; font-size: 12px;
        }
        .toolbar button {
            background: #fff; color: #1e293b; border: 0; border-radius: 6px;
            padding: 7px 14px; font-weight: 600; cursor: pointer;
        }
        .toolbar button:focus-visible { outline: 2px solid #fbbf24; outline-offset: 2px; }

        .sheet {
            max-width: 820px; margin: 0 auto 24px; padding: 24px;
            background: #fff; border-radius: 8px;
        }

        /* L'en-tête de la fiche est dans l'en-tête du tableau : il revient en
           haut de chaque page, et une page séparée des autres dit encore
           quel lieu elle compte et pour quel inventaire. */
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 4px 5px; vertical-align: middle; }
        th { background: #e2e8f0; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; text-align: left; }
        th.entete, td.visa { border: 0; background: none; padding: 0; font-size: 10px; text-transform: none; letter-spacing: 0; font-weight: 400; }
        th.entete { padding-bottom: 8px; }

        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; }
        .head h1 { font-size: 15px; margin: 2px 0; text-transform: uppercase; letter-spacing: .04em; }
        .head .etab { font-size: 11px; font-weight: 700; }
        .head .identite { font-size: 8.5px; color: #555; }
        .head .meta { text-align: right; font-size: 10px; white-space: nowrap; }
        .head .meta div { margin-bottom: 3px; }
        .blank { display: inline-block; min-width: 110px; border-bottom: 1px solid #555; }

        .ref { display: inline-block; padding: 2px 6px; border: 1px solid #1a1a1a; font-weight: 700; margin-top: 4px; }
        .fige { font-size: 9px; color: #555; margin-left: 4px; }

        .consigne { margin-top: 6px; padding: 3px 6px; border-left: 3px solid #1a1a1a; background: #f8fafc; font-size: 9px; }

        td.num, th.num { text-align: right; white-space: nowrap; }
        td.ref-article { white-space: nowrap; }
        td.fill { height: 20px; }
        tr.group td { background: #f1f5f9; font-weight: 700; font-size: 10px; }
        tr.group .nombre { font-weight: 400; color: #555; }
        .muted { color: #555; }
        /* Un stock négatif est une anomalie de saisie : le contrôleur la vérifie sur place. */
        td.negatif { font-weight: 700; text-decoration: underline; }

        /* Visa en bas de chaque page : une page signée ne s'échange pas. */
        td.visa { padding-top: 6px; }
        .visas { display: flex; justify-content: flex-end; gap: 24px; font-size: 9px; }
        .visas span { display: inline-block; min-width: 150px; border-bottom: 1px solid #555; padding-bottom: 10px; }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 14px; break-inside: avoid; }
        .signatures div { border: 1px solid #555; height: 62px; padding: 4px 6px; font-size: 9px; }

        .empty { padding: 24px; text-align: center; color: #555; border: 1px dashed #999; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { max-width: none; margin: 0; padding: 0; border-radius: 0; break-after: page; }
            .sheet:last-child { break-after: auto; }
            tr { break-inside: avoid; }
            tr.group { break-after: avoid; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        }
        .detail-conditionnement { font-size: 8px; color: #475569; margin-top: 2px; }
    </style>
</head>
<body>
    @php
        $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
        $colonnes = $showTheoretical ? 8 : 6;
        $identite = collect([$tenant?->address, $tenant?->phone, $tenant?->email])->filter()->implode(' · ');
        $imprimeeLe = now()->format('d/m/Y à H:i');
    @endphp

    <div class="toolbar">
        <span>{{ count($sheets) }} fiche(s) de comptage {{ $showTheoretical ? 'avec stock théorique' : 'à l’aveugle' }}</span>
        <button type="button" onclick="window.print()">Imprimer</button>
    </div>

    @forelse($sheets as $sheet)
        @php $articles = collect($sheet['groups'])->sum(fn ($lignes) => count($lignes)); @endphp
        <section class="sheet">
            <table>
                <thead>
                    <tr>
                        <th class="entete" colspan="{{ $colonnes }}">
                            <div class="head">
                                <div>
                                    <div class="etab">{{ $tenant?->name ?? 'Établissement' }}</div>
                                    @if($identite !== '')<div class="identite">{{ $identite }}</div>@endif
                                    <h1>Fiche de comptage — {{ $sheet['title'] }}</h1>
                                    @if($sheet['subtitle'])<div class="muted">{{ $sheet['subtitle'] }}</div>@endif
                                    @if($sheet['selection'] ?? null)<div class="muted">{{ $sheet['selection'] }}</div>@endif
                                    <div>
                                        @if($sheet['reference'])<span class="ref">Inventaire {{ $sheet['reference'] }}</span>@endif
                                        <span class="fige">
                                            {{ $articles }} article(s)
                                            @if($sheet['frozen_at'])
                                                · {{ $showTheoretical ? 'stock théorique figé' : 'inventaire ouvert' }} le {{ $sheet['frozen_at']->format('d/m/Y à H:i') }}
                                            @elseif($showTheoretical)
                                                · stock théorique relevé le {{ $imprimeeLe }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="meta">
                                    <div>Imprimée le {{ $imprimeeLe }}@if($printedBy) par {{ $printedBy }}@endif</div>
                                    <div>Date du comptage : <span class="blank"></span></div>
                                    <div>Heure début / fin : <span class="blank"></span></div>
                                </div>
                            </div>
                            <div class="consigne">
                                Compter ce qui est réellement présent, dans l'unité de la ligne. Écrire 0 pour un article absent : aucune case ne reste vide.
                                Une correction se raye et se paraphe, sans surcharge.
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th style="width:28px">N°</th>
                        <th style="width:70px">Réf.</th>
                        <th>Article</th>
                        <th style="width:52px">Unité</th>
                        @if($showTheoretical)<th class="num" style="width:62px">Théorique</th>@endif
                        <th class="num" style="width:80px">Compté</th>
                        @if($showTheoretical)<th class="num" style="width:56px">Écart</th>@endif
                        <th style="width:130px">Observations</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <td class="visa" colspan="{{ $colonnes }}">
                            <div class="visas">
                                <span>Visa du compteur</span>
                                <span>Visa du contrôleur</span>
                            </div>
                        </td>
                    </tr>
                </tfoot>
                <tbody>
                    @php $numero = 0; @endphp
                    @forelse($sheet['groups'] as $categorie => $lignes)
                        <tr class="group"><td colspan="{{ $colonnes }}">{{ $categorie }} <span class="nombre">· {{ count($lignes) }}</span></td></tr>
                        @foreach($lignes as $ligne)
                            @php $numero++; @endphp
                            <tr>
                                <td class="num">{{ $numero }}</td>
                                <td class="muted ref-article">{{ $ligne['reference'] }}</td>
                                <td>
                                    {{ $ligne['name'] }}
                                    @if(!empty($ligne['conditionnements']))
                                        <div class="detail-conditionnement">
                                            @foreach($ligne['conditionnements'] as $nom){{ \App\Support\Conditionnement::accorde($nom, 2) }} fermés …… · @endforeach{{ \App\Support\Conditionnement::accorde((string) $ligne['unit'], 2) }} en vrac ……
                                        </div>
                                    @endif
                                </td>
                                <td class="muted">{{ $ligne['unit'] }}</td>
                                @if($showTheoretical)<td class="num {{ $ligne['theoretical'] < 0 ? 'negatif' : '' }}">{{ $qte($ligne['theoretical']) }}</td>@endif
                                <td class="fill"></td>
                                @if($showTheoretical)<td class="fill"></td>@endif
                                <td class="fill"></td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="{{ $colonnes }}" class="empty">Aucun article à compter dans ce service.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="signatures">
                <div>Compté par (nom, signature)</div>
                <div>Contrôlé par (économat)</div>
                <div>Responsable du service</div>
            </div>
        </section>
    @empty
        <section class="sheet"><p class="empty">Aucune fiche à imprimer.</p></section>
    @endforelse
</body>
</html>

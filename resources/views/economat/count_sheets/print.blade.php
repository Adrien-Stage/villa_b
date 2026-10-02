<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiches de comptage — {{ $tenant?->name ?? 'Établissement' }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm 14mm 10mm; }

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

        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; margin-bottom: 10px; }
        .head h1 { font-size: 15px; margin: 0 0 2px; text-transform: uppercase; letter-spacing: .04em; }
        .head .etab { font-size: 11px; font-weight: 700; }
        .head .meta { text-align: right; font-size: 10px; }
        .head .meta div { margin-bottom: 2px; }
        .blank { display: inline-block; min-width: 110px; border-bottom: 1px solid #555; }

        .ref { display: inline-block; padding: 2px 6px; border: 1px solid #1a1a1a; font-weight: 700; margin-top: 4px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #555; padding: 4px 5px; vertical-align: middle; }
        th { background: #e2e8f0; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.fill { height: 20px; }
        tr.group td { background: #f1f5f9; font-weight: 700; font-size: 10px; }
        .muted { color: #555; }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 14px; }
        .signatures div { border: 1px solid #555; height: 62px; padding: 4px 6px; font-size: 9px; }

        .empty { padding: 24px; text-align: center; color: #555; border: 1px dashed #999; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { max-width: none; margin: 0; padding: 0; border-radius: 0; break-after: page; }
            .sheet:last-child { break-after: auto; }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    @php
        $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
    @endphp

    <div class="toolbar">
        <span>{{ count($sheets) }} fiche(s) de comptage {{ $showTheoretical ? 'avec stock théorique' : 'à l’aveugle' }}</span>
        <button type="button" onclick="window.print()">Imprimer</button>
    </div>

    @forelse($sheets as $sheet)
        <section class="sheet">
            <div class="head">
                <div>
                    <div class="etab">{{ $tenant?->name ?? 'Établissement' }}</div>
                    <h1>Fiche de comptage — {{ $sheet['title'] }}</h1>
                    @if($sheet['subtitle'])<div class="muted">{{ $sheet['subtitle'] }}</div>@endif
                    @if($sheet['reference'])<div class="ref">Inventaire {{ $sheet['reference'] }}</div>@endif
                </div>
                <div class="meta">
                    <div>Imprimée le {{ now()->format('d/m/Y à H:i') }}</div>
                    <div>Date du comptage : <span class="blank"></span></div>
                    <div>Heure début / fin : <span class="blank"></span></div>
                </div>
            </div>

            @if(empty($sheet['groups']))
                <p class="empty">Aucun article à compter dans ce service.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width:28px">N°</th>
                            <th style="width:70px">Réf.</th>
                            <th>Article</th>
                            <th style="width:52px">Unité</th>
                            @if($showTheoretical)<th class="num" style="width:62px">Théorique</th>@endif
                            <th class="num" style="width:72px">Compté</th>
                            @if($showTheoretical)<th class="num" style="width:56px">Écart</th>@endif
                            <th style="width:130px">Observations</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $numero = 0; @endphp
                        @foreach($sheet['groups'] as $categorie => $lignes)
                            <tr class="group"><td colspan="{{ $showTheoretical ? 8 : 6 }}">{{ $categorie }}</td></tr>
                            @foreach($lignes as $ligne)
                                @php $numero++; @endphp
                                <tr>
                                    <td class="num">{{ $numero }}</td>
                                    <td class="muted">{{ $ligne['reference'] }}</td>
                                    <td>{{ $ligne['name'] }}</td>
                                    <td class="muted">{{ $ligne['unit'] }}</td>
                                    @if($showTheoretical)<td class="num">{{ $qte($ligne['theoretical']) }}</td>@endif
                                    <td class="fill"></td>
                                    @if($showTheoretical)<td class="fill"></td>@endif
                                    <td class="fill"></td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif

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

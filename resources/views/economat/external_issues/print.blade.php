<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bon de sortie {{ $sortie->number }} — {{ $tenant?->name ?? 'Établissement' }}</title>
    @php
        $fcfa = fn (int $v) => number_format($v / 100, 0, ',', ' ');
        $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
        $identite = collect([$tenant?->address, $tenant?->phone, $tenant?->email])->filter()->implode(' · ');
    @endphp
    <style>
        @include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => 'Bon de sortie hors établissement '.$sortie->number])

        @font-face {
            font-family: 'Qwigley';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('/fonts/Qwigley-Regular.woff2') format('woff2'), url('/fonts/Qwigley-Regular.ttf') format('truetype');
        }

        * { box-sizing: border-box; font-family: DejaVu Sans, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        body { margin: 0; color: #1a1a1a; font-size: 10px; line-height: 1.45; background: #f8fafc; }
        .page { max-width: 850px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); }
        .no-print { display: flex; justify-content: space-between; align-items: center; max-width: 850px; margin: 0 auto 15px; padding: 10px 16px; background: #1e293b; color: #fff; border-radius: 8px; }
        .no-print button { background: #2563eb; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .no-print a { color: #cbd5e1; text-decoration: none; font-size: 12px; }
        .entete { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #334155; padding-bottom: 12px; margin-bottom: 14px; }
        .etab { font-size: 14px; font-weight: 700; color: #0f172a; }
        .muted { color: #64748b; font-size: 9px; }
        h1 { font-size: 15px; margin: 6px 0 2px; text-transform: uppercase; letter-spacing: .04em; color: #0f172a; }
        .numero { font-family: DejaVu Sans Mono, monospace; font-size: 13px; font-weight: 700; text-align: right; }
        .annule { display: inline-block; margin-top: 4px; padding: 2px 8px; border: 1px solid #991b1b; color: #991b1b; font-weight: 700; text-transform: uppercase; font-size: 9px; }
        .grille { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
        .cadre { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 10px; }
        .cadre h2 { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #475569; margin: 0 0 4px; }
        .cadre p { margin: 1px 0; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lignes th { background: #f1f5f9; text-align: left; font-size: 8.5px; text-transform: uppercase; color: #475569; padding: 6px; border-bottom: 1px solid #cbd5e1; }
        table.lignes td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; font-family: DejaVu Sans Mono, monospace; white-space: nowrap; }
        tfoot td { font-weight: 700; border-top: 2px solid #334155; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 26px; page-break-inside: avoid; }
        .signatures td { width: 33.33%; border: 1px solid #cbd5e1; padding: 10px; vertical-align: top; height: 100px; }
        .sig-titre { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #334155; }
        .sig-main { font-family: 'Qwigley', cursive, 'Brush Script MT', sans-serif; font-size: 32px; line-height: 1; color: #1e3a8a; display: inline-block; transform: rotate(-3deg); margin: 6px 0 2px 4px; }
        .mention { margin-top: 14px; font-size: 8.5px; color: #475569; }
        @media print {
            body { background: #fff; }
            .page { max-width: 100%; margin: 0; padding: 0; box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <span style="font-weight:600;font-size:13px;">Aperçu avant impression — Bon de sortie {{ $sortie->number }}</span>
        <span style="display:flex;gap:10px;align-items:center;">
            <button type="button" onclick="window.print()">Imprimer le bon</button>
            <a href="javascript:window.close()">Fermer</a>
        </span>
    </div>

    <div class="page">
        <div class="entete">
            <div>
                <div class="etab">{{ $tenant?->name ?? 'Établissement' }}</div>
                @if($identite !== '')<div class="muted">{{ $identite }}</div>@endif
                <h1>Bon de sortie hors établissement</h1>
                <div class="muted">Matériel sorti du magasin central sans servir l'établissement</div>
            </div>
            <div class="numero">
                {{ $sortie->number }}
                <div class="muted" style="font-weight:400">Sorti le {{ $sortie->issued_at->format('d/m/Y à H:i') }}</div>
                @if($sortie->isCancelled())<div class="annule">Annulé</div>@endif
            </div>
        </div>

        <div class="grille">
            <div class="cadre">
                <h2>Emporté par</h2>
                <p><strong>{{ $sortie->beneficiary_name }}</strong></p>
                @if($sortie->beneficiary_organisation)<p>{{ $sortie->beneficiary_organisation }}</p>@endif
                <p>Téléphone : {{ $sortie->beneficiary_phone ?? '—' }}</p>
                <p>Pièce d'identité : {{ $sortie->beneficiary_id_document ?? '—' }}</p>
            </div>
            <div class="cadre">
                <h2>Motif</h2>
                <p><strong>{{ $sortie->reasonLabel() }}</strong></p>
                @if($sortie->expected_return_at)<p>Retour prévu le {{ $sortie->expected_return_at->format('d/m/Y') }}</p>@endif
                @if($sortie->notes)<p class="muted" style="font-size:9.5px">{{ $sortie->notes }}</p>@endif
                <p>Validé par : {{ $sortie->issuedBy?->name ?? '—' }}</p>
            </div>
        </div>

        <table class="lignes">
            <thead>
                <tr>
                    <th style="width:24px">#</th>
                    <th style="width:80px">Référence</th>
                    <th>Article</th>
                    <th class="num">Quantité</th>
                    <th class="num">Coût unitaire</th>
                    <th class="num">Valeur</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sortie->lines as $i => $ligne)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="muted">{{ $ligne->item?->reference ?? '' }}</td>
                        <td>{{ $ligne->item?->name ?? '—' }}</td>
                        <td class="num">{{ $ligne->packaging_name ? $ligne->enConditionnement((float) $ligne->quantity) : $qte($ligne->quantity) . ' ' . $ligne->item?->unit }}</td>
                        <td class="num">{{ $fcfa((int) $ligne->unit_cost) }} F</td>
                        <td class="num">{{ $fcfa((int) $ligne->total_cost) }} F</td>
                        <td>{{ $ligne->notes ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Valeur totale au coût moyen</td>
                    <td class="num">{{ $fcfa((int) $sortie->total_value) }} F</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        @if($sortie->isCancelled())
            <p class="mention">Annulé le {{ $sortie->cancelled_at?->format('d/m/Y à H:i') }} par {{ $sortie->cancelledBy?->name ?? '—' }} : {{ $sortie->cancellation_reason }}. Le matériel est revenu en stock.</p>
        @endif

        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-titre">Le demandeur</div>
                    <div class="muted">Reconnaît avoir reçu le matériel ci-dessus</div>
                    <div class="sig-main">{{ $sortie->beneficiary_signature }}</div>
                    <div class="muted">{{ $sortie->beneficiary_name }}</div>
                </td>
                <td>
                    <div class="sig-titre">L'économe</div>
                    <div class="muted">Sortie validée</div>
                    <div class="sig-main">{{ $sortie->issuer_signature }}</div>
                    <div class="muted">{{ $sortie->issuedBy?->name ?? '' }} — le {{ $sortie->issued_at->format('d/m/Y') }}</div>
                </td>
                <td>
                    <div class="sig-titre">Sécurité / Contrôle à la sortie</div>
                    <div class="muted" style="margin-top:28px">Nom : ....................................</div>
                    <div class="muted">Date et signature :</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

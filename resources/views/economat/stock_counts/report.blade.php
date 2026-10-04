<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Procès-Verbal d'Inventaire — {{ $count->reference }}</title>
    <style>
        @include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => "PV d'Inventaire ".$count->reference.' — Magasin Central'])

        * {
            box-sizing: border-box;
            font-family: DejaVu Sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            color: #1a1a1a;
            font-size: 10px;
            line-height: 1.4;
            background: #f8fafc;
        }

        .page-container {
            max-width: 900px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Barre d'action supérieure (masquée à l'impression) */
        .no-print {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 900px;
            margin: 0 auto 15px auto;
            padding: 10px 16px;
            background: #1e293b;
            color: #fff;
            border-radius: 8px;
        }

        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-print:hover {
            background: #1d4ed8;
        }

        .btn-back {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 12px;
        }

        .btn-back:hover {
            color: #fff;
        }

        /* En-tête officiel */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #334155;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .etab-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .etab-sub {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        .doc-title-block {
            text-align: center;
            margin: 16px 0;
            padding: 10px;
            background: #f1f5f9;
            border-left: 4px solid #0f172a;
        }

        .doc-title {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doc-subtitle {
            font-size: 10px;
            color: #475569;
            margin-top: 3px;
        }

        /* Grille métadonnées */
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .meta-grid td {
            padding: 4px 8px;
            font-size: 9.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .meta-label {
            font-weight: 700;
            color: #475569;
            width: 22%;
        }

        .meta-val {
            color: #0f172a;
            width: 28%;
        }

        /* Tableau des données */
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.items-table thead th {
            background: #0f172a;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 6px;
            border: 1px solid #0f172a;
        }

        table.items-table tbody td {
            padding: 5px 6px;
            font-size: 9px;
            border: 1px solid #cbd5e1;
        }

        table.items-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-mono {
            font-family: "Courier New", Courier, monospace;
        }

        .text-red {
            color: #b91c1c;
            font-weight: bold;
        }

        .text-green {
            color: #15803d;
            font-weight: bold;
        }

        /* Bloc synthèse financière */
        .summary-box {
            margin-left: auto;
            width: 320px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 10px;
            font-size: 9.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-row.total {
            background: #0f172a;
            color: #ffffff;
            font-weight: 800;
            font-size: 11px;
            border-bottom: none;
        }

        /* Signatures contradictoires */
        .signatures-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signatures-table td {
            width: 50%;
            vertical-align: top;
            padding: 10px 20px;
            border: 1px solid #cbd5e1;
            background: #fafafa;
        }

        .signature-title {
            font-weight: 800;
            font-size: 10px;
            color: #0f172a;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }

        .signature-mention {
            font-size: 8.5px;
            color: #64748b;
            font-style: italic;
            margin-bottom: 45px;
        }

        .signature-name {
            font-size: 9.5px;
            font-weight: 700;
            color: #0f172a;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff;
                color: #000;
            }
            .page-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            table.items-table thead {
                display: table-header-group;
            }
            table.items-table tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <a href="{{ route('economat.stock_counts.show', $count) }}" class="btn-back">
        ← Retour à la feuille
    </a>
    <button onclick="window.print()" class="btn-print">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
        Imprimer le Procès-Verbal
    </button>
</div>

<div class="page-container">
    {{-- En-tête de l'établissement --}}
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="etab-name">{{ config('app.name', 'Établissement Hôtelier') }}</div>
                <div class="etab-sub">Département Contrôle de Gestion & Économat Central</div>
                <div class="etab-sub">Service de la Comptabilité Matière</div>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <div style="font-size: 11px; font-weight: 800; font-family: monospace;">RÉF : {{ $count->reference }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 3px;">Date d'édition : {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    {{-- Titre officiel du document --}}
    <div class="doc-title-block">
        <h1 class="doc-title">Procès-Verbal d'Inventaire Physique et d'Écarts de Stock</h1>
        <div class="doc-subtitle">Magasin Central (Économat) · Rapprochement contradictoire physique / théorique</div>
    </div>

    {{-- Grille des informations générales --}}
    <table class="meta-grid">
        <tr>
            <td class="meta-label">Numéro PV :</td>
            <td class="meta-val font-mono font-bold">{{ $count->reference }}</td>
            <td class="meta-label">Date du comptage :</td>
            <td class="meta-val font-mono">{{ $count->count_date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Périmètre / Catégorie :</td>
            <td class="meta-val">{{ $count->category?->name ?? 'Magasin complet (Tous articles)' }}</td>
            <td class="meta-label">Statut du PV :</td>
            <td class="meta-val">
                <strong>{{ $count->status === 'closed' ? 'Régularisé & Clôturé' : 'Provisoire' }}</strong>
            </td>
        </tr>
        <tr>
            <td class="meta-label">Auteur du comptage :</td>
            <td class="meta-val">{{ $count->openedBy?->name ?? 'Économe' }}</td>
            <td class="meta-label">Validé / Clôturé par :</td>
            <td class="meta-val">{{ $count->closedBy?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Tableau des articles --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 25px;">N°</th>
                <th style="text-align: left;">Désignation de l'article</th>
                <th style="width: 70px; text-align: left;">Catégorie</th>
                <th style="width: 45px; text-align: center;">Unité</th>
                <th style="width: 65px;" class="text-right">Stock Théo.</th>
                <th style="width: 65px;" class="text-right">Compté Réel</th>
                <th style="width: 60px;" class="text-right">Écart Qté</th>
                <th style="width: 70px;" class="text-right">CUMP</th>
                <th style="width: 75px;" class="text-right">Écart FCFA</th>
                <th style="width: 140px; text-align: left;">Motif & Justification</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $index => $line)
                @php
                    $theo = (float) $line->theoretical_quantity;
                    $counted = $line->isCounted() ? (float) $line->counted_quantity : null;
                    $varQty = (float) $line->variance_quantity;
                    $varVal = (int) $line->variance_value;
                    $cumpFCFA = (int) $line->unit_cost / 100;
                @endphp
                <tr>
                    <td class="text-center font-mono">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $line->item?->name ?? '—' }}</strong>
                    </td>
                    <td>{{ $line->item?->category?->name ?? 'Général' }}</td>
                    <td class="text-center">{{ $line->item?->unit }}</td>
                    <td class="text-right font-mono">{{ rtrim(rtrim(number_format($theo, 3, ',', ' '), '0'), ',') }}</td>
                    <td class="text-right font-mono">
                        {{ $counted !== null ? rtrim(rtrim(number_format($counted, 3, ',', ' '), '0'), ',') : '—' }}
                    </td>
                    <td class="text-right font-mono {{ $varQty < 0 ? 'text-red' : ($varQty > 0 ? 'text-green' : '') }}">
                        {{ $counted !== null ? ($varQty > 0 ? '+' : '') . rtrim(rtrim(number_format($varQty, 3, ',', ' '), '0'), ',') : '—' }}
                    </td>
                    <td class="text-right font-mono">{{ number_format($cumpFCFA, 0, ',', ' ') }} F</td>
                    <td class="text-right font-mono {{ $varVal < 0 ? 'text-red' : ($varVal > 0 ? 'text-green' : '') }}">
                        {{ $counted !== null ? ($varVal > 0 ? '+' : '') . number_format(round($varVal / 100), 0, ',', ' ') . ' F' : '—' }}
                    </td>
                    <td>
                        @if($line->reason)
                            <strong>{{ $line->reasonLabel() }}</strong>
                        @endif
                        @if($line->notes)
                            <span style="color: #64748b;">{{ $line->reason ? ' — ' : '' }}{{ $line->notes }}</span>
                        @endif
                        @if(!$line->reason && !$line->notes && abs($varQty) < 0.0005)
                            <span style="color: #94a3b8;">Conforme</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 20px; color: #94a3b8;">Aucun article dans cette feuille d'inventaire.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Synthèse chiffrée certifiée --}}
    <div class="summary-box">
        <div class="summary-row">
            <span>Valeur théorique avant inventaire :</span>
            <span class="font-mono font-bold">{{ number_format($count->total_theoretical_value / 100, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="summary-row">
            <span>Valeur physique constatée en rayon :</span>
            <span class="font-mono font-bold">{{ number_format($count->total_counted_value / 100, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="summary-row" style="color: #b91c1c;">
            <span>Total pertes / démarque constatée :</span>
            <span class="font-mono font-bold">−{{ number_format($count->loss_value / 100, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="summary-row" style="color: #15803d;">
            <span>Total excédents constatés :</span>
            <span class="font-mono font-bold">+{{ number_format($count->surplus_value / 100, 0, ',', ' ') }} FCFA</span>
        </div>
        @php $netVar = (int) $count->variance_value; @endphp
        <div class="summary-row total">
            <span>ÉCART NET RÉGULARISÉ :</span>
            <span class="font-mono">{{ $netVar > 0 ? '+' : ($netVar < 0 ? '−' : '') }}{{ number_format(abs($netVar) / 100, 0, ',', ' ') }} FCFA</span>
        </div>
    </div>

    {{-- Signatures contradictoires --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-title">L'Économe / Magasinier</div>
                <div class="signature-mention">« Certifie exact le comptage physique contradictoire effectué en magasin central. »</div>
                <div class="signature-name">Nom : {{ $count->openedBy?->name ?? '____________________' }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 4px;">Date et signature :</div>
            </td>
            <td>
                <div class="signature-title">Contrôle de Gestion / Direction</div>
                <div class="signature-mention">« Bon pour approbation et régularisation des écritures de stocks au journal. »</div>
                <div class="signature-name">Nom : {{ $count->closedBy?->name ?? '____________________' }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 4px;">Date et visa :</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>

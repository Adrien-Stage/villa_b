<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bordereau de Réception — {{ $receipt->number }}</title>
    <style>
        @include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => 'Bordereau de Réception '.$receipt->number.' — Magasin Central'])

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

        .btn-back {
            color: #94a3b8;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-back:hover {
            color: #fff;
        }

        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
        }

        .etab-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
        }

        .etab-sub {
            font-size: 9px;
            color: #475569;
            margin-top: 2px;
        }

        .doc-title-block {
            text-align: center;
            margin: 16px 0 20px 0;
            padding: 10px;
            background: #f1f5f9;
            border-radius: 4px;
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
            padding: 6px 8px;
            border: 1px solid #0f172a;
        }

        table.items-table tbody td {
            padding: 5px 8px;
            font-size: 9px;
            border: 1px solid #cbd5e1;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: DejaVu Sans Mono, Menlo, monospace; }
        .font-bold { font-weight: bold; }

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

        .signatures-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signatures-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            background: #fafafa;
        }

        .signature-title {
            font-weight: 800;
            font-size: 9.5px;
            color: #0f172a;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }

        .signature-mention {
            font-size: 8px;
            color: #64748b;
            font-style: italic;
            margin-bottom: 40px;
        }

        @import url('https://fonts.googleapis.com/css2?family=Qwigley&display=swap');

        @font-face {
            font-family: 'Qwigley';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('{{ asset('fonts/Qwigley-Regular.woff2') }}') format('woff2'), url('{{ asset('fonts/Qwigley-Regular.ttf') }}') format('truetype');
        }

        .sig-handwritten {
            font-family: 'Qwigley', cursive, 'Brush Script MT', sans-serif;
            font-size: 32px;
            line-height: 1;
            color: #1e3a8a;
            display: inline-block;
            transform: rotate(-3deg);
            padding-left: 6px;
            margin: 4px 0 2px 0;
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
    <a href="{{ route('economat.receipts.show', $receipt) }}" class="btn-back">
        ← Retour au bordereau
    </a>
    <button onclick="window.print()" class="btn-print">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
        Imprimer le Bordereau de Réception
    </button>
</div>

<div class="page-container">
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="etab-name">{{ $tenant?->name ?? config('app.name', 'Établissement Hôtelier') }}</div>
                <div class="etab-sub">Département Contrôle de Gestion & Économat Central</div>
                <div class="etab-sub">Service Réceptions & Comptabilité Matière</div>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <div style="font-size: 11px; font-weight: 800; font-family: monospace;">RÉF : {{ $receipt->number }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 3px;">Date d'édition : {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title-block">
        <h1 class="doc-title">Bon d'Entrée en Stock — Bordereau Officiel de Réception (BR)</h1>
        <div class="doc-subtitle">Contrôle contradictoire de livraison physique et intégration des marchandises au magasin</div>
    </div>

    <table class="meta-grid">
        <tr>
            <td class="meta-label">Numéro BR :</td>
            <td class="meta-val font-mono font-bold">{{ $receipt->number }}</td>
            <td class="meta-label">Date de réception :</td>
            <td class="meta-val font-mono">{{ $receipt->received_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Fournisseur :</td>
            <td class="meta-val"><strong>{{ $receipt->supplier?->name }}</strong></td>
            <td class="meta-label">N° BL Livreur :</td>
            <td class="meta-val font-mono">{{ $receipt->delivery_note_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Bon de commande lié :</td>
            <td class="meta-val font-mono">{{ $receipt->purchaseOrder?->number }}@if($receipt->purchaseOrder?->isRegularisation()) <span style="font-family: inherit;">(régularisation — réception directe)</span>@endif</td>
            <td class="meta-label">Réceptionné par :</td>
            <td class="meta-val">{{ $receipt->receivedBy?->name ?? 'Magasinier' }}</td>
        </tr>
        @if($receipt->notes)
            <tr>
                <td class="meta-label">Remarques / Chauffeur :</td>
                <td class="meta-val" colspan="3">{{ $receipt->notes }}</td>
            </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 28%; text-align: left;">Désignation de l'article</th>
                <th style="width: 10%; text-align: right;">Commandé</th>
                <th style="width: 10%; text-align: right;">Livré (BL)</th>
                <th style="width: 12%; text-align: right;">Admis (Stock)</th>
                <th style="width: 10%; text-align: right;">Refusé</th>
                <th style="width: 12%; text-align: right;">Prix Unitaire</th>
                <th style="width: 18%; text-align: right;">Montant Admis (TTC)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipt->lines as $line)
                <tr>
                    <td>
                        <div class="font-bold">{{ $line->item?->name ?? '—' }}</div>
                        <div style="font-size: 8px; color: #64748b;">Unité : {{ $line->item?->unit }}</div>
                        @if($line->quantity_rejected > 0)
                            <div style="font-size: 8px; color: #b91c1c; font-weight: bold; margin-top: 2px;">
                                Litige : {{ $line->rejectionReasonLabel() }} @if($line->notes) — {{ $line->notes }}@endif
                            </div>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }}</td>
                    <td class="text-right font-mono">{{ rtrim(rtrim(number_format($line->quantity_delivered, 3, ',', ' '), '0'), ',') }}</td>
                    <td class="text-right font-mono font-bold">{{ rtrim(rtrim(number_format($line->quantity_accepted, 3, ',', ' '), '0'), ',') }}</td>
                    <td class="text-right font-mono {{ $line->quantity_rejected > 0 ? 'text-red font-bold' : '' }}">
                        {{ rtrim(rtrim(number_format($line->quantity_rejected, 3, ',', ' '), '0'), ',') }}
                    </td>
                    <td class="text-right font-mono">{{ number_format($line->unit_cost / 100, 0, ',', ' ') }} F</td>
                    <td class="text-right font-mono font-bold">{{ number_format($line->total_cost / 100, 0, ',', ' ') }} F</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-row">
            <span>Nombre d'articles pointés :</span>
            <span class="font-mono font-bold">{{ $receipt->lines->count() }}</span>
        </div>
        <div class="summary-row">
            <span>Quantités totales acceptées :</span>
            <span class="font-mono font-bold">{{ rtrim(rtrim(number_format($receipt->totalAcceptedQuantity(), 3, ',', ' '), '0'), ',') }}</span>
        </div>
        @if($receipt->hasRejections())
            <div class="summary-row" style="color: #b91c1c;">
                <span>Quantités refusées (litige) :</span>
                <span class="font-mono font-bold">{{ rtrim(rtrim(number_format($receipt->totalRejectedQuantity(), 3, ',', ' '), '0'), ',') }}</span>
            </div>
        @endif
        <div class="summary-row total">
            <span>VALEUR ADMISE EN STOCK :</span>
            <span class="font-mono">{{ number_format($receipt->total_amount / 100, 0, ',', ' ') }} FCFA</span>
        </div>
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-title">Le Livreur / Fournisseur</div>
                <div class="signature-mention">« Bon pour livraison contradictoire »</div>
                <div class="signature-line">Nom & Signature :</div>
            </td>
            <td>
                <div class="signature-title">L'Économe / Magasinier Réceptionnaire</div>
                <div class="signature-mention">« Marchandises admises en stock — Bon d'entrée validé »</div>
                <div style="margin-top: 4px; font-weight: bold; color: #0f172a; font-size: 9.5px;">
                    Nom : {{ $receipt->receivedBy?->name ?? 'L\'Économe' }}
                </div>
                <div style="margin-top: 4px;">
                    <span class="sig-handwritten">{{ $receipt->receiverSignature() }}</span>
                </div>
                <div style="font-size: 8px; color: #64748b; margin-top: 4px;">
                    ✓ Réceptionné et signé numériquement le {{ $receipt->received_at->format('d/m/Y à H:i') }}
                </div>
            </td>
            <td>
                <div class="signature-title">Contrôle de Gestion / Direction</div>
                <div class="signature-mention">« Vu et vérifié pour imputation »</div>
                <div class="signature-line">Visa & Cachet :</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>

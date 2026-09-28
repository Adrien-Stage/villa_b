<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bon de Commande — {{ $order->number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 12mm 16mm 12mm;
            @bottom-right {
                content: "Page " counter(page) " / " counter(pages);
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                font-size: 8pt;
                color: #666;
            }
            @bottom-left {
                content: "Bon de Commande {{ $order->number }} — Service Achats & Économat";
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                font-size: 8pt;
                color: #666;
            }
        }

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
            width: 50%;
            vertical-align: top;
            padding: 12px 18px;
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
            margin-bottom: 45px;
        }

        .signature-line {
            font-size: 8.5px;
            font-weight: 600;
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
    <a href="{{ route('economat.orders.show', $order) }}" class="btn-back">
        ← Retour au bon de commande
    </a>
    <button onclick="window.print()" class="btn-print">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
        Imprimer le Bon de Commande
    </button>
</div>

<div class="page-container">
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="etab-name">{{ config('app.name', 'Établissement Hôtelier') }}</div>
                <div class="etab-sub">Direction Administrative & Financière</div>
                <div class="etab-sub">Service Approvisionnements & Économat Central</div>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <div style="font-size: 11px; font-weight: 800; font-family: monospace;">BON N° : {{ $order->number }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 3px;">Date : {{ $order->created_at->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title-block">
        <h1 class="doc-title">Bon de Commande Fournisseur</h1>
        <div class="doc-subtitle">Engagement de dépense et commande ferme de marchandises</div>
    </div>

    <table class="meta-grid">
        <tr>
            <td class="meta-label">Fournisseur :</td>
            <td class="meta-val font-bold">{{ $order->supplier?->name }}</td>
            <td class="meta-label">Numéro Bon :</td>
            <td class="meta-val font-mono font-bold">{{ $order->number }}</td>
        </tr>
        <tr>
            <td class="meta-label">Contact / Téléphone :</td>
            <td class="meta-val">{{ $order->supplier?->phone ?? '—' }}</td>
            <td class="meta-label">Date souhaitée :</td>
            <td class="meta-val font-mono">{{ $order->expected_at?->format('d/m/Y') ?? 'Dès que possible' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Email fournisseur :</td>
            <td class="meta-val">{{ $order->supplier?->email ?? '—' }}</td>
            <td class="meta-label">Statut du bon :</td>
            <td class="meta-val"><strong>{{ $order->statusLabel() }}</strong></td>
        </tr>
        @if($order->purchaseRequest)
            <tr>
                <td class="meta-label">Demande d'achat liée :</td>
                <td class="meta-val font-mono">{{ $order->purchaseRequest->number }} ({{ $order->purchaseRequest->departmentLabel() }})</td>
                <td class="meta-label">Émis par :</td>
                <td class="meta-val">{{ $order->createdBy?->name ?? 'Service Achats' }}</td>
            </tr>
        @endif
        @if($order->notes)
            <tr>
                <td class="meta-label">Instructions livraison :</td>
                <td class="meta-val" colspan="3">{{ $order->notes }}</td>
            </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">N°</th>
                <th style="width: 45%; text-align: left;">Désignation de l'article</th>
                <th style="width: 15%; text-align: right;">Quantité</th>
                <th style="width: 15%; text-align: right;">Prix Unitaire HT</th>
                <th style="width: 20%; text-align: right;">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->lines as $idx => $line)
                <tr>
                    <td class="text-center font-mono">{{ $idx + 1 }}</td>
                    <td>
                        <div class="font-bold">{{ $line->item?->name ?? '—' }}</div>
                        <div style="font-size: 8px; color: #64748b;">Réf : {{ $line->item?->reference ?? '—' }}</div>
                    </td>
                    <td class="text-right font-mono font-bold">
                        {{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }} {{ $line->item?->unit }}
                    </td>
                    <td class="text-right font-mono">
                        {{ number_format($line->unit_price / 100, 0, ',', ' ') }} FCFA
                    </td>
                    <td class="text-right font-mono font-bold">
                        {{ number_format((int) round((float) $line->quantity_ordered * $line->unit_price) / 100, 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-row">
            <span>Nombre d'articles commandés :</span>
            <span class="font-mono font-bold">{{ $order->lines->count() }}</span>
        </div>
        <div class="summary-row total">
            <span>MONTANT TOTAL COMMANDE :</span>
            <span class="font-mono">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</span>
        </div>
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-title">L'Économe / Responsable des Achats</div>
                <div class="signature-mention">« Bon pour commande »</div>
                <div class="signature-line">{{ $order->createdBy?->name ?? 'L\'Économe' }}</div>
            </td>
            <td>
                <div class="signature-title">La Direction Générale / Contrôle</div>
                <div class="signature-mention">« Bon pour accord financier et engagement »</div>
                <div class="signature-line">Visa & Cachet :</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>

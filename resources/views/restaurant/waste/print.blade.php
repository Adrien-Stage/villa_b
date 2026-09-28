<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PV de mise au rebut - {{ $waste->reference }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 20mm 15mm;
            @bottom-right {
                content: "Page " counter(page) " / " counter(pages);
                font-family: Arial, sans-serif;
                font-size: 8pt;
                color: #666;
            }
            @bottom-left {
                content: "Document généré le {{ now()->format('d/m/Y à H:i') }} — ERP Hôtelier";
                font-family: Arial, sans-serif;
                font-size: 8pt;
                color: #666;
            }
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #111;
            line-height: 1.4;
            font-size: 10pt;
            margin: 0;
            padding: 0;
        }

        .header {
            border-bottom: 2px solid #222;
            padding-bottom: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .hotel-title {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
        }

        .hotel-meta {
            font-size: 9pt;
            color: #444;
            line-height: 1.3;
        }

        .doc-title-block {
            text-align: right;
        }

        .doc-title {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #b91c1c;
            margin: 0 0 4px 0;
        }

        .doc-ref {
            font-family: monospace;
            font-size: 11pt;
            font-weight: bold;
            color: #222;
        }

        .meta-grid {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .meta-grid td {
            padding: 6px 10px;
            border: 1px solid #ddd;
            font-size: 9pt;
            vertical-align: top;
        }

        .meta-label {
            font-weight: bold;
            background-color: #f8f8f8;
            width: 25%;
            color: #444;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        table.items-table th {
            background-color: #f1f1f1;
            border: 1px solid #ccc;
            padding: 8px 10px;
            font-size: 9pt;
            text-align: left;
            text-transform: uppercase;
        }

        table.items-table td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            font-size: 9pt;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        .total-box {
            background-color: #fef2f2;
            border: 1px solid #f87171;
            color: #991b1b;
            font-size: 11pt;
            font-weight: bold;
            padding: 10px 15px;
            text-align: right;
            margin-bottom: 25px;
        }

        .notes-box {
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #fafafa;
            margin-bottom: 30px;
            font-size: 9pt;
        }

        .signatures {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }

        .signatures td {
            width: 33.33%;
            vertical-align: top;
            padding: 10px;
            border: 1px solid #ccc;
            height: 90px;
            font-size: 9pt;
        }

        .signature-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8pt;
            color: #555;
            margin-bottom: 40px;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <div>
            <div class="hotel-title">{{ $waste->tenant?->name ?? config('app.name', 'HÔTEL RESTAURANT') }}</div>
            <div class="hotel-meta">
                Service Restauration & Garde-manger<br>
                Département : {{ $waste->departmentLabel() }}
            </div>
        </div>
        <div class="doc-title-block">
            <div class="doc-title">PV de Mise au Rebut</div>
            <div class="doc-ref">{{ $waste->reference }}</div>
            <div class="hotel-meta">Date : {{ $waste->occurred_at?->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <table class="meta-grid">
        <tr>
            <td class="meta-label">Motif de la perte :</td>
            <td class="font-bold">{{ $waste->reasonLabel() }}</td>
            <td class="meta-label">Atelier / Rayon :</td>
            <td>{{ $waste->departmentLabel() }}</td>
        </tr>
        <tr>
            <td class="meta-label">Responsable désigné :</td>
            <td>{{ $waste->responsible_person ?? 'Non spécifié' }}</td>
            <td class="meta-label">Saisi dans l'ERP par :</td>
            <td>{{ $waste->recordedBy?->name ?? 'Système' }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Désignation de la matière</th>
                <th>Catégorie</th>
                <th class="text-right">Quantité mise au rebut</th>
                <th class="text-right">Coût unitaire (FCFA)</th>
                <th class="text-right">Valorisation totale (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="font-bold">{{ $waste->item?->name ?? 'Article' }}</td>
                <td>{{ $waste->item?->category?->name ?? 'Matière première' }}</td>
                <td class="text-right font-bold">{{ rtrim(rtrim(number_format((float) $waste->quantity, 3, ',', ' '), '0'), ',') }} {{ $waste->item?->unit }}</td>
                <td class="text-right">{{ number_format($waste->unitCostFcfa(), 2, ',', ' ') }}</td>
                <td class="text-right font-bold">{{ number_format($waste->totalCostFcfa(), 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>

    <div class="total-box">
        VALORISATION TOTALE DE LA PERTE : {{ $waste->formattedTotalCost() }}
    </div>

    @if($waste->notes)
        <div class="notes-box">
            <div class="font-bold" style="margin-bottom: 4px;">Circonstances et observations du déclarant :</div>
            <div>{{ $waste->notes }}</div>
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-title">Le Chef de Cuisine / Déclarant</div>
                <div style="font-size: 8pt; color: #777;">Date & Signature :</div>
            </td>
            <td>
                <div class="signature-title">L'Économe / Contrôle de Gestion</div>
                <div style="font-size: 8pt; color: #777;">Date & Signature :</div>
            </td>
            <td>
                <div class="signature-title">La Direction Générale</div>
                <div style="font-size: 8pt; color: #777;">Date & Visa :</div>
            </td>
        </tr>
    </table>

</body>
</html>

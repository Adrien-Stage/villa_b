<!DOCTYPE html>
<html lang="fr">
<head>
    @php
        $avecPrix = $avecPrix ?? true;
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bon de Commande — {{ $order->number }}</title>
    <style>
        @include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => 'Bon de Commande '.$order->number.' — Service Achats & Économat'])

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

        @import url('https://fonts.googleapis.com/css2?family=Qwigley&display=swap');

        @font-face {
            font-family: 'Qwigley';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('/fonts/Qwigley-Regular.woff2') format('woff2'),
                 url('/fonts/Qwigley-Regular.ttf') format('truetype');
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

        .sig-meta {
            font-size: 7.5px;
            color: #64748b;
            font-family: DejaVu Sans, monospace;
        }

        .signature-mention {
            font-size: 8px;
            color: #64748b;
            font-style: italic;
            margin-bottom: 4px;
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
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <a href="{{ route('economat.orders.show', $order) }}" class="btn-back">
            ← Retour au bon de commande
        </a>
        <div style="display: inline-flex; border-radius: 6px; overflow: hidden; border: 1px solid #475569;">
            <a href="{{ route('economat.orders.print', [$order, 'prix' => 1]) }}" style="padding: 5px 12px; font-size: 11px; font-weight: 600; text-decoration: none; {{ $avecPrix ? 'background: #2563eb; color: #fff;' : 'background: #334155; color: #cbd5e1;' }}">
                Version avec prix
            </a>
            <a href="{{ route('economat.orders.print', [$order, 'prix' => 0]) }}" style="padding: 5px 12px; font-size: 11px; font-weight: 600; text-decoration: none; {{ !$avecPrix ? 'background: #2563eb; color: #fff;' : 'background: #334155; color: #cbd5e1;' }}">
                Version sans prix
            </a>
        </div>
    </div>
    <button onclick="window.print()" class="btn-print">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
        Imprimer ({{ $avecPrix ? 'avec prix' : 'sans prix' }})
    </button>
</div>

<div class="page-container">
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="etab-name">{{ $tenant?->name ?? config('app.name', 'Établissement Hôtelier') }}</div>
                <div class="etab-sub">Direction Administrative & Financière</div>
                <div class="etab-sub">Service des Achats</div>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <div style="font-size: 11px; font-weight: 800; font-family: monospace;">BON N° : {{ $order->number }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 3px;">Date : {{ $order->created_at->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title-block">
        @if($order->isRegularisation())
            <h1 class="doc-title">Bon de Commande de Régularisation {{ $avecPrix ? '(Chiffré)' : '(Sans prix)' }}</h1>
            <div class="doc-subtitle">{{ $avecPrix ? 'Établi après réception — valorisation financière des marchandises' : 'Établi après la réception d\'une marchandise livrée sans commande préalable' }}</div>
        @else
            <h1 class="doc-title">Bon de Commande Fournisseur {{ $avecPrix ? '(Chiffré)' : '' }}</h1>
            <div class="doc-subtitle">{{ $avecPrix ? 'Commande valorisée de marchandises : articles, quantités, prix unitaires et montants' : 'Commande ferme de marchandises : articles et quantités à livrer' }}</div>
        @endif
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
                <td class="meta-label">{{ $order->isRegularisation() ? 'Objet :' : 'Instructions livraison :' }}</td>
                <td class="meta-val" colspan="3">{{ $order->notes }}</td>
            </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                @if($avecPrix)
                    <th style="width: 5%; text-align: center;">N°</th>
                    <th style="width: 45%; text-align: left;">Désignation de l'article</th>
                    <th style="width: 11%; text-align: right;">Quantité</th>
                    <th style="width: 7%; text-align: center;">Unité</th>
                    <th style="width: 16%; text-align: right;">P.U. HT</th>
                    <th style="width: 16%; text-align: right;">Total HT</th>
                @else
                    <th style="width: 6%; text-align: center;">N°</th>
                    <th style="width: 64%; text-align: left;">Désignation de l'article</th>
                    <th style="width: 18%; text-align: right;">Quantité</th>
                    <th style="width: 12%; text-align: center;">Unité</th>
                @endif
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
                        {{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }}
                    </td>
                    <td class="text-center font-mono font-bold" style="font-size: 8.5px;">
                        {{ $line->item?->shortUnit() ?? $line->item?->unit }}
                    </td>
                    @if($avecPrix)
                        <td class="text-right font-mono">
                            {{ number_format($line->unit_price / 100, 0, ',', ' ') }} F
                        </td>
                        <td class="text-right font-mono font-bold">
                            {{ number_format(($line->unit_price * $line->quantity_ordered) / 100, 0, ',', ' ') }} F
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-row">
            <span>Nombre d'articles commandés :</span>
            <span class="font-mono font-bold">{{ $order->lines->count() }}</span>
        </div>
        @if($avecPrix)
            <div class="summary-row total">
                <span>Montant Total HT :</span>
                <span class="font-mono">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA</span>
            </div>
        @endif
    </div>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-title">Le Responsable des Achats</div>
                <div class="signature-mention">« Bon pour commande & engagement »</div>
                <div style="margin-top: 4px; font-weight: bold; color: #0f172a; font-size: 9.5px;">
                    Nom : {{ $order->createdBy?->name ?? 'Service Achats' }}
                </div>
                <div style="margin-top: 4px;">
                    <span class="sig-handwritten">{{ $order->issuerSignature() }}</span>
                </div>
                <div class="sig-meta">
                    ✓ Émis et signé numériquement le {{ $order->created_at->format('d/m/Y à H:i') }}
                </div>
            </td>
            <td>
                <div class="signature-title">Contrôle de gestion</div>
                @if($order->purchaseRequest?->reviewed_at && $order->purchaseRequest->status !== 'rejected')
                    <div class="signature-mention">« Demande {{ $order->purchaseRequest->number }} approuvée »</div>
                    <div style="margin-top: 4px; font-weight: bold; color: #0f172a; font-size: 9.5px;">
                        {{ $order->purchaseRequest->reviewedBy?->name ?? '—' }}, le {{ $order->purchaseRequest->reviewed_at->format('d/m/Y') }}
                    </div>
                @else
                    <div class="signature-mention">« Visa »</div>
                    <div class="signature-line" style="margin-top: 35px;">Nom & Visa : ....................................</div>
                @endif
            </td>
        </tr>
    </table>
</div>

</body>
</html>

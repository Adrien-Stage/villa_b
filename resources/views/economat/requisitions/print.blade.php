<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bon de Réquisition {{ $requisition->number }} — {{ $requisition->departmentLabel() }}</title>
    <style>
        @include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => 'Bon de Réquisition '.$requisition->number.' — Magasin Central'])

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
            max-width: 850px;
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
            max-width: 850px;
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

        .btn-close {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
        }

        .btn-close:hover {
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
            text-align: right;
        }

        .doc-badge {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .doc-ref {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            font-family: DejaVu Sans, monospace;
        }

        .doc-date {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Cadre détails du bon */
        .meta-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #f8fafc;
            padding: 10px 14px;
            margin-bottom: 16px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px 20px;
        }

        .meta-item {
            font-size: 9.5px;
        }

        .meta-label {
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 8.5px;
        }

        .meta-value {
            color: #0f172a;
            font-weight: 600;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-awaiting { background: #fef3c7; color: #92400e; }
        .status-pending { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #e0e7ff; color: #3730a3; }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f1f5f9; color: #475569; }

        /* Tableau articles */
        .section-title {
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 8px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 14px;
        }

        table.data-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8px;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        table.data-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: DejaVu Sans, monospace; }
        .font-bold { font-weight: 700; }

        /* Cadre signatures */
        @import url('https://fonts.googleapis.com/css2?family=Qwigley&display=swap');

        @font-face {
            font-family: 'Qwigley';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('/fonts/Qwigley-Regular.woff2') format('woff2'),
                 url('/fonts/Qwigley-Regular.ttf') format('truetype');
        }

        .signatures-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signatures-table td {
            width: 25%;
            border: 1px solid #cbd5e1;
            padding: 10px;
            vertical-align: top;
            height: 95px;
            min-height: 95px;
        }

        .sig-title {
            font-size: 9px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .sig-note {
            font-size: 8px;
            color: #64748b;
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

        @media print {
            body {
                background: #ffffff;
            }
            .page-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <span style="font-weight: 600; font-size: 13px;">Aperçu avant impression — Bon de Réquisition {{ $requisition->number }}</span>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button class="btn-print" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimer le bon
            </button>
            <a href="javascript:window.close()" class="btn-close">Fermer</a>
        </div>
    </div>

    <div class="page-container">
        {{-- En-tête officiel --}}
        <table class="header-table">
            <tr>
                <td style="vertical-align: top;">
                    <div class="etab-name">{{ $tenant?->name ?? config('app.name', 'Établissement Hôtelier') }}</div>
                    <div class="etab-sub">Magasin Central & Économat · Gestion des Stocks</div>
                    <div class="etab-sub">Adresse : {{ $tenant?->address ?? 'Siège social' }} · Tél : {{ $tenant?->phone ?? '—' }}</div>
                </td>
                <td class="doc-title-block" style="vertical-align: top;">
                    <span class="doc-badge">BON DE RÉQUISITION INTERNE</span>
                    <div class="doc-ref">{{ $requisition->number }}</div>
                    <div class="doc-date">Date : {{ $requisition->created_at->format('d/m/Y à H:i') }}</div>
                </td>
            </tr>
        </table>

        {{-- Détails du bon --}}
        <div class="meta-box">
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Service demandeur :</span>
                    <span class="meta-value" style="font-size: 11px;">{{ $requisition->departmentLabel() }}@if($requisition->serviceStore) — dépôt {{ $requisition->serviceStore->name }}@endif</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Statut du bon :</span>
                    @php
                        $statusClass = match($requisition->status) {
                            'awaiting_endorsement' => 'status-awaiting',
                            'pending'   => 'status-pending',
                            'approved'  => 'status-approved',
                            'delivered' => 'status-delivered',
                            'rejected'  => 'status-rejected',
                            default     => 'status-cancelled',
                        };
                    @endphp
                    <span class="status-pill {{ $statusClass }}">{{ $requisition->statusLabel() }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Demandé par :</span>
                    <span class="meta-value">{{ $requisition->requestedBy?->name ?? '—' }}</span>
                    <span style="font-size: 8.5px; color: #64748b;">({{ $requisition->requestedBy?->email ?? '' }})</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Visa du chef de service :</span>
                    <span class="meta-value">
                        @if($requisition->endorsedBy)
                            {{ $requisition->refuseeAuVisa() ? 'Refusée par' : '' }} {{ $requisition->endorsedBy->name }} le {{ $requisition->endorsed_at?->format('d/m/Y à H:i') }}
                        @else
                            En attente du visa
                        @endif
                    </span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Validation Économat :</span>
                    <span class="meta-value">
                        @if($requisition->reviewedBy)
                            {{ $requisition->reviewedBy->name }} le {{ $requisition->reviewed_at?->format('d/m/Y à H:i') }}
                        @else
                            En attente de validation
                        @endif
                    </span>
                </div>
                @if($requisition->delivered_at)
                    <div class="meta-item">
                        <span class="meta-label">Date de livraison physique :</span>
                        <span class="meta-value">{{ $requisition->delivered_at->format('d/m/Y à H:i') }}</span>
                    </div>
                @endif
                <div class="meta-item" style="grid-column: span 2;">
                    <span class="meta-label">Motif / Justification du réapprovisionnement :</span>
                    <span class="meta-value">{{ $requisition->purpose ?: 'Réassort régulier et besoins de fonctionnement du service' }}</span>
                </div>
                @if($requisition->review_notes)
                    <div class="meta-item" style="grid-column: span 2;">
                        <span class="meta-label">Remarques de l'économe / arbitrage :</span>
                        <span class="meta-value" style="color: #475569;">{{ $requisition->review_notes }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tableau des articles demandés et livrés --}}
        <div class="section-title">Désignation des marchandises & fournitures</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 25px;">#</th>
                    <th style="width: 70px;">Référence</th>
                    <th>Désignation de l'article</th>
                    <th>Catégorie</th>
                    <th class="text-center" style="width: 45px;">Unité</th>
                    <th class="text-right" style="width: 70px;">Qté Demandée</th>
                    <th class="text-right" style="width: 70px;">Qté Servie</th>
                    <th class="text-right" style="width: 75px;">P.U. CUMP</th>
                    <th class="text-right" style="width: 90px;">Total (FCFA)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalRequestedAmount = 0;
                    $totalIssuedAmount = 0;
                @endphp
                @foreach($requisition->lines as $idx => $line)
                    @php
                        $item = $line->item;
                        $unitCost = (int) ($item?->average_cost ?? 0);
                        $lineReqTotal = (int) round((float) $line->quantity_requested * $unitCost);
                        $lineIssTotal = (int) round((float) $line->quantity_issued * $unitCost);
                        $totalRequestedAmount += $lineReqTotal;
                        $totalIssuedAmount += $lineIssTotal;
                    @endphp
                    <tr>
                        <td class="text-center font-mono" style="color: #64748b;">{{ $idx + 1 }}</td>
                        <td class="font-mono">{{ $item?->reference ?? '—' }}</td>
                        <td>
                            <strong style="color: #0f172a;">{{ $item?->name ?? 'Article non répertorié' }}</strong>
                        </td>
                        <td style="color: #475569;">
                            {{ $item?->category?->name ?? 'Général' }}
                        </td>
                        <td class="text-center">{{ $item?->unit ?? 'u' }}</td>
                        <td class="text-right font-mono font-bold">
                            @if($line->packaging_name)
                                {{ $line->quantiteDemandee() }}
                            @else
                                {{ rtrim(rtrim(number_format($line->quantity_requested, 3, ',', ' '), '0'), ',') }}
                            @endif
                        </td>
                        <td class="text-right font-mono font-bold" style="color: {{ $requisition->status === 'delivered' ? '#15803d' : '#475569' }};">
                            @if($requisition->status === 'delivered')
                                @if($line->packaging_name)
                                    {{ $line->quantiteServie() }}
                                @else
                                    {{ rtrim(rtrim(number_format($line->quantity_issued, 3, ',', ' '), '0'), ',') }}
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right font-mono">
                            {{ number_format($unitCost / 100, 0, ',', ' ') }}
                        </td>
                        <td class="text-right font-mono font-bold">
                            @if($requisition->status === 'delivered')
                                {{ number_format($lineIssTotal / 100, 0, ',', ' ') }}
                            @else
                                {{ number_format($lineReqTotal / 100, 0, ',', ' ') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr style="background: #f1f5f9; font-weight: bold;">
                    <td colspan="5" style="text-align: right; text-transform: uppercase;">
                        TOTAL VALORISÉ DU BON DE RÉQUISITION
                    </td>
                    <td class="text-right font-mono" style="font-size: 9.5px;">
                        {{ rtrim(rtrim(number_format($requisition->lines->sum('quantity_requested'), 3, ',', ' '), '0'), ',') }}
                    </td>
                    <td class="text-right font-mono" style="font-size: 9.5px; color: {{ $requisition->status === 'delivered' ? '#15803d' : '#475569' }};">
                        @if($requisition->status === 'delivered')
                            {{ rtrim(rtrim(number_format($requisition->lines->sum('quantity_issued'), 3, ',', ' '), '0'), ',') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right font-mono">—</td>
                    <td class="text-right font-mono font-bold" style="font-size: 10px; color: #0f172a;">
                        @if($requisition->status === 'delivered')
                            {{ number_format($totalIssuedAmount / 100, 0, ',', ' ') }} FCFA
                        @else
                            {{ number_format($totalRequestedAmount / 100, 0, ',', ' ') }} FCFA
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Signatures officielles pour décharge et traçabilité --}}
        <table class="signatures-table">
            <tr>
                <td>
                    <div class="sig-title">Le Demandeur</div>
                    <div class="sig-note">Responsable {{ $requisition->departmentLabel() }}</div>
                    <div class="sig-note" style="margin-top: 4px;">Nom : <strong>{{ $requisition->requestedBy?->name ?? '—' }}</strong></div>
                    <div style="margin-top: 4px;">
                        <span class="sig-handwritten">{{ $requisition->requesterSignature() }}</span>
                    </div>
                    <div class="sig-meta">
                        ✓ Signé électroniquement le {{ $requisition->created_at->format('d/m/Y à H:i') }}
                    </div>
                </td>
                <td>
                    <div class="sig-title">Le Chef de service</div>
                    <div class="sig-note">Visa du service demandeur</div>
                    @if($requisition->endorsedBy && !$requisition->refuseeAuVisa())
                        <div class="sig-note" style="margin-top: 4px;">Nom : <strong>{{ $requisition->endorsedBy->name }}</strong></div>
                        <div style="margin-top: 4px;">
                            <span class="sig-handwritten">{{ $requisition->endorsedBy->signatureName() }}</span>
                        </div>
                        <div class="sig-meta">✓ Visé électroniquement le {{ $requisition->endorsed_at?->format('d/m/Y à H:i') }}</div>
                    @else
                        <div class="sig-note" style="margin-top: 25px;">Nom : ....................................</div>
                        <div class="sig-note">Date & Signature :</div>
                    @endif
                </td>
                <td>
                    <div class="sig-title">L'Économe / Magasinier</div>
                    <div class="sig-note">Visa de sortie magasin & remise</div>
                    <div class="sig-note" style="margin-top: 25px;">Nom : {{ $requisition->reviewedBy?->name ?? '....................................' }}</div>
                    <div class="sig-note">Date & Signature :</div>
                </td>
                <td>
                    <div class="sig-title">Contrôle de Gestion / Direction</div>
                    <div class="sig-note">Visa d'audit & conformité</div>
                    <div class="sig-note" style="margin-top: 25px;">Nom : ....................................</div>
                    <div class="sig-note">Date & Signature :</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>

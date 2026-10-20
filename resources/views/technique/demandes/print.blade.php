<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiche d'Intervention {{ $demande->reference }} — {{ $demande->lieu() }}</title>
    <style>
        @include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => "Fiche d'Intervention Technique ".$demande->reference])

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

        /* En-tête */
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
            background: #4338ca;
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

        /* Cadre détails */
        .meta-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #f8fafc;
            padding: 10px 14px;
            margin-bottom: 14px;
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
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-open { background: #fef3c7; color: #92400e; }
        .status-assigned { background: #e0f2fe; color: #0369a1; }
        .status-in_progress { background: #e0e7ff; color: #3730a3; }
        .status-submitted { background: #f3e8ff; color: #6b21a8; }
        .status-done { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #f1f5f9; color: #475569; }

        /* Sections */
        .section-title {
            font-size: 10px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 8px;
        }

        .content-box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #ffffff;
            padding: 10px 12px;
            font-size: 9.5px;
            color: #1e293b;
            white-space: pre-line;
            line-height: 1.5;
            margin-bottom: 12px;
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
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        table.data-table tr:nth-child(even) {
            background: #fafafa;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Signatures */
        .signatures-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 24px;
        }

        .signature-box {
            border: 1px solid #94a3b8;
            border-radius: 6px;
            padding: 8px 10px;
            height: 90px;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sig-title {
            font-size: 8.5px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 4px;
        }

        .sig-content {
            font-size: 8px;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="no-print">
    <div>
        <strong>Fiche d'Intervention Technique</strong> — {{ $demande->reference }}
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <button class="btn-print" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Imprimer la fiche
        </button>
        <a class="btn-close" href="{{ route('technique.demandes.show', $demande) }}">← Retour à la demande</a>
    </div>
</div>

<div class="page-container">
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="etab-name">{{ $tenant?->name ?? config('app.name', 'Établissement Hôtelier') }}</div>
                <div class="etab-sub">Département Technique & Maintenance des Bâtiments</div>
                <div class="etab-sub">Plomberie · Climatisation · Électricité CFO/CFA · Menuiserie · Second Œuvre</div>
            </td>
            <td class="doc-title-block" style="vertical-align: top;">
                <span class="doc-badge">Fiche d'Intervention</span>
                <div class="doc-ref">{{ $demande->reference }}</div>
                <div class="doc-date">Date : {{ $demande->created_at->format('d/m/Y à H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="meta-box">
        <div class="meta-grid">
            <div class="meta-item">
                <div class="meta-label">Localisation / Chambre</div>
                <div class="meta-value">
                    {{ $demande->lieu() }}
                    @if($demande->room)
                        ({{ $demande->room->roomType->name ?? 'Chambre' }})
                        @if($demande->room_blocked)
                            — <span style="color: #b91c1c; font-weight: bold;">Chambre retirée de la vente</span>
                        @endif
                    @endif
                </div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Demandeur / Service d'origine</div>
                <div class="meta-value">{{ $demande->requester?->name ?? '—' }} ({{ $demande->departmentLabel() }})</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Corps d'état / Spécialité</div>
                <div class="meta-value">{{ $demande->specialtyLabel() }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Degré de priorité</div>
                <div class="meta-value">{{ $demande->priorityLabel() }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Technicien assigné</div>
                <div class="meta-value">{{ $demande->technician?->name ?? 'Non assigné' }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Statut de l'intervention</div>
                <div class="meta-value">
                    <span class="status-pill status-{{ $demande->status }}">{{ $demande->statusLabel() }}</span>
                    @if($demande->dureeMinutes() !== null)
                        <span style="font-size: 8.5px; color: #475569; margin-left: 6px;">Durée : {{ intdiv($demande->dureeMinutes(), 60) }}h {{ str_pad($demande->dureeMinutes() % 60, 2, '0', STR_PAD_LEFT) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="section-title">1. Diagnostic & Description de la panne</div>
    <div class="content-box">
        <strong>Objet :</strong> {{ $demande->title }}
        @if($demande->description)
            <br><br><strong>Détails du problème :</strong><br>{{ $demande->description }}
        @endif
    </div>

    <div class="section-title">2. Rapport d'exécution des travaux (Fiche d'intervention)</div>
    <div class="content-box">
        @if($demande->resolution)
            {{ $demande->resolution }}
        @else
            <em>(Intervention en cours d'exécution — Travaux non encore consignés)</em>
        @endif
    </div>

    <div class="section-title">3. Pièces détachées, matériels & consommables employés</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 15%;">Réf. Pièce</th>
                <th>Désignation de la pièce</th>
                <th style="width: 12%;" class="text-right">Quantité</th>
                <th style="width: 10%;" class="text-center">Unité</th>
                <th style="width: 15%;" class="text-right">P.U. (FCFA)</th>
                <th style="width: 18%;" class="text-right">Total (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($demande->parts as $part)
                @php
                    $qte = abs((float) $part->quantity);
                    $totalLigne = (int) round($qte * ($part->unit_cost / 100));
                @endphp
                <tr>
                    <td>{{ $part->item?->reference ?? '—' }}</td>
                    <td>{{ $part->item?->name ?? 'Article' }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($qte, 3, ',', ' '), '0'), ',') }}</td>
                    <td class="text-center">{{ $part->item?->unit ?? 'U' }}</td>
                    <td class="text-right">{{ number_format($part->unit_cost / 100, 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($totalLigne, 0, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 10px; color: #64748b;">
                        Aucune pièce sortie du dépôt technique pour cette intervention.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($demande->parts->isNotEmpty())
            <tfoot>
                <tr style="font-weight: 700; background: #f8fafc;">
                    <td colspan="5" class="text-right">VALEUR TOTALE DES PIÈCES EMPLOYÉES :</td>
                    <td class="text-right">{{ number_format($demande->valeurDesPieces() / 100, 0, ',', ' ') }} FCFA</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="signatures-grid">
        <div class="signature-box">
            <div class="sig-title">Le Technicien Intervenant</div>
            <div class="sig-content">
                {{ $demande->technician?->name ?? 'Nom & Visa' }}<br>
                Date : {{ $demande->completed_at ? $demande->completed_at->format('d/m/Y') : date('d/m/Y') }}
            </div>
        </div>
        <div class="signature-box">
            <div class="sig-title">Le Responsable Technique (Visa)</div>
            <div class="sig-content">
                {{ $demande->completer?->name ?? 'Visa & Clôture' }}<br>
                Date visa : {{ $demande->isDone() && $demande->completed_at ? $demande->completed_at->format('d/m/Y') : 'En attente' }}
            </div>
        </div>
        <div class="signature-box">
            <div class="sig-title">Service Demandeur / Réception travaux</div>
            <div class="sig-content">
                {{ $demande->requester?->name ?? 'Bon pour accord' }}<br>
                Date & Signature :
            </div>
        </div>
    </div>
</div>

</body>
</html>

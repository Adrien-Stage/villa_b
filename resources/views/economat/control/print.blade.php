<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapport de Contrôle des Stocks & Food Cost — {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</title>
    <style>
        @include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => 'Rapport de Contrôle des Stocks & Food Cost — Audit Interne'])

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
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .doc-date {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Cadres résumé */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }

        .summary-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            background: #f8fafc;
        }

        .card-label {
            font-size: 8px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .card-value {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 3px;
            font-family: DejaVu Sans, monospace;
        }

        .section-title {
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 16px;
            margin-bottom: 10px;
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

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: DejaVu Sans, monospace; }
        .font-bold { font-weight: 700; }

        /* Signatures */
        .signatures-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signatures-table td {
            width: 33.33%;
            border: 1px solid #cbd5e1;
            padding: 10px;
            vertical-align: top;
            height: 90px;
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
            color: #94a3b8;
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
        <span style="font-weight: 600; font-size: 13px;">Aperçu avant impression — Rapport de Contrôle des Stocks</span>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button class="btn-print" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimer
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
                    <div class="etab-sub">Département Économat & Contrôle de Gestion</div>
                    <div class="etab-sub">Adresse : {{ $tenant?->address ?? 'Siège social' }} · Tél : {{ $tenant?->phone ?? '—' }}</div>
                </td>
                <td class="doc-title-block" style="vertical-align: top;">
                    <span class="doc-badge">RAPPORT OFFICIEL DE CONTRÔLE</span>
                    <div class="doc-ref">AUDIT STOCKS & FOOD COST</div>
                    <div class="doc-date">Période du {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</div>
                    <div class="doc-date">Édité le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()?->name ?? 'Système' }}</div>
                </td>
            </tr>
        </table>

        {{-- Cartes de synthèse --}}
        <div class="summary-grid">
            <div class="summary-card">
                <div class="card-label">Valeur Totale Stock</div>
                <div class="card-value">{{ number_format($report['valuation']['total_value'] / 100, 0, ',', ' ') }} FCFA</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                    Économat : {{ number_format($report['valuation']['economat_value'] / 100, 0, ',', ' ') }} FCFA
                </div>
            </div>

            <div class="summary-card">
                <div class="card-label">Ratio Food Cost</div>
                <div class="card-value" style="color: {{ $report['food_cost_percent'] > 35 ? '#b91c1c' : ($report['food_cost_percent'] > 30 ? '#d97706' : '#15803d') }};">
                    {{ number_format($report['food_cost_percent'], 1) }} %
                </div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                    CA : {{ number_format($report['restaurant_revenue'] / 100, 0, ',', ' ') }} FCFA
                </div>
            </div>

            <div class="summary-card">
                <div class="card-label">Entrées Achats (BL)</div>
                <div class="card-value">{{ number_format($report['total_purchases_received'] / 100, 0, ',', ' ') }} FCFA</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                    Sorties livrées : {{ number_format($report['total_requisitions_delivered'] / 100, 0, ',', ' ') }} FCFA
                </div>
            </div>

            <div class="summary-card">
                <div class="card-label">Manquants Inventaires</div>
                <div class="card-value" style="color: #b91c1c;">
                    −{{ number_format($report['variances']['total_loss_value'] / 100, 0, ',', ' ') }} FCFA
                </div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                    Surplus : +{{ number_format($report['variances']['total_surplus_value'] / 100, 0, ',', ' ') }} FCFA
                </div>
            </div>
        </div>

        {{-- Section 1 : Analyse Matière & Food Cost Restaurant --}}
        <div class="section-title">1. Performance Matière & Ratio Food Cost (Restaurant)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Indicateur Matière</th>
                    <th class="text-right">Montant (FCFA)</th>
                    <th class="text-right">% Chiffre d'Affaires</th>
                    <th>Observations & Normes</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-bold">Chiffre d'Affaires Restaurant HT</td>
                    <td class="text-right font-mono font-bold">{{ number_format($report['restaurant_revenue'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono">100.0 %</td>
                    <td>Base de calcul du ratio de matière</td>
                </tr>
                <tr>
                    <td>Coût théorique des fiches techniques (ventes)</td>
                    <td class="text-right font-mono">{{ number_format($report['theoretical_food_cost'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono">
                        {{ $report['restaurant_revenue'] > 0 ? number_format(($report['theoretical_food_cost'] / $report['restaurant_revenue']) * 100, 1) : '0.0' }} %
                    </td>
                    <td>Consommation standard selon mercuriales et recettes</td>
                </tr>
                <tr>
                    <td>Pertes, avaries & coulages cuisine déclarés</td>
                    <td class="text-right font-mono" style="color: #b91c1c;">+{{ number_format($report['total_waste_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono">
                        {{ $report['restaurant_revenue'] > 0 ? number_format(($report['total_waste_value'] / $report['restaurant_revenue']) * 100, 1) : '0.0' }} %
                    </td>
                    <td>Déchets de préparation, périmés et incidents</td>
                </tr>
                <tr>
                    <td>Manquants constatés aux inventaires cuisine</td>
                    <td class="text-right font-mono" style="color: #b91c1c;">+{{ number_format($report['variances']['restaurant']['loss_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono">
                        {{ $report['restaurant_revenue'] > 0 ? number_format(($report['variances']['restaurant']['loss_value'] / $report['restaurant_revenue']) * 100, 1) : '0.0' }} %
                    </td>
                    <td>Écarts physiques négatifs de fin de période</td>
                </tr>
                <tr style="background: #f8fafc; font-weight: bold;">
                    <td>Coût matière réel total consommé</td>
                    <td class="text-right font-mono">{{ number_format($report['real_kitchen_cost'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono" style="color: {{ $report['food_cost_percent'] > 35 ? '#b91c1c' : ($report['food_cost_percent'] > 30 ? '#d97706' : '#15803d') }};">
                        {{ number_format($report['food_cost_percent'], 1) }} %
                    </td>
                    <td>
                        {{ $report['food_cost_percent'] > 35 ? 'Alerte : Coût matière supérieur au seuil critique (>35%)' : ($report['food_cost_percent'] > 30 ? 'Vigilance : Coût matière légèrement supérieur à l\'objectif' : 'Conforme aux standards d\'exploitation (<30%)') }}
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Section 2 : Valorisation Consolidée par Catégorie --}}
        <div class="section-title">2. Valorisation Globale des Stocks au CUMP (Magasin & Garde-Manger)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Catégorie de Stock</th>
                    <th class="text-center">Nb Références</th>
                    <th class="text-right">Valeur au CUMP (FCFA)</th>
                    <th class="text-right">% Valeur Totale</th>
                </tr>
            </thead>
            <tbody>
                @php $totalV = max(1, $report['valuation']['total_value']); @endphp
                @foreach($report['valuation']['categories'] as $cat)
                    <tr>
                        <td>{{ $cat['name'] }}</td>
                        <td class="text-center font-mono">{{ $cat['count'] }}</td>
                        <td class="text-right font-mono font-bold">{{ number_format($cat['value'] / 100, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono">{{ round(($cat['value'] / $totalV) * 100, 1) }} %</td>
                    </tr>
                @endforeach
                <tr style="background: #f1f5f9; font-weight: bold;">
                    <td>TOTAL DES STOCKS CONSOLIDÉS</td>
                    <td class="text-center font-mono">{{ $report['valuation']['economat_items_count'] + $report['valuation']['pantry_items_count'] }}</td>
                    <td class="text-right font-mono">{{ number_format($report['valuation']['total_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono">100.0 %</td>
                </tr>
            </tbody>
        </table>

        {{-- Section 3 : Synthèse des Écarts d'Inventaires --}}
        <div class="section-title">3. Synthèse des Écarts d'Inventaires Physiques</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Emplacement</th>
                    <th class="text-center">Lignes en écart</th>
                    <th class="text-right">Pertes / Manquants (FCFA)</th>
                    <th class="text-right">Excédents / Surplus (FCFA)</th>
                    <th class="text-right">Écart Net (FCFA)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-bold">Magasin Central (Économat)</td>
                    <td class="text-center font-mono">{{ $report['variances']['economat']['count'] }}</td>
                    <td class="text-right font-mono" style="color: #b91c1c;">−{{ number_format($report['variances']['economat']['loss_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono" style="color: #15803d;">+{{ number_format($report['variances']['economat']['surplus_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono font-bold">
                        {{ $report['variances']['economat']['variance_value'] > 0 ? '+' : '' }}{{ number_format($report['variances']['economat']['variance_value'] / 100, 0, ',', ' ') }}
                    </td>
                </tr>
                <tr>
                    <td class="font-bold">Garde-Manger (Restaurant)</td>
                    <td class="text-center font-mono">{{ $report['variances']['restaurant']['count'] }}</td>
                    <td class="text-right font-mono" style="color: #b91c1c;">−{{ number_format($report['variances']['restaurant']['loss_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono" style="color: #15803d;">+{{ number_format($report['variances']['restaurant']['surplus_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono font-bold">
                        {{ $report['variances']['restaurant']['variance_value'] > 0 ? '+' : '' }}{{ number_format($report['variances']['restaurant']['variance_value'] / 100, 0, ',', ' ') }}
                    </td>
                </tr>
                <tr style="background: #f1f5f9; font-weight: bold;">
                    <td>CONSOLIDATION DES ÉCARTS</td>
                    <td class="text-center font-mono">{{ $report['variances']['economat']['count'] + $report['variances']['restaurant']['count'] }}</td>
                    <td class="text-right font-mono" style="color: #b91c1c;">−{{ number_format($report['variances']['total_loss_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono" style="color: #15803d;">+{{ number_format($report['variances']['total_surplus_value'] / 100, 0, ',', ' ') }}</td>
                    <td class="text-right font-mono">
                        {{ $report['variances']['total_variance_value'] > 0 ? '+' : '' }}{{ number_format($report['variances']['total_variance_value'] / 100, 0, ',', ' ') }}
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Signatures officielles --}}
        <table class="signatures-table">
            <tr>
                <td>
                    <div class="sig-title">L'Économe en Chef</div>
                    <div class="sig-note">Visa & justification des stocks</div>
                </td>
                <td>
                    <div class="sig-title">Le Contrôleur de Gestion</div>
                    <div class="sig-note">Audit & validation des ratios</div>
                </td>
                <td>
                    <div class="sig-title">La Direction Générale</div>
                    <div class="sig-note">Approbation & décisions</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>

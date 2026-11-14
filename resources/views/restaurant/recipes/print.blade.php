<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiche Technique — {{ $recipe->name }}</title>
    <style>
        @include('partials.impression', ['haut' => '12mm', 'bas' => '14mm', 'cotes' => '12mm', 'pied' => 'Fiche Technique de Cuisine — ' . $recipe->name])

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
            max-width: 820px;
            margin: 20px auto;
            background: #ffffff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .no-print {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 820px;
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
        .btn-back:hover { color: #fff; }

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
            margin-bottom: 16px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
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
            margin: 12px 0 16px 0;
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
            padding: 5px 8px;
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
            margin-bottom: 16px;
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

        table.items-table tfoot td {
            padding: 6px 8px;
            font-size: 9.5px;
            font-weight: 700;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-top: 2px solid #0f172a;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .instructions-box {
            margin-top: 16px;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fafafa;
        }
        .instructions-title {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            color: #475569;
            margin-bottom: 6px;
        }
        .instructions-content {
            white-space: pre-line;
            font-size: 9.5px;
            color: #1e293b;
        }

        .signatures-table {
            width: 100%;
            margin-top: 24px;
            border-collapse: collapse;
        }
        .signatures-table td {
            width: 50%;
            border: 1px solid #94a3b8;
            padding: 8px 12px;
            height: 65px;
            vertical-align: top;
            font-size: 9px;
        }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <a href="{{ route('restaurant.recipes.index') }}" class="btn-back">
        &larr; Retour aux fiches techniques
    </a>
    <button type="button" onclick="window.print()" class="btn-print">
        Imprimer la fiche
    </button>
</div>

<div class="page-container">
    {{-- En-tête officiel de l'établissement --}}
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 65%;">
                <div class="etab-name">{{ $tenant?->name ?? 'ÉTABLISSEMENT' }}</div>
                <div class="etab-sub">
                    {{ $tenant?->city ?? '' }}{{ !empty($tenant?->country) ? ' — ' . $tenant->country : '' }}
                    @if(!empty($tenant?->phone)) · Tél : {{ $tenant->phone }} @endif
                    @if(!empty($tenant?->email)) · Email : {{ $tenant->email }} @endif
                </div>
                @if($recipe->pointOfSale)
                    <div style="font-size: 10px; font-weight: bold; color: #1e293b; margin-top: 4px;">
                        Point de vente / Restaurant : {{ $recipe->pointOfSale->name }}
                    </div>
                @endif
            </td>
            <td style="vertical-align: top; text-align: right; width: 35%;">
                <div style="font-size: 11px; font-weight: 700; color: #0f172a;">FICHE TECHNIQUE CUISINE</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Réf : FT-CUI-{{ str_pad($recipe->id, 4, '0', STR_PAD_LEFT) }}</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title-block">
        <h1 class="doc-title">{{ $recipe->name }}</h1>
        <div class="doc-subtitle">
            Nature : {{ $recipe->type === \App\Models\RestaurantRecipe::TYPE_DISH ? 'Plat du Menu' : 'Préparation de base (batch)' }}
            @if($recipe->menuItem?->category) · Catégorie : {{ $recipe->menuItem->category->name }} @endif
            @if(!$recipe->is_active) · <strong style="color: #dc2626;">Fiche inactive</strong> @endif
        </div>
    </div>

    @php
        $unitCost = (int) round($recipe->unitCost());
        $sellingPrice = (int) ($recipe->menuItem?->price ?? 0);
        $foodCost = $recipe->foodCostPercent();
        $margin = $recipe->margin() !== null ? (int) round($recipe->margin()) : null;
        $totalCost = (int) round($recipe->totalCost());
    @endphp

    {{-- Métadonnées de la fiche --}}
    <table class="meta-grid">
        <tr>
            <td class="meta-label">Rendement :</td>
            <td class="meta-val"><strong>{{ (float) $recipe->yield_quantity }}</strong> {{ $recipe->type === \App\Models\RestaurantRecipe::TYPE_DISH ? 'portion(s)' : ($recipe->producedItem?->unit ?? 'unité(s)') }}</td>
            <td class="meta-label">Coût matière unitaire :</td>
            <td class="meta-val"><strong>{{ number_format($unitCost / 100, 0, ',', ' ') }} FCFA</strong></td>
        </tr>
        <tr>
            <td class="meta-label">Coût matière total :</td>
            <td class="meta-val">{{ number_format($totalCost / 100, 0, ',', ' ') }} FCFA</td>
            <td class="meta-label">Prix de vente carte :</td>
            <td class="meta-val">{{ $sellingPrice > 0 ? number_format($sellingPrice / 100, 0, ',', ' ') . ' FCFA' : '— (Préparation interne)' }}</td>
        </tr>
        @if($recipe->type === \App\Models\RestaurantRecipe::TYPE_DISH)
        <tr>
            <td class="meta-label">Food Cost (Ratio matière) :</td>
            <td class="meta-val"><strong>{{ $foodCost !== null ? round($foodCost, 1) . ' %' : '—' }}</strong></td>
            <td class="meta-label">Marge brute / portion :</td>
            <td class="meta-val"><strong>{{ $margin !== null ? number_format($margin / 100, 0, ',', ' ') . ' FCFA' : '—' }}</strong></td>
        </tr>
        @endif
    </table>

    {{-- Tableau des ingrédients --}}
    <div style="font-weight: 700; text-transform: uppercase; font-size: 9px; color: #475569; margin-bottom: 6px;">
        Composition & Mercuriale des Ingrédients
    </div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Ingrédient (Garde-manger)</th>
                <th style="width: 15%; text-align: right;">Quantité brute</th>
                <th style="width: 12%; text-align: right;">Perte (%)</th>
                <th style="width: 13%; text-align: right;">Prix unitaire</th>
                <th style="width: 15%; text-align: right;">Coût total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recipe->lines as $idx => $line)
                @php
                    $cost = (int) round($line->cost());
                    $unitPrice = (int) round($line->item?->average_cost ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $line->item?->name ?? 'Article inconnu' }}</strong>
                        @if($line->notes)
                            <div style="font-size: 8px; color: #64748b;">Note : {{ $line->notes }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ (float) $line->quantity }} {{ $line->item?->unit ?? '' }}</td>
                    <td class="text-right">{{ (float) $line->waste_percent > 0 ? (float) $line->waste_percent . ' %' : '0 %' }}</td>
                    <td class="text-right">{{ number_format($unitPrice / 100, 0, ',', ' ') }} FCFA</td>
                    <td class="text-right font-bold">{{ number_format($cost / 100, 0, ',', ' ') }} FCFA</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 12px; color: #64748b;">
                        Aucun ingrédient renseigné sur cette fiche.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align: right;">COÛT TOTAL DE PRODUCTION :</td>
                <td class="text-right">{{ number_format($totalCost / 100, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td colspan="5" style="text-align: right;">COÛT MATIÈRE PAR PORTION :</td>
                <td class="text-right" style="color: #0f172a;">{{ number_format($unitCost / 100, 0, ',', ' ') }} FCFA</td>
            </tr>
        </tfoot>
    </table>

    {{-- Instructions et Procédé de fabrication --}}
    @if($recipe->notes)
        <div class="instructions-box">
            <div class="instructions-title">Procédé de fabrication & Instructions du Chef</div>
            <div class="instructions-content">{{ $recipe->notes }}</div>
        </div>
    @endif

    {{-- Signatures et visas --}}
    <table class="signatures-table">
        <tr>
            <td>
                <strong>Établi par le Chef de Cuisine :</strong>
                <div style="margin-top: 35px; border-bottom: 1px dotted #94a3b8; width: 80%;"></div>
                <div style="font-size: 8px; color: #64748b; margin-top: 3px;">Date & Signature</div>
            </td>
            <td>
                <strong>Visé par le Contrôleur / Économat :</strong>
                <div style="margin-top: 35px; border-bottom: 1px dotted #94a3b8; width: 80%;"></div>
                <div style="font-size: 8px; color: #64748b; margin-top: 3px;">Date & Signature</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>

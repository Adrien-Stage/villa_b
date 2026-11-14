<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recueil des Fiches Techniques — {{ $tenant?->name ?? 'Établissement' }}</title>
    <style>
        @include('partials.impression', ['haut' => '12mm', 'bas' => '14mm', 'cotes' => '12mm', 'pied' => 'Recueil Complet des Fiches Techniques — ' . ($tenant?->name ?? 'Établissement')])

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
            margin: 12px 0 20px 0;
            padding: 10px;
            background: #f1f5f9;
            border-radius: 4px;
        }

        .doc-title {
            font-size: 15px;
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

        .sheet-break {
            page-break-after: always;
            break-after: page;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px dashed #cbd5e1;
        }

        .sheet-break:last-child {
            page-break-after: avoid;
            break-after: avoid;
            border-bottom: none;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .meta-grid td {
            padding: 4px 8px;
            font-size: 9px;
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
            margin-bottom: 14px;
        }

        table.items-table thead th {
            background: #0f172a;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 5px 8px;
            border: 1px solid #0f172a;
        }

        table.items-table tbody td {
            padding: 4px 8px;
            font-size: 9px;
            border: 1px solid #cbd5e1;
        }

        table.items-table tfoot td {
            padding: 5px 8px;
            font-size: 9px;
            font-weight: 700;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-top: 2px solid #0f172a;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .instructions-box {
            margin-top: 10px;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fafafa;
        }
        .instructions-title {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            color: #475569;
            margin-bottom: 4px;
        }
        .instructions-content {
            white-space: pre-line;
            font-size: 9px;
            color: #1e293b;
        }

        .signatures-table {
            width: 100%;
            margin-top: 16px;
            border-collapse: collapse;
        }
        .signatures-table td {
            width: 50%;
            border: 1px solid #94a3b8;
            padding: 6px 10px;
            height: 55px;
            vertical-align: top;
            font-size: 8.5px;
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
            .sheet-break {
                border-bottom: none !important;
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
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
        Imprimer tout le recueil
    </button>
</div>

<div class="page-container">
    {{-- Page de garde / En-tête de synthèse --}}
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 65%;">
                <div class="etab-name">{{ $tenant?->name ?? 'ÉTABLISSEMENT' }}</div>
                <div class="etab-sub">
                    {{ $tenant?->city ?? '' }}{{ !empty($tenant?->country) ? ' — ' . $tenant->country : '' }}
                    @if(!empty($tenant?->phone)) · Tél : {{ $tenant->phone }} @endif
                    @if(!empty($tenant?->email)) · Email : {{ $tenant->email }} @endif
                </div>
            </td>
            <td style="vertical-align: top; text-align: right; width: 35%;">
                <div style="font-size: 11px; font-weight: 700; color: #0f172a;">RECUEIL DES FICHES TECHNIQUES</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Cuisine (Plats & Prépas) & Bar</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title-block">
        <h1 class="doc-title">LIVRE DE RECETTES & FICHES TECHNIQUES</h1>
        <div class="doc-subtitle">
            {{ $dishes->count() }} plat(s) de cuisine · {{ $preparations->count() }} préparation(s) de base · {{ $barDrinks->count() }} boisson(s) et cocktail(s) du bar
        </div>
    </div>

    {{-- SECTION 1 : PLATS DE CUISINE --}}
    @foreach($dishes as $recipe)
        @php
            $unitCost = (int) round($recipe->unitCost());
            $sellingPrice = (int) ($recipe->menuItem?->price ?? 0);
            $foodCost = $recipe->foodCostPercent();
            $margin = $recipe->margin() !== null ? (int) round($recipe->margin()) : null;
            $totalCost = (int) round($recipe->totalCost());
        @endphp
        <div class="sheet-break">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 12px;">
                <div>
                    <span style="font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #b45309; letter-spacing: 0.5px;">Cuisine · Plat</span>
                    <h2 style="font-size: 13px; font-weight: 800; color: #0f172a; margin: 2px 0 0 0;">{{ $recipe->name }}</h2>
                    <div style="font-size: 8.5px; color: #64748b;">
                        {{ $recipe->pointOfSale?->name ?? 'Tous restaurants' }}
                        @if($recipe->menuItem?->category) · {{ $recipe->menuItem->category->name }} @endif
                    </div>
                </div>
                <div style="text-align: right; font-size: 9px; color: #64748b;">
                    Réf : FT-CUI-{{ str_pad($recipe->id, 4, '0', STR_PAD_LEFT) }}
                </div>
            </div>

            <table class="meta-grid">
                <tr>
                    <td class="meta-label">Rendement :</td>
                    <td class="meta-val"><strong>{{ (float) $recipe->yield_quantity }} portion(s)</strong></td>
                    <td class="meta-label">Coût matière / portion :</td>
                    <td class="meta-val"><strong>{{ number_format($unitCost / 100, 0, ',', ' ') }} FCFA</strong></td>
                </tr>
                <tr>
                    <td class="meta-label">Prix de vente carte :</td>
                    <td class="meta-val">{{ $sellingPrice > 0 ? number_format($sellingPrice / 100, 0, ',', ' ') . ' FCFA' : '—' }}</td>
                    <td class="meta-label">Food Cost / Marge :</td>
                    <td class="meta-val"><strong>{{ $foodCost !== null ? round($foodCost, 1) . ' %' : '—' }}</strong> (Marge : {{ $margin !== null ? number_format($margin / 100, 0, ',', ' ') . ' FCFA' : '—' }})</td>
                </tr>
            </table>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Ingrédient</th>
                        <th style="width: 15%; text-align: right;">Quantité</th>
                        <th style="width: 15%; text-align: right;">P.U. Moyen</th>
                        <th style="width: 20%; text-align: right;">Coût</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recipe->lines as $idx => $line)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>{{ $line->item?->name ?? 'Article inconnu' }}</td>
                            <td class="text-right">{{ (float) $line->quantity }} {{ $line->item?->unit ?? '' }}</td>
                            <td class="text-right">{{ number_format(((int) ($line->item?->average_cost ?? 0)) / 100, 0, ',', ' ') }} FCFA</td>
                            <td class="text-right">{{ number_format(((int) round($line->cost())) / 100, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;">COÛT TOTAL :</td>
                        <td class="text-right">{{ number_format($totalCost / 100, 0, ',', ' ') }} FCFA</td>
                    </tr>
                </tfoot>
            </table>

            @if($recipe->notes)
                <div class="instructions-box">
                    <div class="instructions-title">Procédé & Notes</div>
                    <div class="instructions-content">{{ $recipe->notes }}</div>
                </div>
            @endif
        </div>
    @endforeach

    {{-- SECTION 2 : PRÉPARATIONS DE BASE --}}
    @foreach($preparations as $recipe)
        @php
            $unitCost = (int) round($recipe->unitCost());
            $totalCost = (int) round($recipe->totalCost());
        @endphp
        <div class="sheet-break">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 12px;">
                <div>
                    <span style="font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #2563eb; letter-spacing: 0.5px;">Cuisine · Préparation de base (batch)</span>
                    <h2 style="font-size: 13px; font-weight: 800; color: #0f172a; margin: 2px 0 0 0;">{{ $recipe->name }}</h2>
                    <div style="font-size: 8.5px; color: #64748b;">
                        {{ $recipe->pointOfSale?->name ?? 'Tous restaurants' }} · Alimente : {{ $recipe->producedItem?->name ?? 'Garde-manger' }}
                    </div>
                </div>
                <div style="text-align: right; font-size: 9px; color: #64748b;">
                    Réf : FT-PREP-{{ str_pad($recipe->id, 4, '0', STR_PAD_LEFT) }}
                </div>
            </div>

            <table class="meta-grid">
                <tr>
                    <td class="meta-label">Rendement total batch :</td>
                    <td class="meta-val"><strong>{{ (float) $recipe->yield_quantity }} {{ $recipe->producedItem?->unit ?? 'unité(s)' }}</strong></td>
                    <td class="meta-label">Coût matière unitaire :</td>
                    <td class="meta-val"><strong>{{ number_format($unitCost / 100, 0, ',', ' ') }} FCFA / {{ $recipe->producedItem?->unit ?? 'u' }}</strong></td>
                </tr>
            </table>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Ingrédient</th>
                        <th style="width: 15%; text-align: right;">Quantité</th>
                        <th style="width: 15%; text-align: right;">P.U. Moyen</th>
                        <th style="width: 20%; text-align: right;">Coût</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recipe->lines as $idx => $line)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>{{ $line->item?->name ?? 'Article inconnu' }}</td>
                            <td class="text-right">{{ (float) $line->quantity }} {{ $line->item?->unit ?? '' }}</td>
                            <td class="text-right">{{ number_format(((int) ($line->item?->average_cost ?? 0)) / 100, 0, ',', ' ') }} FCFA</td>
                            <td class="text-right">{{ number_format(((int) round($line->cost())) / 100, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;">COÛT TOTAL DU BATCH :</td>
                        <td class="text-right">{{ number_format($totalCost / 100, 0, ',', ' ') }} FCFA</td>
                    </tr>
                </tfoot>
            </table>

            @if($recipe->notes)
                <div class="instructions-box">
                    <div class="instructions-title">Procédé & Notes</div>
                    <div class="instructions-content">{{ $recipe->notes }}</div>
                </div>
            @endif
        </div>
    @endforeach

    {{-- SECTION 3 : BAR & COCKTAILS --}}
    @foreach($barDrinks as $item)
        @php
            $drink = $item->barDrink;
            $sellingPrice = (int) $item->price;
        @endphp
        <div class="sheet-break">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 12px;">
                <div>
                    <span style="font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #7c3aed; letter-spacing: 0.5px;">Bar · {{ \App\Models\RestaurantBarDrink::FORMATS[$drink->format] ?? $drink->format }}</span>
                    <h2 style="font-size: 13px; font-weight: 800; color: #0f172a; margin: 2px 0 0 0;">{{ $item->name }}</h2>
                    <div style="font-size: 8.5px; color: #64748b;">
                        {{ $item->pointOfSale?->name ?? 'Tous restaurants' }} · Stock : Dépôt Bar
                    </div>
                </div>
                <div style="text-align: right; font-size: 9px; color: #64748b;">
                    Réf : FT-BAR-{{ str_pad($item->id, 4, '0', STR_PAD_LEFT) }}
                </div>
            </div>

            <table class="meta-grid">
                <tr>
                    <td class="meta-label">Format de service :</td>
                    <td class="meta-val"><strong>{{ \App\Models\RestaurantBarDrink::FORMATS[$drink->format] ?? $drink->format }}</strong></td>
                    <td class="meta-label">Prix de vente carte :</td>
                    <td class="meta-val"><strong>{{ number_format($sellingPrice / 100, 0, ',', ' ') }} FCFA</strong></td>
                </tr>
            </table>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">#</th>
                        <th style="width: 52%;">Bouteille / Ingrédient</th>
                        <th style="width: 20%; text-align: right;">Dose</th>
                        <th style="width: 20%; text-align: center;">Unité</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($drink->ingredients ?? [] as $idx => $ing)
                        @php
                            $stockItem = $stockItems[$ing['stock_item_id'] ?? null] ?? null;
                            $measure = ($ing['measure'] ?? '') === 'ml' ? 'Centilitres / mL' : ($stockItem?->unit ?? 'Unité');
                        @endphp
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>{{ $stockItem?->name ?? 'Article' }}</td>
                            <td class="text-right font-bold">{{ $ing['quantity'] }} {{ ($ing['measure'] ?? '') === 'ml' ? 'mL' : '' }}</td>
                            <td class="text-center">{{ $measure }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($drink->instructions)
                <div class="instructions-box">
                    <div class="instructions-title">Méthode de préparation & Service (Instructions Barman)</div>
                    <div class="instructions-content">{{ $drink->instructions }}</div>
                </div>
            @endif
        </div>
    @endforeach
</div>

</body>
</html>

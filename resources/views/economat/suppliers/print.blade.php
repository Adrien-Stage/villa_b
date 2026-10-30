<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiche fournisseur — {{ $supplier->name }}</title>
    @php
        $fcfa = fn ($v) => number_format(((int) $v) / 100, 0, ',', ' ');
        $identite = collect([$tenant?->address, $tenant?->phone, $tenant?->email])->filter()->implode(' · ');
        $recues = $livraisons->where('status', \App\Models\GoodsReceipt::STATUS_RECEIVED);
    @endphp
    <style>
        @include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => 'Fiche fournisseur — '.$supplier->name])

        * { box-sizing: border-box; font-family: DejaVu Sans, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        body { margin: 0; color: #1a1a1a; font-size: 10px; line-height: 1.45; background: #f8fafc; }
        .page { max-width: 850px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); }
        .no-print { display: flex; justify-content: space-between; align-items: center; max-width: 850px; margin: 0 auto 15px; padding: 10px 16px; background: #1e293b; color: #fff; border-radius: 8px; }
        .no-print button { background: #2563eb; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .no-print a { color: #cbd5e1; text-decoration: none; font-size: 12px; }
        .entete { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #334155; padding-bottom: 12px; margin-bottom: 14px; }
        .etab { font-size: 14px; font-weight: 700; color: #0f172a; }
        .muted { color: #64748b; font-size: 9px; }
        h1 { font-size: 15px; margin: 6px 0 2px; text-transform: uppercase; letter-spacing: .04em; color: #0f172a; }
        h2.section { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #334155; margin: 16px 0 4px; }
        .numero { font-family: DejaVu Sans Mono, monospace; font-size: 13px; font-weight: 700; text-align: right; }
        .grille { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 6px; }
        .cadre { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 10px; }
        .cadre h2 { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #475569; margin: 0 0 4px; }
        .cadre p { margin: 1px 0; }
        .chiffres { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 10px 0; }
        .chiffres div { border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; }
        .chiffres strong { display: block; font-size: 12px; color: #0f172a; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.lignes th { background: #f1f5f9; text-align: left; font-size: 8.5px; text-transform: uppercase; color: #475569; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; }
        table.lignes td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; font-family: DejaVu Sans Mono, monospace; white-space: nowrap; }
        .annulee td { color: #94a3b8; text-decoration: line-through; }
        tfoot td { font-weight: 700; border-top: 2px solid #334155; }
        .vide { color: #64748b; font-style: italic; padding: 6px 0; }
        @media print {
            body { background: #fff; }
            .page { max-width: 100%; margin: 0; padding: 0; box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <span style="font-weight:600;font-size:13px;">Aperçu avant impression — Fiche fournisseur</span>
        <span style="display:flex;gap:10px;align-items:center;">
            <button type="button" onclick="window.print()">Imprimer la fiche</button>
            <a href="javascript:window.close()">Fermer</a>
        </span>
    </div>

    <div class="page">
        <div class="entete">
            <div>
                <div class="etab">{{ $tenant?->name ?? 'Établissement' }}</div>
                @if($identite !== '')<div class="muted">{{ $identite }}</div>@endif
                <h1>Fiche fournisseur</h1>
                <div style="font-size:13px;font-weight:700">{{ $supplier->name }}</div>
                <div class="muted">{{ $supplier->category ? $supplier->category_label : 'Fournisseur' }}{{ $supplier->is_active ? '' : ' · Inactif' }}</div>
            </div>
            <div class="numero">
                {{ $supplier->code ?: '' }}
                <div class="muted" style="font-weight:400">Éditée le {{ now()->format('d/m/Y à H:i') }}</div>
            </div>
        </div>

        <div class="grille">
            <div class="cadre">
                <h2>Coordonnées</h2>
                <p>Contact : {{ $supplier->contact_name ?: '—' }}</p>
                <p>Téléphone : {{ $supplier->phone ?: '—' }} · Email : {{ $supplier->email ?: '—' }}</p>
                <p>Adresse : {{ collect([$supplier->address, $supplier->city])->filter()->implode(', ') ?: '—' }}</p>
                <p>NIU : {{ $supplier->tax_id ?: '—' }} · RCCM : {{ $supplier->rccm ?: '—' }}</p>
            </div>
            <div class="cadre">
                <h2>Conditions commerciales</h2>
                <p>Paiement : {{ $supplier->payment_terms ? $supplier->payment_terms_label : '—' }}{{ $supplier->payment_method ? ' · ' . $supplier->payment_method_label : '' }}</p>
                <p>Délai de livraison : {{ $supplier->delivery_lead_time_days !== null ? $supplier->delivery_lead_time_days . ' jour(s)' : '—' }}</p>
                @if($voitBanque)<p>Coordonnées bancaires : {{ $supplier->bank_details ?: '—' }}</p>@endif
                @if($supplier->notes)<p class="muted" style="font-size:9.5px">{{ $supplier->notes }}</p>@endif
            </div>
        </div>

        <div class="chiffres">
            <div><span class="muted">Articles au catalogue</span><strong>{{ $chiffres['articles'] }}</strong></div>
            <div><span class="muted">Livraisons reçues</span><strong>{{ $chiffres['livraisons'] }}</strong></div>
            <div><span class="muted">Livré sur 12 mois</span><strong>{{ $fcfa($chiffres['montant_12_mois']) }} F</strong></div>
            <div><span class="muted">Bons en cours</span><strong>{{ $chiffres['bons_en_cours'] }}</strong></div>
        </div>

        <h2 class="section">Articles qu'il nous livre</h2>
        @if($catalogue->isEmpty())
            <p class="vide">Aucun article au catalogue.</p>
        @else
            <table class="lignes">
                <thead>
                    <tr><th>Article</th><th>Catégorie</th><th class="num">Prix d'achat</th><th>Prix du</th><th>Observation</th></tr>
                </thead>
                <tbody>
                    @foreach($catalogue as $ligne)
                        <tr>
                            <td>{{ $ligne['item']->name }}</td>
                            <td>{{ $ligne['item']->category?->name ?? '—' }}</td>
                            <td class="num">{{ $ligne['prix'] > 0 ? $fcfa($ligne['prix']) . ' F / ' . $ligne['item']->shortUnit() : 'À renseigner' }}</td>
                            <td>{{ $ligne['date']?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ collect([$ligne['habituel'] ? 'Fournisseur habituel' : null, $ligne['moinsCher'] ? 'Moins cher' : null, $ligne['autres'] > 0 ? $ligne['autres'] . ' autre(s) fournisseur(s)' : null])->filter()->implode(' · ') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($enCours->isNotEmpty())
            <h2 class="section">Bons de commande en cours</h2>
            <table class="lignes">
                <thead><tr><th>Bon</th><th>Envoyé le</th><th>Attendu le</th><th>Statut</th><th class="num">Montant</th></tr></thead>
                <tbody>
                    @foreach($enCours as $bon)
                        <tr>
                            <td>{{ $bon->number }}</td>
                            <td>{{ $bon->sent_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $bon->expected_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $bon->statusLabel() }}</td>
                            <td class="num">{{ $fcfa($bon->total_amount) }} F</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2 class="section">Livraisons des douze derniers mois</h2>
        @if($livraisons->isEmpty())
            <p class="vide">Aucune livraison sur la période.</p>
        @else
            <table class="lignes">
                <thead><tr><th>Reçu le</th><th>Bon d'entrée</th><th>Commande</th><th>Bordereau</th><th>Livraison</th><th>Réceptionnaire</th><th class="num">Montant</th></tr></thead>
                <tbody>
                    @foreach($livraisons as $recu)
                        <tr class="{{ $recu->status === \App\Models\GoodsReceipt::STATUS_CANCELLED ? 'annulee' : '' }}">
                            <td>{{ $recu->received_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $recu->number }}</td>
                            <td>{{ $recu->purchaseOrder?->number ?? '—' }}</td>
                            <td>{{ $recu->delivery_note_number ?: '—' }}</td>
                            <td>{{ $recu->status === \App\Models\GoodsReceipt::STATUS_CANCELLED ? 'Annulée' : ($recu->deliveryLabel() ?? '—') }}</td>
                            <td>{{ $recu->receivedBy?->name ?? '—' }}</td>
                            <td class="num">{{ $fcfa($recu->total_amount) }} F</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="6">Total livré ({{ $recues->count() }} livraison(s))</td><td class="num">{{ $fcfa($recues->sum('total_amount')) }} F</td></tr>
                </tfoot>
            </table>
        @endif
    </div>
</body>
</html>

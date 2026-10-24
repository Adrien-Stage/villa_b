@extends('layouts.hotel')

@section('title', 'Tableau de bord de gestion')

@section('content')
@php
    $fcfa = fn ($centimes) => $centimes === null ? '—' : number_format(((int) $centimes) / 100, 0, ',', ' ') . ' F';
    $pct = fn ($v) => $v === null ? '—' : str_replace('.', ',', (string) $v) . ' %';
    $h = $tableau['hebergement'];
    $ha = $tableau['hebergement_avant'];
    $r = $tableau['restaurant'];
    $ra = $tableau['restaurant_avant'];
    $t = $tableau['tresorerie'];
    $seuils = $tableau['seuils'];
    $periode = $tableau['debut']->locale('fr')->isoFormat('Do') . ' au ' . $tableau['fin']->locale('fr')->isoFormat('D MMMM YYYY');
    $comparaison = $tableau['avant_debut']->locale('fr')->isoFormat('Do') . ' au ' . $tableau['avant_fin']->locale('fr')->isoFormat('D MMMM');
@endphp

{{-- Une variation : flèche et couleur selon que la hausse est bonne ou non. --}}
@php
    $variation = function ($valeur, $avant, bool $hausseFavorable = true) {
        $v = \App\Services\TableauDeBordGestion::variation($valeur, $avant);
        if ($v === null) {
            return '<span class="text-primary/35">pas de comparaison</span>';
        }
        $bon = $v == 0 ? null : (($v > 0) === $hausseFavorable);
        $couleur = $bon === null ? 'text-primary/50' : ($bon ? 'text-green-700' : 'text-red-600');
        $fleche = $v > 0 ? '▲' : ($v < 0 ? '▼' : '=');
        return '<span class="' . $couleur . ' font-medium">' . $fleche . ' ' . str_replace('.', ',', (string) abs($v)) . ' %</span>';
    };
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Tableau de bord de gestion</h1>
            <p class="text-sm text-primary/60 mt-0.5">Du {{ $periode }}, comparé au {{ $comparaison }} (même nombre de jours).</p>
        </div>
        <form method="GET" action="{{ route('pilotage.index') }}" class="flex items-end gap-2">
            <label class="block">
                <span class="block text-[11px] font-medium text-primary/60 mb-1">Mois</span>
                <input type="month" name="mois" value="{{ $mois->format('Y-m') }}" max="{{ now()->format('Y-m') }}"
                       class="rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm text-primary outline-none focus:border-primary">
            </label>
            <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark">Afficher</button>
        </form>
    </div>

    @include('housekeeping.partials.flash')

    {{-- Alertes de dérive du mois --}}
    @if($tableau['alertes']->isNotEmpty())
        <section class="rounded-xl border border-amber-300 bg-amber-50 p-4" aria-label="Alertes de gestion du mois">
            <h2 class="text-sm font-semibold text-amber-900">{{ $tableau['alertes']->count() }} alerte(s) de gestion ce mois-ci</h2>
            <ul class="mt-2 space-y-1">
                @foreach($tableau['alertes'] as $alerte)
                    <li class="text-sm text-amber-900"><strong>{{ $alerte->ruleLabel() }}</strong> — {{ $alerte->message }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Hébergement --}}
    <section aria-labelledby="titre-hebergement">
        <h2 id="titre-hebergement" class="text-sm font-semibold text-primary mb-2">Hébergement</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach([
                ['Taux d’occupation', $pct($h['occupation']), $h['occupation'], $ha['occupation'], true, $h['vendues'] . ' nuitées sur ' . $h['disponibles']],
                ['Prix moyen (ADR)', $fcfa($h['adr']), $h['adr'], $ha['adr'], true, 'par nuitée vendue'],
                ['RevPAR', $fcfa($h['revpar']), $h['revpar'], $ha['revpar'], true, 'par chambre disponible'],
                ['Revenu d’hébergement', $fcfa($h['revenu']), $h['revenu'], $ha['revenu'], true, 'nuitées de la période, séjours offerts exclus'],
            ] as [$libelle, $valeur, $brut, $avant, $hausse, $note])
                <div class="bg-white border border-secondary/20 rounded-xl px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider text-primary/45">{{ $libelle }}</p>
                    <p class="text-xl font-heading font-bold text-primary mt-0.5">{{ $valeur }}</p>
                    <p class="text-[11px] mt-0.5">{!! $variation($brut, $avant, $hausse) !!}</p>
                    <p class="text-[10px] text-primary/40">{{ $note }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-3 grid grid-cols-1 lg:grid-cols-3 gap-3">
            <div class="lg:col-span-2 bg-white border border-secondary/20 rounded-xl overflow-hidden">
                <p class="px-4 pt-3 text-xs font-semibold text-primary">Revenu d’hébergement par canal de vente</p>
                <x-table :rows="collect($h['canaux'])" empty="Aucun séjour sur la période." empty-icon="bed" caption="Revenu par canal de vente">
                    <x-slot:head>
                        <x-table.col>Canal</x-table.col>
                        <x-table.col align="right">Revenu</x-table.col>
                        <x-table.col align="right">Part</x-table.col>
                    </x-slot:head>
                    @foreach($h['canaux'] as $canal => $montant)
                        <x-table.row>
                            <x-table.cell>{{ $canal }}</x-table.cell>
                            <x-table.cell align="right" nowrap>{{ $fcfa($montant) }}</x-table.cell>
                            <x-table.cell align="right" nowrap class="text-primary/60">{{ $h['revenu'] > 0 ? round($montant * 100 / $h['revenu']) : 0 }} %</x-table.cell>
                        </x-table.row>
                    @endforeach
                </x-table>
            </div>
            <div class="bg-white border border-secondary/20 rounded-xl px-4 py-3 space-y-2">
                <p class="text-xs font-semibold text-primary">Arrivées prévues sur la période : {{ $h['arrivees'] }}</p>
                <div class="flex items-baseline justify-between">
                    <span class="text-sm text-primary/70">Taux d’annulation</span>
                    <span class="text-sm font-semibold text-primary">{{ $pct($h['taux_annulation']) }} <span class="text-[11px] font-normal">{!! $variation($h['taux_annulation'], $ha['taux_annulation'], false) !!}</span></span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-sm text-primary/70">Non-présentations</span>
                    <span class="text-sm font-semibold text-primary">{{ $pct($h['taux_absence']) }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Restauration --}}
    @if($r !== null)
        <section aria-labelledby="titre-restauration">
            <h2 id="titre-restauration" class="text-sm font-semibold text-primary mb-2">Restauration</h2>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                @foreach([
                    ['Chiffre d’affaires', $fcfa($r['chiffre_affaires']), $r['chiffre_affaires'], $ra['chiffre_affaires'], true, $r['commandes'] . ' commande(s)'],
                    ['Couverts', number_format($r['couverts'], 0, ',', ' '), $r['couverts'], $ra['couverts'], true, 'saisis à la commande en salle'],
                    ['Revenu par couvert', $fcfa($r['revenu_par_couvert']), $r['revenu_par_couvert'], $ra['revenu_par_couvert'], true, 'commandes qui portent leurs couverts'],
                    ['Coût matière', $pct($r['cout_matiere']), $r['cout_matiere'], $ra['cout_matiere'], false, 'seuil d’alerte : ' . $seuils['seuil_cout_matiere'] . ' %'],
                    ['Pertes', $fcfa($r['pertes']), $r['pertes'], $ra['pertes'], false, 'valorisées au coût moyen'],
                ] as [$libelle, $valeur, $brut, $avant, $hausse, $note])
                    <div class="bg-white border rounded-xl px-4 py-3 {{ $libelle === 'Coût matière' && $r['cout_matiere'] > $seuils['seuil_cout_matiere'] ? 'border-red-300 bg-red-50/40' : 'border-secondary/20' }}">
                        <p class="text-[10px] uppercase tracking-wider text-primary/45">{{ $libelle }}</p>
                        <p class="text-xl font-heading font-bold text-primary mt-0.5">{{ $valeur }}</p>
                        <p class="text-[11px] mt-0.5">{!! $variation($brut, $avant, $hausse) !!}</p>
                        <p class="text-[10px] text-primary/40">{{ $note }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Trésorerie --}}
    <section aria-labelledby="titre-tresorerie">
        <h2 id="titre-tresorerie" class="text-sm font-semibold text-primary mb-2">Trésorerie</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach([
                ['Encaissements', $fcfa($t['encaissements']), $t['encaissements'], $t['encaissements_avant'], true, 'toutes activités'],
                ['Dépenses', $fcfa($t['depenses']), $t['depenses'], $t['depenses_avant'], false, 'saisies en comptabilité'],
                ['Encaissements boutique', $fcfa($t['par_activite']['boutique']), $t['par_activite']['boutique'], $t['boutique_avant'], true, 'ventes réglées'],
                ['Créances clients', $fcfa($t['creances']), null, null, false, 'à ce jour, séjours et commandes'],
            ] as [$libelle, $valeur, $brut, $avant, $hausse, $note])
                <div class="bg-white border border-secondary/20 rounded-xl px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider text-primary/45">{{ $libelle }}</p>
                    <p class="text-xl font-heading font-bold text-primary mt-0.5">{{ $valeur }}</p>
                    @if($avant !== null)<p class="text-[11px] mt-0.5">{!! $variation($brut, $avant, $hausse) !!}</p>@endif
                    <p class="text-[10px] text-primary/40">{{ $note }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-3 bg-white border border-secondary/20 rounded-xl overflow-hidden">
            <p class="px-4 pt-3 text-xs font-semibold text-primary">Encaissements par moyen de paiement</p>
            <x-table :rows="collect($t['par_moyen'])" empty="Aucun encaissement sur la période." empty-icon="wallet" caption="Encaissements par moyen de paiement">
                <x-slot:head>
                    <x-table.col>Moyen</x-table.col>
                    <x-table.col align="right">Montant</x-table.col>
                </x-slot:head>
                @foreach($t['par_moyen'] as $moyen => $montant)
                    <x-table.row>
                        <x-table.cell>{{ \App\Editions\Registres::MODES[$moyen] ?? ucfirst($moyen) }}</x-table.cell>
                        <x-table.cell align="right" nowrap>{{ $fcfa($montant) }}</x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>
    </section>

    {{-- Budget du mois --}}
    <section aria-labelledby="titre-budget" class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-secondary/15 bg-gray-50/60">
            <h2 id="titre-budget" class="text-sm font-semibold text-primary">Budget du mois, ramené aux jours écoulés</h2>
            @droit('pilotage.budget.voir')
                <a href="{{ route('pilotage.budget.index', ['annee' => $mois->year]) }}" class="text-xs font-semibold text-primary underline">Budget {{ $mois->year }}</a>
            @enddroit
        </div>
        @if($tableau['budget'] === null)
            <p class="px-4 py-6 text-center text-sm text-primary/50">Pas de budget pour {{ $mois->year }} : les écarts s'afficheront dès qu'il sera établi (Pilotage › Budget).</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase tracking-wider text-primary/45">
                        <tr class="border-b border-secondary/15">
                            <th class="px-4 py-2 text-left font-medium">Centre</th>
                            <th class="px-3 py-2 text-right font-medium">Produits budget</th>
                            <th class="px-3 py-2 text-right font-medium">Réalisé</th>
                            <th class="px-3 py-2 text-right font-medium">Écart</th>
                            <th class="px-3 py-2 text-right font-medium">Charges budget</th>
                            <th class="px-3 py-2 text-right font-medium">Réalisé</th>
                            <th class="px-4 py-2 text-right font-medium">Écart</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tableau['budget'] as $centre => $natures)
                            @php($p = $natures['revenue'])
                            @php($c = $natures['cost'])
                            @continue($p['budget'] === 0 && $p['realise'] === 0 && $c['budget'] === 0 && $c['realise'] === 0)
                            <tr class="border-b border-secondary/10 last:border-0">
                                <td class="px-4 py-2 text-primary">{{ \App\Services\TableauDeBordGestion::centre($centre) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap text-primary/70">{{ $fcfa($p['budget']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fcfa($p['realise']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap {{ $p['ecart']['favorable'] === null ? 'text-primary/50' : ($p['ecart']['favorable'] ? 'text-green-700' : 'text-red-600') }}">{{ $p['ecart']['pct'] === null ? '—' : $pct($p['ecart']['pct']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap text-primary/70">{{ $fcfa($c['budget']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fcfa($c['realise']) }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap {{ $c['ecart']['favorable'] === null ? 'text-primary/50' : ($c['ecart']['favorable'] ? 'text-green-700' : 'text-red-600') }}">{{ $c['ecart']['pct'] === null ? '—' : $pct($c['ecart']['pct']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-4 py-2 text-[11px] text-primary/45 border-t border-secondary/10">Vert : favorable à l'hôtel (recettes au-dessus, charges en dessous du budget). Rouge : défavorable.</p>
        @endif
    </section>

    {{-- Seuils d'alerte --}}
    <section aria-labelledby="titre-seuils" class="bg-white border border-secondary/20 rounded-xl p-4">
        <h2 id="titre-seuils" class="text-sm font-semibold text-primary">Seuils des alertes de gestion</h2>
        <p class="text-xs text-primary/50 mt-0.5 mb-3">Chaque matin, les dérives du mois en cours sont signalées au contrôle de gestion, au responsable financier et à la direction — une fois par mois et par centre.</p>
        @if($peutRegler)
            <form method="POST" action="{{ route('pilotage.seuils.update') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                @csrf
                @method('PUT')
                <input type="hidden" name="mois" value="{{ $mois->format('Y-m') }}">
                <label class="block">
                    <span class="block text-xs text-primary/70 mb-1">Tolérance sur le budget (%)</span>
                    <input type="number" name="tolerance_budget" min="0" max="100" required value="{{ $seuils['tolerance_budget'] }}" class="w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-primary">
                </label>
                <label class="block">
                    <span class="block text-xs text-primary/70 mb-1">Coût matière maximal (%)</span>
                    <input type="number" name="seuil_cout_matiere" min="1" max="100" required value="{{ $seuils['seuil_cout_matiere'] }}" class="w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-primary">
                </label>
                <label class="block">
                    <span class="block text-xs text-primary/70 mb-1">Baisse de marge tolérée (points)</span>
                    <input type="number" name="baisse_marge" min="1" max="100" required value="{{ $seuils['baisse_marge'] }}" class="w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-primary">
                </label>
                <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark">Enregistrer les seuils</button>
            </form>
        @else
            <p class="text-sm text-primary/70">Tolérance sur le budget : {{ $seuils['tolerance_budget'] }} % · coût matière maximal : {{ $seuils['seuil_cout_matiere'] }} % · baisse de marge tolérée : {{ $seuils['baisse_marge'] }} points.</p>
        @endif
    </section>
</div>
@endsection

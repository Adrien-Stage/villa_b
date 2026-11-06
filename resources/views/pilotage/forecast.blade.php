@extends('layouts.hotel')

@section('title', 'Prévisions (Forecast) ' . $annee)

@section('content')
@php
    $fcfa = fn ($centimes) => number_format(((int) $centimes) / 100, 0, ',', ' ');
    $moisNoms = ['Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
    $sources = \App\Services\RealiseDeGestion::SOURCES;
    $cellule = 'w-24 rounded-md border border-secondary/25 bg-white px-2 py-1 text-right font-mono text-xs text-primary outline-none focus:border-primary';
    $teinte = fn (?bool $favorable) => $favorable === null ? 'text-primary/50' : ($favorable ? 'text-green-700' : 'text-red-600');
@endphp

<div class="max-w-full space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-heading font-semibold text-primary">Prévisions d’atterrissage (Forecast) {{ $annee }}</h1>
                @if($grille['valide'])
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        ✓ Validé par le RAF
                    </span>
                @elseif($grille['existe'])
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                        Brouillon de prévision
                    </span>
                @endif
            </div>
            <p class="text-sm text-primary/60 mt-0.5 max-w-3xl">
                Trajectoire révisée en cours d'exercice : combinaison des mois réalisés et des projections de fin d'année. Montants en FCFA.
                Mois écoulés constatés : {{ $grille['mois_ecoules'] }} sur 12.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('pilotage.budget.index', ['annee' => $annee]) }}" class="px-3 py-1.5 border border-secondary/30 text-xs font-semibold rounded-lg text-primary hover:bg-accent/20">📋 Voir Budget {{ $annee }}</a>
            @if($peutModifier)
                <a href="{{ route('pilotage.forecast.index', ['annee' => $annee, 'depuis' => 'atterrissage']) }}"
                   class="px-3 py-1.5 border border-primary/40 bg-primary/5 text-xs font-semibold rounded-lg text-primary hover:bg-primary/10">⚡ Calculer atterrissage (Réalisé + Budget)</a>
                <a href="{{ route('pilotage.forecast.index', ['annee' => $annee, 'depuis' => 'budget']) }}"
                   class="px-3 py-1.5 border border-secondary/30 text-xs font-semibold rounded-lg text-primary hover:bg-accent/20">Repartir du budget initial</a>
            @endif
            <a href="{{ route('pilotage.forecast.index', ['annee' => $annee - 1]) }}" class="px-3 py-1.5 border border-secondary/30 text-xs font-semibold rounded-lg text-primary hover:bg-accent/20">← {{ $annee - 1 }}</a>
            <a href="{{ route('pilotage.forecast.index', ['annee' => $annee + 1]) }}" class="px-3 py-1.5 border border-secondary/30 text-xs font-semibold rounded-lg text-primary hover:bg-accent/20">{{ $annee + 1 }} →</a>
        </div>
    </div>

    @include('housekeeping.partials.flash')

    @if($propose)
        <p class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
            @if($modePropose === 'atterrissage')
                La grille propose une projection d'atterrissage : les mois 1 à {{ $grille['mois_ecoules'] }} reprennent le réalisé constaté, et les mois restants reprennent le budget.
            @else
                La grille propose les montants du budget initial {{ $annee }}. Ajustez les projections selon les dernières tendances.
            @endif
        </p>
    @elseif(! $grille['existe'])
        <p class="rounded-lg border border-dashed border-secondary/30 px-4 py-3 text-sm text-primary/60">
            Aucune prévision (forecast) enregistrée pour {{ $annee }}.{{ $peutModifier ? ' Calculez automatiquement un atterrissage ci-dessus ou saisissez vos prévisions.' : '' }}
        </p>
    @endif

    <form method="POST" action="{{ route('pilotage.forecast.update', $annee) }}">
        @csrf
        @method('PUT')
        <div class="bg-white border border-secondary/20 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-xs">
                <caption class="sr-only">Prévisions {{ $annee }} par centre et par mois</caption>
                <thead class="bg-gray-50/80 text-[10px] uppercase tracking-wider text-primary/50">
                    <tr class="border-b border-secondary/15">
                        <th scope="col" class="sticky left-0 bg-gray-50 px-3 py-2 text-left font-medium">Centre</th>
                        @foreach($moisNoms as $i => $nom)
                            <th scope="col" class="px-2 py-2 text-right font-medium {{ $i + 1 <= $grille['mois_ecoules'] ? 'text-primary/70' : '' }}">{{ $nom }}</th>
                        @endforeach
                        <th scope="col" class="px-3 py-2 text-right font-medium">Atterrissage Annuel</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">Écart Réalisé</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grille['centres'] as $bloc)
                        @foreach($bloc['lignes'] as $nature => $ligne)
                            <tr class="border-t {{ $loop->first ? 'border-secondary/25' : 'border-secondary/10' }}">
                                <th scope="row" class="sticky left-0 bg-white px-3 pt-2 text-left font-medium text-primary whitespace-nowrap">
                                    @if($loop->first)<span class="block text-[11px] font-semibold">{{ $bloc['libelle'] }}</span>@endif
                                    <span class="text-primary/60">{{ $ligne['libelle'] }} — prévision</span>
                                </th>
                                @foreach(range(1, 12) as $m)
                                    <td class="px-1 pt-2 text-right">
                                        @if($peutModifier)
                                            <input type="number" min="0" step="1000" name="forecast[{{ $bloc['centre'] }}][{{ $nature }}][{{ $m }}]"
                                                   value="{{ intdiv((int) $ligne['forecast'][$m], 100) }}" class="{{ $cellule }}"
                                                   aria-label="{{ $bloc['libelle'] }}, {{ $ligne['libelle'] }}, {{ $moisNoms[$m - 1] }}">
                                        @else
                                            <span class="font-mono">{{ $fcfa($ligne['forecast'][$m]) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 pt-2 text-right font-mono font-semibold text-primary whitespace-nowrap">{{ $fcfa($ligne['total_forecast']) }}</td>
                                <td class="px-3 pt-2"></td>
                            </tr>
                            <tr>
                                <th scope="row" class="sticky left-0 bg-white px-3 pb-2 text-left font-normal text-primary/45 whitespace-nowrap">{{ $ligne['libelle'] }} — réalisé</th>
                                @foreach(range(1, 12) as $m)
                                    <td class="px-2 pb-2 text-right font-mono text-primary/60 whitespace-nowrap">{{ $m <= $grille['mois_ecoules'] ? $fcfa($ligne['realise'][$m]) : '' }}</td>
                                @endforeach
                                <td class="px-3 pb-2 text-right font-mono text-primary/70 whitespace-nowrap">{{ $fcfa($ligne['total_realise']) }}</td>
                                <td class="px-3 pb-2 text-right font-mono whitespace-nowrap {{ $teinte($ligne['ecart']['favorable']) }}">
                                    @if($ligne['forecast_au_jour'] > 0 || $ligne['realise_au_jour'] > 0)
                                        {{ $ligne['ecart']['montant'] > 0 ? '+' : '' }}{{ $fcfa($ligne['ecart']['montant']) }}
                                        @if($ligne['ecart']['pct'] !== null)<span class="block text-[10px]">{{ $ligne['ecart']['pct'] > 0 ? '+' : '' }}{{ str_replace('.', ',', (string) $ligne['ecart']['pct']) }} %</span>@endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="border-t-2 border-secondary/30 bg-gray-50/60">
                        <th scope="row" class="sticky left-0 bg-gray-50 px-3 py-2 text-left font-semibold text-primary whitespace-nowrap">Résultat — prévision</th>
                        @foreach(range(1, 12) as $m)
                            <td class="px-2 py-2 text-right font-mono whitespace-nowrap">{{ $fcfa($grille['resultat']['forecast'][$m]) }}</td>
                        @endforeach
                        <td class="px-3 py-2 text-right font-mono font-semibold whitespace-nowrap">{{ $fcfa($grille['resultat']['total_forecast']) }}</td>
                        <td class="px-3 py-2"></td>
                    </tr>
                    <tr class="bg-gray-50/60">
                        <th scope="row" class="sticky left-0 bg-gray-50 px-3 pb-2 text-left font-normal text-primary/60 whitespace-nowrap">Résultat — réalisé</th>
                        @foreach(range(1, 12) as $m)
                            <td class="px-2 pb-2 text-right font-mono text-primary/60 whitespace-nowrap">{{ $m <= $grille['mois_ecoules'] ? $fcfa($grille['resultat']['realise'][$m]) : '' }}</td>
                        @endforeach
                        <td class="px-3 pb-2 text-right font-mono text-primary/70 whitespace-nowrap">{{ $fcfa($grille['resultat']['total_realise']) }}</td>
                        <td class="px-3 pb-2"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-[11px] text-primary/45">Vert : favorable à l'hôtel. Rouge : défavorable. Les mois futurs affichent la projection révisée.</p>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            @if($peutValider && $grille['existe'] && !$grille['valide'])
                <div>
                    <button type="button" onclick="document.getElementById('form-valider-forecast').submit()" class="px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-lg hover:bg-green-800 shadow-sm">
                        ✓ Valider formellement les prévisions {{ $annee }} (Exclusivité RAF)
                    </button>
                </div>
            @else
                <div></div>
            @endif

            @if($peutModifier)
                <button type="submit" class="px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark">Enregistrer les prévisions {{ $annee }}</button>
            @endif
        </div>
    </form>

    @if($peutValider && $grille['existe'] && !$grille['valide'])
        <form id="form-valider-forecast" method="POST" action="{{ route('pilotage.forecast.valider', $annee) }}" class="hidden">
            @csrf
        </form>
    @endif
</div>
@endsection

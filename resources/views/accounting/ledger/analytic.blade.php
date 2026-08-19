@extends('layouts.hotel')

@section('title', 'Analytique')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Analytique</h1>
        <p class="text-sm text-primary/60 mt-1">{{ $periode['label'] }} — rentabilité par point de vente</p>
    </div>
    <a href="{{ route('accounting.ledger.analytic.margins', ['from' => $periode['from']->toDateString(), 'to' => $periode['to']->toDateString()]) }}"
       class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-lg border border-secondary/25 bg-white text-xs font-semibold text-primary/70 hover:bg-accent/20 transition-colors shrink-0">
        <i data-lucide="bed-double" class="w-3.5 h-3.5"></i>
        Marges par type de chambre
    </a>
</div>

@include('accounting.ledger.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

<form method="GET" class="flex flex-col sm:flex-row gap-2 mb-5">
    <div>
        <label class="block text-xs font-medium text-primary/70 mb-1.5">Du</label>
        <input type="date" name="from" value="{{ $periode['from']->toDateString() }}"
               class="rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
    </div>
    <div>
        <label class="block text-xs font-medium text-primary/70 mb-1.5">Au</label>
        <input type="date" name="to" value="{{ $periode['to']->toDateString() }}"
               class="rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
    </div>
    <div class="flex items-end">
        <button type="submit" class="px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
            Afficher
        </button>
    </div>
</form>

{{-- La qualité de l'analytique se dit avant ses résultats : une marge calculée
     sur un tiers des charges n'est pas une marge. --}}
@if($ventilation['rate'] !== null && $ventilation['rate'] < 80)
    <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-[11px] text-amber-900 leading-relaxed">
        <strong>{{ $ventilation['rate'] }} % des charges seulement portent un centre d'analyse.</strong>
        Les marges ci-dessous sont calculées sur cette part ; les
        {{ $fcfa($ventilation['unassigned']) }} FCFA restants figurent en « non ventilé ».
        Tant que ce taux est bas, lisez ces marges comme un ordre de grandeur, pas comme un résultat.
    </div>
@endif

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Produits</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($resultat['totals']['revenue']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Charges</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($resultat['totals']['cost']) }}</p>
    </div>
    <div class="bg-white rounded-xl border p-3.5 {{ $resultat['totals']['margin'] < 0 ? 'border-red-300 bg-red-50/60' : 'border-secondary/20' }}">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Marge</p>
        <p class="text-base font-heading font-bold mt-0.5 {{ $resultat['totals']['margin'] < 0 ? 'text-red-600' : 'text-primary' }}">
            {{ $fcfa($resultat['totals']['margin']) }}
        </p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">RevPAR</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">
            {{ $revpar['revpar'] !== null ? $fcfa($revpar['revpar']) : '—' }}
        </p>
        <p class="text-[10px] text-primary/45 mt-0.5">par chambre disponible</p>
    </div>
</div>

{{-- RevPAR --}}
<div class="bg-white rounded-xl border border-secondary/20 shadow-sm p-5 mb-5">
    <h2 class="text-sm font-semibold text-primary mb-1">RevPAR — revenu par chambre disponible</h2>
    <p class="text-[11px] text-primary/60 leading-relaxed mb-4">
        Un prix moyen élevé sur trois chambres vendues ne vaut pas un prix modeste sur trente, et un taux
        d'occupation obtenu en bradant ne paie pas les charges. Le RevPAR réconcilie les deux en rapportant
        le produit d'hébergement à <strong>toutes</strong> les chambres vendables, occupées ou non.
    </p>
    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
        <div>
            <dt class="text-[10px] uppercase tracking-widest text-primary/40">Produit hébergement</dt>
            <dd class="text-primary font-mono mt-0.5">{{ $fcfa($revpar['revenue']) }}</dd>
        </div>
        <div>
            <dt class="text-[10px] uppercase tracking-widest text-primary/40">Nuitées disponibles</dt>
            <dd class="text-primary font-mono mt-0.5">{{ number_format($revpar['available'], 0, ',', ' ') }}</dd>
            <dd class="text-[10px] text-primary/40">{{ $revpar['rooms'] }} chambres × {{ $revpar['nights'] }} nuits</dd>
        </div>
        <div>
            <dt class="text-[10px] uppercase tracking-widest text-primary/40">Taux d'occupation</dt>
            <dd class="text-primary font-mono mt-0.5">{{ $revpar['occupancy'] !== null ? $revpar['occupancy'] . ' %' : '—' }}</dd>
            <dd class="text-[10px] text-primary/40">{{ number_format($revpar['sold'], 0, ',', ' ') }} nuitées vendues</dd>
        </div>
        <div>
            <dt class="text-[10px] uppercase tracking-widest text-primary/40">Prix moyen (ADR)</dt>
            <dd class="text-primary font-mono mt-0.5">{{ $revpar['adr'] !== null ? $fcfa($revpar['adr']) : '—' }}</dd>
        </div>
    </dl>
</div>

{{-- Résultat par centre --}}
<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-secondary/15">
        <h2 class="text-sm font-semibold text-primary">Résultat par centre de profit</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Centre</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Produits</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Charges</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Marge</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Taux</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @foreach($resultat['rows'] as $ligne)
                    <tr class="hover:bg-accent/10">
                        <td class="px-4 py-2 text-primary font-medium">{{ $ligne['label'] }}</td>
                        <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $fcfa($ligne['revenue']) }}</td>
                        <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $fcfa($ligne['cost']) }}</td>
                        <td class="px-3 py-2 text-right font-semibold whitespace-nowrap {{ $ligne['margin'] < 0 ? 'text-red-600' : 'text-primary' }}">
                            {{ $fcfa($ligne['margin']) }}
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap {{ $ligne['margin'] < 0 ? 'text-red-600' : 'text-primary/60' }}">
                            {{ $ligne['margin_pct'] !== null ? $ligne['margin_pct'] . ' %' : '—' }}
                        </td>
                    </tr>
                @endforeach

                {{-- Le loyer et les assurances ne se rattachent honnêtement à aucun
                     point de vente : les répartir au prorata donnerait une marge
                     d'apparence précise et de fait arbitraire. --}}
                <tr class="bg-accent/10 italic text-primary/60">
                    <td class="px-4 py-2">
                        Non ventilé
                        <span class="block text-[10px] not-italic">charges de structure — le coût d'exister</span>
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fcfa($resultat['unassigned']['revenue']) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fcfa($resultat['unassigned']['cost']) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fcfa($resultat['unassigned']['margin']) }}</td>
                    <td class="px-4 py-2 text-right">—</td>
                </tr>
            </tbody>
            <tfoot class="bg-accent/20 font-semibold text-primary">
                <tr>
                    <td class="px-4 py-2.5 uppercase tracking-widest text-[10px]">Total</td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($resultat['totals']['revenue']) }}</td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($resultat['totals']['cost']) }}</td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap {{ $resultat['totals']['margin'] < 0 ? 'text-red-600' : '' }}">
                        {{ $fcfa($resultat['totals']['margin']) }}
                    </td>
                    <td class="px-4 py-2.5"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Reflet de classe 9 --}}
<div class="bg-white rounded-xl border border-secondary/20 shadow-sm p-5">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex-1">
            <h2 class="text-sm font-semibold text-primary mb-1">Reflet de classe 9</h2>
            <p class="text-[11px] text-primary/60 leading-relaxed">
                Les tableaux ci-dessus se lisent directement dans les classes 6 et 7. Le reflet les
                <em>écrit</em> : il reprend les charges dans les comptes réfléchis et les ventile par
                destination. Rien n'est déplacé — la charge d'origine reste à sa place, et la classe 9
                se solde à zéro sur elle-même.
                <strong>Ni le bilan ni le compte de résultat ne bougent.</strong>
            </p>

            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-4 text-xs">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-primary/40">État</dt>
                    <dd class="mt-0.5 {{ $reflet['mirrored'] ? 'text-green-700' : 'text-primary/60' }}">
                        {{ $reflet['mirrored'] ? 'Période reflétée' : 'Pas encore reflétée' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-primary/40">Charges reflétées</dt>
                    <dd class="text-primary font-mono mt-0.5">{{ $fcfa($reflet['reflected']) }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-primary/40">Classe 6 aujourd'hui</dt>
                    <dd class="text-primary font-mono mt-0.5">{{ $fcfa($reflet['current']) }}</dd>
                </div>
            </dl>

            @if($reflet['mirrored'] && $reflet['drift'] !== 0)
                <p class="mt-3 text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    <strong>Reflet périmé de {{ $fcfa(abs($reflet['drift'])) }} FCFA.</strong>
                    Des charges ont été enregistrées depuis. Recalculer contre-passe le reflet existant
                    et en produit un neuf — les deux restent au journal.
                </p>
            @endif
        </div>

        <form method="POST" action="{{ route('accounting.ledger.analytic.mirror') }}" class="shrink-0">
            @csrf
            <input type="hidden" name="from" value="{{ $periode['from']->toDateString() }}">
            <input type="hidden" name="to" value="{{ $periode['to']->toDateString() }}">
            @if($reflet['mirrored'])
                <input type="hidden" name="force" value="1">
            @endif
            <button type="submit"
                    onclick="return {{ $reflet['mirrored'] ? "confirm('Contre-passer le reflet existant et le recalculer ?')" : 'true' }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors whitespace-nowrap">
                <i data-lucide="{{ $reflet['mirrored'] ? 'refresh-cw' : 'copy' }}" class="w-3.5 h-3.5"></i>
                {{ $reflet['mirrored'] ? 'Recalculer' : 'Produire le reflet' }}
            </button>
            @if($reflet['entry'])
                <a href="{{ route('accounting.ledger.entry', $reflet['entry']) }}"
                   class="block text-center mt-2 text-[11px] text-primary/50 hover:text-primary transition-colors">Voir l'écriture</a>
            @endif
        </form>
    </div>
</div>
@endsection

@extends('layouts.hotel')

@section('title', 'Tableau technique')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Service technique & maintenance</h1>
            <p class="text-sm text-primary/60 mt-0.5">Gestion des interventions (plomberie, menuiserie, climatisation, CFO, CFA) et suivi des fiches techniques.</p>
        </div>
        @if($estResponsable)
            @droit('technique.demandes.creer')
                <a href="{{ route('technique.demandes.create') }}" class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4 text-secondary" aria-hidden="true"></i> Nouvelle demande
                </a>
            @enddroit
        @endif
    </div>

    @include('economat.partials.flash')

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        @foreach([
            ['À prendre', $compteurs['ouvertes'], 'inbox', 'text-amber-700 bg-amber-50'],
            ['Urgentes', $compteurs['urgentes'], 'siren', 'text-red-700 bg-red-50'],
            ['En cours', $compteurs['en_cours'], 'hammer', 'text-indigo-700 bg-indigo-50'],
            ['Fiches à viser', $compteurs['a_viser'], 'clipboard-check', 'text-purple-700 bg-purple-50'],
            ["Terminées ajourd'hui", $compteurs['terminees'], 'circle-check', 'text-green-700 bg-green-50'],
        ] as [$libelle, $nombre, $icone, $teinte])
            <div class="bg-white rounded-xl border border-secondary/20 p-4 flex items-center gap-3 shadow-sm">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center {{ $teinte }}">
                    <i data-lucide="{{ $icone }}" class="w-5 h-5" aria-hidden="true"></i>
                </span>
                <div>
                    <p class="text-2xl font-semibold text-primary leading-none">{{ $nombre }}</p>
                    <p class="text-xs text-primary/60 mt-1">{{ $libelle }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if($aViser->isNotEmpty())
        <section class="mb-6 rounded-xl border border-purple-200 bg-purple-50/50 p-4" aria-labelledby="titre-viser">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 id="titre-viser" class="font-heading font-semibold text-purple-900 flex items-center gap-2">
                        <i data-lucide="clipboard-check" class="w-5 h-5 text-purple-700"></i> Fiches d'intervention à viser ({{ $aViser->count() }})
                    </h2>
                    <p class="text-xs text-purple-800/80">Travaux et pièces renseignés par l'équipe : en attente du visa du responsable technique.</p>
                </div>
            </div>
            @include('technique.partials.liste', ['demandes' => $aViser, 'vide' => ''])
        </section>
    @endif

    @if($miennes->isNotEmpty())
        <section class="mb-6" aria-labelledby="titre-miennes">
            <h2 id="titre-miennes" class="font-heading font-semibold text-primary mb-2 flex items-center gap-2">
                <i data-lucide="wrench" class="w-4 h-4 text-secondary"></i> Mes interventions confiées
            </h2>
            @include('technique.partials.liste', ['demandes' => $miennes, 'vide' => ''])
        </section>
    @endif

    <section class="mb-6" aria-labelledby="titre-a-prendre">
        <h2 id="titre-a-prendre" class="font-heading font-semibold text-primary mb-1">Interventions à prendre / affecter</h2>
        <p class="text-xs text-primary/55 mb-2">
            Demandes ouvertes en attente de prise en charge.
            @if($mesSpecialites !== []) Celles de vos spécialités en priorité. @endif
        </p>
        @include('technique.partials.liste', ['demandes' => $aPrendre, 'vide' => 'Aucune demande en attente.', 'prendre' => $peutPrendre])
    </section>

    <section class="mb-6" aria-labelledby="titre-en-cours">
        <h2 id="titre-en-cours" class="font-heading font-semibold text-primary mb-2">En cours d'exécution</h2>
        @include('technique.partials.liste', ['demandes' => $enCours, 'vide' => 'Aucune intervention en cours.'])
    </section>

    <section aria-labelledby="titre-terminees">
        <div class="flex items-center justify-between mb-2">
            <h2 id="titre-terminees" class="font-heading font-semibold text-primary">Terminées ces 7 derniers jours</h2>
            <a href="{{ route('technique.demandes.index', ['statut' => 'done']) }}" class="text-xs font-medium text-primary/70 hover:text-primary underline">Toutes les terminées</a>
        </div>
        @include('technique.partials.liste', ['demandes' => $terminees, 'vide' => 'Aucune intervention terminée cette semaine.'])
    </section>
</div>
@endsection

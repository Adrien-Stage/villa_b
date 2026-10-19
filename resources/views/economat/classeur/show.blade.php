@extends('layouts.hotel')

@section('title', 'Import ' . $import->reference . ' — Classeur Excel')

@section('content')
@php
    $resume = $analyse['resume'] ?? [];
    $erreurs = $analyse['erreurs'] ?? [];
    $avertissements = $analyse['avertissements'] ?? [];
    $articles = collect($analyse['articles'] ?? [])->where('action', '!=', 'inchange');
    $mouvements = $analyse['mouvements'] ?? [];
    $qte = fn ($q) => rtrim(rtrim(number_format((float) $q, 3, ',', ' '), '0'), ',');
    $ou = fn ($e) => trim(($e['onglet'] ?? '') . ($e['ligne'] ? ' · ligne ' . $e['ligne'] : ''), ' ·');
    $couleurs = [
        'Stock initial' => 'border-slate-200 bg-slate-50 text-slate-700',
        'Entrée'        => 'border-green-200 bg-green-50 text-green-700',
        'Sortie'        => 'border-red-200 bg-red-50 text-red-700',
        'Retour'        => 'border-sky-200 bg-sky-50 text-sky-700',
        'Ajustement'    => 'border-amber-200 bg-amber-50 text-amber-800',
    ];
@endphp
<div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('economat.classeur.index') }}" class="text-xs text-primary/60 hover:text-primary inline-flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5" aria-hidden="true"></i> Classeur Excel
            </a>
            <h1 class="mt-1 text-xl font-heading font-semibold text-primary">Import {{ $import->reference }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                {{ $import->magasinLabel() }} · {{ $import->file_name }} · déposé le {{ $import->created_at->format('d/m/Y à H:i') }} par {{ $import->creator?->name ?? '—' }}
            </p>
        </div>
        <span @class([
            'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold border',
            'border-green-200 bg-green-50 text-green-700' => ! $import->isDraft(),
            'border-amber-200 bg-amber-50 text-amber-800' => $import->isDraft(),
        ])>{{ $import->statusLabel() }}</span>
    </div>

    @include('economat.partials.flash')

    @unless($import->isDraft())
        <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg flex flex-wrap items-center justify-between gap-3">
            <span class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
                Importé le {{ $import->applied_at?->format('d/m/Y à H:i') }} par {{ $import->applier?->name ?? '—' }}.
            </span>
            @droit('economat.classeur.export')
                <a href="{{ route('economat.classeur.export', array_filter(['depot' => $import->service_store_id])) }}" class="inline-flex items-center gap-1.5 font-medium underline">
                    <i data-lucide="download" class="w-4 h-4" aria-hidden="true"></i> Télécharger le classeur à jour
                </a>
            @enddroit
        </div>
    @endunless

    {{-- Résumé --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-secondary/20 p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50 font-semibold">Articles</p>
            <p class="mt-1 text-lg font-semibold text-primary">{{ $resume['articles_crees'] ?? 0 }} <span class="text-sm font-normal text-primary/60">créé(s)</span></p>
            <p class="text-xs text-primary/60">{{ $resume['articles_modifies'] ?? 0 }} modifié(s) · {{ $resume['articles_inchanges'] ?? 0 }} inchangé(s)</p>
        </div>
        <div class="bg-white rounded-xl border border-secondary/20 p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50 font-semibold">Mouvements {{ $import->isDraft() ? 'à enregistrer' : 'enregistrés' }}</p>
            <p class="mt-1 text-lg font-semibold text-primary">{{ $resume['mouvements'] ?? 0 }}</p>
            <p class="text-xs text-primary/60">
                {{ collect($resume['par_type'] ?? [])->filter()->map(fn ($nombre, $type) => $nombre . ' ' . mb_strtolower($type))->implode(' · ') ?: 'aucun' }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-secondary/20 p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50 font-semibold">Valeur des arrivées</p>
            <p class="mt-1 text-lg font-semibold text-primary">{{ number_format($resume['valeur_entrees'] ?? 0, 0, ',', ' ') }} <span class="text-sm font-normal text-primary/60">FCFA</span></p>
            <p class="text-xs text-primary/60">{{ $resume['categories_creees'] ?? 0 }} catégorie(s) · {{ $resume['fournisseurs_crees'] ?? 0 }} fournisseur(s) à créer</p>
        </div>
        <div class="bg-white rounded-xl border border-secondary/20 p-4">
            <p class="text-[11px] uppercase tracking-wider text-primary/50 font-semibold">Déjà dans l'application</p>
            <p class="mt-1 text-lg font-semibold text-primary">{{ ($resume['deja_enregistres'] ?? 0) + ($resume['deja_importes'] ?? 0) }}</p>
            <p class="text-xs text-primary/60">{{ $resume['deja_enregistres'] ?? 0 }} numérotée(s) · {{ $resume['deja_importes'] ?? 0 }} déjà importée(s) : non rejouées</p>
        </div>
    </div>

    @if($erreurs !== [])
        <section class="mb-6 rounded-xl border border-red-200 bg-red-50/60 p-4" aria-labelledby="titre-erreurs">
            <h2 id="titre-erreurs" class="font-heading font-semibold text-red-800 flex items-center gap-2">
                <i data-lucide="circle-x" class="w-4 h-4" aria-hidden="true"></i> {{ count($erreurs) }} erreur(s) à corriger dans le fichier
            </h2>
            <p class="text-xs text-red-800/80 mt-0.5">Rien ne sera importé tant qu'il en reste : corrigez le classeur puis déposez-le de nouveau.</p>
            <ul class="mt-3 space-y-1 text-sm text-red-900 max-h-96 overflow-y-auto">
                @foreach($erreurs as $e)
                    <li class="flex gap-2"><span class="shrink-0 text-xs font-semibold text-red-700/80 min-w-[9rem]">{{ $ou($e) ?: 'Classeur' }}</span><span>{{ $e['message'] }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($avertissements !== [])
        <section class="mb-6 rounded-xl border border-amber-200 bg-amber-50/70 p-4" aria-labelledby="titre-avertissements">
            <h2 id="titre-avertissements" class="font-heading font-semibold text-amber-900 flex items-center gap-2">
                <i data-lucide="triangle-alert" class="w-4 h-4" aria-hidden="true"></i> {{ $import->isDraft() ? "À savoir avant d'importer" : "Signalé à l'import" }}
            </h2>
            <ul class="mt-2 space-y-1 text-sm text-amber-900 max-h-72 overflow-y-auto">
                @foreach($avertissements as $e)
                    <li class="flex gap-2"><span class="shrink-0 text-xs font-semibold text-amber-800/80 min-w-[9rem]">{{ $ou($e) ?: 'Classeur' }}</span><span>{{ $e['message'] }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($import->isDraft())
        <div class="mb-8 flex flex-wrap items-center gap-3">
            @if($peutConfirmer)
                <form method="POST" action="{{ route('economat.classeur.confirm', $import) }}"
                      onsubmit="return confirm('Enregistrer cet import ? Les articles et les {{ $resume['mouvements'] ?? 0 }} mouvement(s) seront écrits au stock.')">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                        <i data-lucide="check" class="w-4 h-4" aria-hidden="true"></i> Confirmer l'import
                    </button>
                </form>
            @endif
            <a href="{{ route('economat.classeur.index', array_filter(['depot' => $import->service_store_id])) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-secondary/30 bg-white text-primary text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                <i data-lucide="file-up" class="w-4 h-4" aria-hidden="true"></i> Déposer un fichier corrigé
            </a>
        </div>
    @endif

    @if(($analyse['categories_a_creer'] ?? []) !== [] || ($analyse['fournisseurs_a_creer'] ?? []) !== [])
        <section class="mb-6 grid gap-4 md:grid-cols-2">
            @if(($analyse['categories_a_creer'] ?? []) !== [])
                <div class="bg-white rounded-xl border border-secondary/20 p-4">
                    <h2 class="font-heading font-semibold text-primary text-sm">Catégories {{ $import->isDraft() ? 'à créer' : 'créées' }}</h2>
                    <p class="mt-1 text-sm text-primary/70">{{ implode(', ', $analyse['categories_a_creer']) }}</p>
                </div>
            @endif
            @if(($analyse['fournisseurs_a_creer'] ?? []) !== [])
                <div class="bg-white rounded-xl border border-secondary/20 p-4">
                    <h2 class="font-heading font-semibold text-primary text-sm">Fournisseurs {{ $import->isDraft() ? 'à créer' : 'créés' }}</h2>
                    <p class="mt-1 text-sm text-primary/70">{{ implode(', ', array_keys($analyse['fournisseurs_a_creer'])) }}</p>
                </div>
            @endif
        </section>
    @endif

    @if($articles->isNotEmpty())
        {{-- Repliée quand elle est longue : l'essentiel est dans le résumé. --}}
        <details class="mb-6 group" @if($articles->count() <= 25) open @endif>
            <summary class="cursor-pointer list-none flex items-center gap-2 mb-2 font-heading font-semibold text-primary">
                <i data-lucide="chevron-right" class="w-4 h-4 transition-transform group-open:rotate-90" aria-hidden="true"></i>
                Articles {{ $import->isDraft() ? 'créés ou modifiés à l\'import' : 'créés ou modifiés' }} ({{ $articles->count() }})
            </summary>
            <x-table :rows="$articles" caption="Articles de l'import">
                <x-slot:head>
                    <x-table.col>Référence</x-table.col>
                    <x-table.col>Désignation</x-table.col>
                    <x-table.col hide="sm">Unité</x-table.col>
                    <x-table.col>Changement</x-table.col>
                </x-slot:head>
                @foreach($articles as $a)
                    <x-table.row>
                        <x-table.cell nowrap class="text-xs font-medium">{{ $a['reference'] }}</x-table.cell>
                        <x-table.cell class="text-sm">{{ $a['name'] }}</x-table.cell>
                        <x-table.cell hide="sm" class="text-xs text-primary/70">{{ $a['unit'] }}</x-table.cell>
                        <x-table.cell class="text-xs text-primary/70">
                            @if($a['action'] === 'creer')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold border border-green-200 bg-green-50 text-green-700">Nouvel article</span>
                            @else
                                @foreach($a['changements'] as [$libelle, $avant, $apres])
                                    <span class="block">{{ $libelle }} : <span class="line-through text-primary/40">{{ $avant }}</span> → {{ $apres }}</span>
                                @endforeach
                            @endif
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </details>
    @endif

    @if($mouvements !== [])
        <details class="group" @if(count($mouvements) <= 25) open @endif>
            <summary class="cursor-pointer list-none flex items-center gap-2 mb-2 font-heading font-semibold text-primary">
                <i data-lucide="chevron-right" class="w-4 h-4 transition-transform group-open:rotate-90" aria-hidden="true"></i>
                Mouvements {{ $import->isDraft() ? 'à enregistrer' : 'enregistrés' }}, dans l'ordre ({{ count($mouvements) }})
            </summary>
            <x-table :rows="$mouvements" caption="Mouvements de l'import">
                <x-slot:head>
                    <x-table.col>Date</x-table.col>
                    <x-table.col>Article</x-table.col>
                    <x-table.col>Type</x-table.col>
                    <x-table.col align="right">Quantité</x-table.col>
                    <x-table.col align="right" hide="md">Coût unit.</x-table.col>
                    <x-table.col hide="lg">Document</x-table.col>
                    <x-table.col hide="lg">Destination / motif</x-table.col>
                </x-slot:head>
                @foreach($mouvements as $m)
                    <x-table.row>
                        <x-table.cell nowrap class="text-xs">
                            {{ \Carbon\Carbon::parse($m['date'])->format('d/m/Y') }}
                            @if(($m['date_effective'] ?? $m['date']) !== $m['date'])
                                <span class="block text-[10px] text-amber-700">enregistré le {{ \Carbon\Carbon::parse($m['date_effective'])->format('d/m/Y') }}</span>
                            @endif
                        </x-table.cell>
                        <x-table.cell class="text-sm">
                            {{ $m['designation'] }}
                            <span class="block text-[11px] text-primary/50">{{ $m['reference'] }} · ligne {{ $m['ligne'] }}</span>
                        </x-table.cell>
                        <x-table.cell nowrap>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold border {{ $couleurs[$m['type']] ?? '' }}">{{ $m['type'] }}</span>
                        </x-table.cell>
                        <x-table.cell align="right" nowrap class="font-medium">{{ $qte($m['quantite']) }} <span class="text-xs font-normal text-primary/60">{{ $m['unite'] }}</span></x-table.cell>
                        <x-table.cell align="right" nowrap hide="md" class="text-xs text-primary/70">{{ $m['cout'] !== null ? number_format($m['cout'], 0, ',', ' ') : '—' }}</x-table.cell>
                        <x-table.cell hide="lg" class="text-xs text-primary/70">
                            {{ $m['document'] ?? '' }}
                            @if(($m['pointe'] ?? null) === true)<span class="block text-[10px] text-green-700">pointé</span>@endif
                        </x-table.cell>
                        <x-table.cell hide="lg" class="text-xs text-primary/70">{{ collect([$m['destination'] ?? null, $m['motif'] ?? null])->filter()->implode(' — ') }}</x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </details>
    @endif
</div>
@endsection

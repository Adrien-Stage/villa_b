@extends('layouts.hotel')

@section('title', $demande->reference)

@section('content')
@php
    $qte = fn ($q) => rtrim(rtrim(number_format(abs((float) $q), 3, ',', ' '), '0'), ',');
    $champ = 'w-full rounded-lg border border-secondary/30 text-sm px-3 py-2';
    $bouton = 'inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-colors';
@endphp
<div class="max-w-6xl mx-auto">
    <a href="{{ route('technique.demandes.index') }}" class="text-xs text-primary/60 hover:text-primary inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5" aria-hidden="true"></i> Demandes d'intervention
    </a>
    <div class="mt-1 mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">{{ $demande->title }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                {{ $demande->reference }} · {{ $demande->lieu() }} · {{ $demande->specialtyLabel() }}
            </p>
        </div>
        <span class="inline-flex flex-wrap gap-1.5">@include('technique.partials.badges')</span>
    </div>

    @include('economat.partials.flash')
    @if($errors->any())
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- La demande et son histoire --}}
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white rounded-xl border border-secondary/20 p-5">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-xs text-primary/50">Demandée par</dt><dd class="text-primary">{{ $demande->requester?->name ?? '—' }} · {{ $demande->departmentLabel() }}</dd></div>
                    <div><dt class="text-xs text-primary/50">Le</dt><dd class="text-primary">{{ $demande->created_at->format('d/m/Y à H:i') }}</dd></div>
                    <div><dt class="text-xs text-primary/50">Technicien</dt><dd class="text-primary">{{ $demande->technician?->name ?? 'Pas encore affectée' }}</dd></div>
                    <div><dt class="text-xs text-primary/50">Chambre</dt>
                        <dd class="text-primary">
                            {{ $demande->room ? 'Chambre ' . $demande->room->number . ' — ' . $demande->room->status->label() : '—' }}
                            @if($demande->room_blocked)
                                <span class="text-xs text-orange-700">{{ $demande->isActive() ? '(retirée de la vente)' : '(retirée de la vente pendant l\'intervention)' }}</span>
                            @endif
                        </dd>
                    </div>
                    @if($demande->dureeMinutes() !== null)
                        <div><dt class="text-xs text-primary/50">Durée de l'intervention</dt><dd class="text-primary">{{ intdiv($demande->dureeMinutes(), 60) }} h {{ str_pad($demande->dureeMinutes() % 60, 2, '0', STR_PAD_LEFT) }}</dd></div>
                    @endif
                    @if($demande->source === \App\Models\MaintenanceRequest::SOURCE_HOUSEKEEPING)
                        <div><dt class="text-xs text-primary/50">Origine</dt><dd class="text-primary">Signalement du housekeeping</dd></div>
                    @endif
                </dl>
                @if($demande->description)
                    <p class="mt-4 text-sm text-primary/80 whitespace-pre-line border-t border-secondary/15 pt-4">{{ $demande->description }}</p>
                @endif
                @if($demande->resolution)
                    <div class="mt-4 rounded-lg bg-green-50 border border-green-200 p-3">
                        <p class="text-xs font-semibold text-green-800">Compte rendu de l'intervention</p>
                        <p class="mt-1 text-sm text-green-900 whitespace-pre-line">{{ $demande->resolution }}</p>
                    </div>
                @endif
                @if($demande->cancel_reason)
                    <p class="mt-4 text-sm text-primary/70"><span class="font-semibold">Annulée :</span> {{ $demande->cancel_reason }}</p>
                @endif
            </section>

            @if($demande->parts->isNotEmpty())
                <section aria-labelledby="titre-pieces">
                    <h2 id="titre-pieces" class="font-heading font-semibold text-primary mb-2">Pièces employées</h2>
                    <x-table :rows="$demande->parts" caption="Pièces employées">
                        <x-slot:head>
                            <x-table.col>Pièce</x-table.col>
                            <x-table.col align="right">Quantité</x-table.col>
                            <x-table.col align="right" hide="sm">Valeur (FCFA)</x-table.col>
                            <x-table.col hide="md">Le</x-table.col>
                        </x-slot:head>
                        @foreach($demande->parts as $piece)
                            <x-table.row>
                                <x-table.cell class="text-sm">{{ $piece->item?->name ?? '—' }}</x-table.cell>
                                <x-table.cell align="right" nowrap>{{ $qte($piece->quantity) }} <span class="text-xs text-primary/60">{{ $piece->item?->unit }}</span></x-table.cell>
                                <x-table.cell align="right" hide="sm" class="text-xs">{{ number_format(abs((float) $piece->quantity) * $piece->unit_cost / 100, 0, ',', ' ') }}</x-table.cell>
                                <x-table.cell hide="md" class="text-xs text-primary/60">{{ $piece->occurred_at?->format('d/m H:i') }}</x-table.cell>
                            </x-table.row>
                        @endforeach
                    </x-table>
                    <p class="mt-2 text-right text-xs text-primary/70">Valeur des pièces : <span class="font-semibold text-primary">{{ number_format($demande->valeurDesPieces() / 100, 0, ',', ' ') }} FCFA</span></p>
                </section>
            @endif

            <section class="bg-white rounded-xl border border-secondary/20 p-5" aria-labelledby="titre-historique">
                <h2 id="titre-historique" class="font-heading font-semibold text-primary mb-3">Historique</h2>
                <ol class="relative border-l border-secondary/30 ml-2 space-y-4">
                    @foreach($demande->events as $etape)
                        <li class="ml-4">
                            <span class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full bg-secondary/60 border-2 border-white" aria-hidden="true"></span>
                            <p class="text-sm font-medium text-primary">{{ $etape->libelle() }} <span class="font-normal text-primary/60">— {{ $etape->user?->name ?? 'Système' }}</span></p>
                            <p class="text-[11px] text-primary/50">{{ $etape->created_at?->format('d/m/Y à H:i') }}</p>
                            @if($etape->notes)
                                <p class="mt-1 text-xs text-primary/75 whitespace-pre-line">{{ $etape->notes }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- Ce que l'on peut faire --}}
        <aside class="space-y-4">
            @if($peut['prendre'])
                <form method="POST" action="{{ route('technique.demandes.take', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4">
                    @csrf
                    <p class="text-sm text-primary/70 mb-3">Personne n'a encore cette demande.</p>
                    <button type="submit" class="{{ $bouton }} w-full bg-primary text-white hover:bg-surface-dark"><i data-lucide="hand" class="w-4 h-4" aria-hidden="true"></i> Je la prends</button>
                </form>
            @endif

            @if($peut['affecter'])
                <form method="POST" action="{{ route('technique.demandes.assign', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4 space-y-3">
                    @csrf
                    <h2 class="font-heading font-semibold text-primary text-sm">{{ $demande->assigned_to ? 'Réaffecter' : 'Affecter' }}</h2>
                    <label class="block text-xs text-primary/70">Technicien
                        <select name="technician_id" required class="mt-1 {{ $champ }}">
                            <option value="">Choisir…</option>
                            @foreach($techniciens as $technicien)
                                @php($siennes = $technicien->technicianSpecialties->pluck('specialty'))
                                <option value="{{ $technicien->id }}" @selected($demande->assigned_to === $technicien->id)>
                                    {{ $technicien->name }}{{ $siennes->contains($demande->specialty) ? ' ★' : '' }}{{ $siennes->isNotEmpty() ? ' — ' . $siennes->map(fn ($s) => \App\Models\MaintenanceRequest::SPECIALTIES[$s] ?? $s)->implode(', ') : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block text-xs text-primary/70">Spécialité
                            <select name="specialty" class="mt-1 {{ $champ }}">
                                @foreach(\App\Models\MaintenanceRequest::SPECIALTIES as $cle => $libelle)
                                    <option value="{{ $cle }}" @selected($demande->specialty === $cle)>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs text-primary/70">Priorité
                            <select name="priority" class="mt-1 {{ $champ }}">
                                @foreach(\App\Models\MaintenanceRequest::PRIORITIES as $cle => $libelle)
                                    <option value="{{ $cle }}" @selected($demande->priority === $cle)>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <p class="text-[11px] text-primary/50">★ : technicien de cette spécialité.</p>
                    <button type="submit" class="{{ $bouton }} w-full bg-primary text-white hover:bg-surface-dark"><i data-lucide="user-check" class="w-4 h-4" aria-hidden="true"></i> Affecter</button>
                </form>
            @endif

            @if($peut['commencer'])
                <form method="POST" action="{{ route('technique.demandes.start', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4">
                    @csrf
                    <button type="submit" class="{{ $bouton }} w-full bg-indigo-600 text-white hover:bg-indigo-700"><i data-lucide="play" class="w-4 h-4" aria-hidden="true"></i> Commencer l'intervention</button>
                </form>
            @endif

            @if($peut['pieces'])
                <div class="bg-white rounded-xl border border-secondary/20 p-4">
                    <h2 class="font-heading font-semibold text-primary text-sm mb-2">Pièces employées</h2>
                    @if($depots->isEmpty())
                        <p class="text-xs text-primary/60">Aucun dépôt technique : l'économe le crée dans Économat › Dépôts de service, pour le service « Technique / Maintenance ».</p>
                    @else
                        @foreach($depots as $depot)
                            @php($disponibles = $stocks->get($depot->id, collect())->sortBy(fn ($s) => $s->item->name))
                            <form method="POST" action="{{ route('technique.demandes.parts', $demande) }}" class="space-y-2" x-data="{ filtre: '' }">
                                @csrf
                                <input type="hidden" name="depot" value="{{ $depot->id }}">
                                <p class="text-xs text-primary/60">Du dépôt « {{ $depot->name }} » — saisissez la quantité de chaque pièce sortie.</p>
                                @if($disponibles->isEmpty())
                                    <p class="text-xs text-primary/50">Ce dépôt est vide.</p>
                                @else
                                    <input type="search" x-model="filtre" placeholder="Chercher une pièce…" class="{{ $champ }}">
                                    <div class="max-h-64 overflow-y-auto divide-y divide-secondary/10 border border-secondary/15 rounded-lg">
                                        @foreach($disponibles as $stock)
                                            <label class="flex items-center gap-2 px-2 py-1.5 text-xs" x-show="!filtre || @js(mb_strtolower($stock->item->name . ' ' . $stock->item->reference)).includes(filtre.toLowerCase())">
                                                <span class="flex-1 text-primary">{{ $stock->item->name }} <span class="text-primary/45">({{ $qte($stock->current_stock) }} {{ $stock->item->unit }})</span></span>
                                                <input type="number" name="pieces[{{ $stock->stock_item_id }}]" min="0" step="any" max="{{ (float) $stock->current_stock }}"
                                                       class="w-20 rounded border border-secondary/30 px-2 py-1 text-right" aria-label="Quantité de {{ $stock->item->name }}">
                                            </label>
                                        @endforeach
                                    </div>
                                    <button type="submit" class="{{ $bouton }} w-full border border-secondary/30 bg-white text-primary hover:bg-gray-50"><i data-lucide="package-minus" class="w-4 h-4" aria-hidden="true"></i> Enregistrer les pièces</button>
                                @endif
                            </form>
                        @endforeach
                    @endif
                </div>
            @endif

            @if($peut['terminer'])
                <form method="POST" action="{{ route('technique.demandes.complete', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4 space-y-3">
                    @csrf
                    <h2 class="font-heading font-semibold text-primary text-sm">Terminer</h2>
                    <textarea name="resolution" rows="4" required maxlength="3000" placeholder="Ce qui a été fait, ce qui reste à surveiller…" class="{{ $champ }}">{{ old('resolution') }}</textarea>
                    <button type="submit" class="{{ $bouton }} w-full bg-green-600 text-white hover:bg-green-700"><i data-lucide="circle-check" class="w-4 h-4" aria-hidden="true"></i> Intervention terminée</button>
                    @if($demande->room_blocked)
                        <p class="text-[11px] text-primary/50">La chambre repassera au ménage (ou au client s'il loge encore).</p>
                    @endif
                </form>
            @endif

            @if($peut['rouvrir'])
                <form method="POST" action="{{ route('technique.demandes.reopen', $demande) }}" class="bg-white rounded-xl border border-amber-200 p-4 space-y-3">
                    @csrf
                    <h2 class="font-heading font-semibold text-primary text-sm">Le problème persiste ?</h2>
                    <p class="text-xs text-primary/60">Vous pouvez rouvrir la demande jusqu'au {{ $demande->completed_at->addDays(\App\Models\MaintenanceRequest::DELAI_REOUVERTURE)->format('d/m/Y') }}.</p>
                    <textarea name="motif" rows="3" required maxlength="1000" placeholder="Ce qui ne va toujours pas…" class="{{ $champ }}"></textarea>
                    <button type="submit" class="{{ $bouton }} w-full bg-amber-600 text-white hover:bg-amber-700"><i data-lucide="rotate-ccw" class="w-4 h-4" aria-hidden="true"></i> Rouvrir la demande</button>
                </form>
            @endif

            @if($peut['annuler'])
                <form method="POST" action="{{ route('technique.demandes.cancel', $demande) }}" class="bg-white rounded-xl border border-secondary/20 p-4 space-y-2"
                      x-data="{ ouvert: false }">
                    @csrf
                    <button type="button" x-show="!ouvert" @click="ouvert = true" class="text-xs font-medium text-red-700 hover:underline">Annuler la demande…</button>
                    <div x-show="ouvert" x-cloak class="space-y-2">
                        <input type="text" name="motif" maxlength="500" required placeholder="Pourquoi ?" class="{{ $champ }}">
                        <button type="submit" class="{{ $bouton }} w-full border border-red-200 text-red-700 hover:bg-red-50">Annuler la demande</button>
                    </div>
                </form>
            @endif
        </aside>
    </div>
</div>
@endsection

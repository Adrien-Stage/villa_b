@extends('layouts.hotel')

@section('title', 'Mini-bar — chambre ' . $room->number)

@section('content')
@php
    $fcfa = fn (int $c) => number_format($c / 100, 0, ',', ' ') . ' F';
    $manque = $lignes->sum(fn ($l) => $l->manquant());
    $vide = $room->miniBarVide();
    $peutRecharger = $manque > 0 && (!$sejour || $vide);
@endphp

<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="wine" class="w-7 h-7 text-primary"></i>
                <span>Mini-bar — chambre {{ $room->number }}</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1">
                {{ $room->roomType?->name }} · {{ $room->status->label() }}
                @if($sejour)
                    · client en séjour
                    @if($sejour->minibar_check_requested_at)
                        · <span class="text-red-700 font-medium">départ : la réception attend le relevé (demandé à {{ $sejour->minibar_check_requested_at->format('H:i') }})</span>
                    @endif
                @elseif($releveDu)
                    · <span class="text-red-700 font-medium">client parti sans relevé</span>
                @endif
            </p>
        </div>
        <a href="{{ route('housekeeping.index') }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Housekeeping
        </a>
    </div>

    @include('housekeeping.partials.flash')

    @if($lignes->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-10 text-center border border-dashed border-secondary/30">
            <p class="text-sm text-primary/50">Cette chambre n'a pas de mini-bar.</p>
            @droit('housekeeping.minibar.voir')
                <a href="{{ route('housekeeping.minibar.index') }}" class="inline-block mt-2 text-xs text-secondary hover:text-primary underline">Régler les dotations</a>
            @enddroit
        </div>
    @else
        {{-- ═══ Relevé ═══ --}}
        @droit('housekeeping.minibar.check')
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Relevé des consommations</h2>
                <p class="text-xs text-primary/50 mt-0.5">
                    Comptez ce qui manque dans le mini-bar et indiquez-le pour chaque article, 0 si rien n'a été pris.
                    @if($sejour) La consommation part au folio du client. @endif
                </p>
            </div>
            @if(!$peutRelever)
                <div class="px-5 py-6 text-sm text-primary/50">
                    Pas de relevé à faire : la chambre n'a pas de client, et aucun relevé n'y est dû.
                </div>
            @else
                <form method="POST" action="{{ route('housekeeping.minibar.check', $room) }}" class="p-5 space-y-4">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-[11px] uppercase tracking-wider text-primary/50">
                                <tr>
                                    <th class="py-2 text-left font-semibold">Article</th>
                                    <th class="py-2 px-3 text-right font-semibold">Dans le mini-bar</th>
                                    @if($voitMontants)<th class="py-2 px-3 text-right font-semibold">Prix</th>@endif
                                    <th class="py-2 text-right font-semibold w-36">Consommé</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-secondary/10">
                                @foreach($lignes as $ligne)
                                    <tr>
                                        <td class="py-2 font-medium text-primary">{{ $ligne->article->name }}</td>
                                        <td class="py-2 px-3 text-right tabular-nums">{{ $ligne->quantity }}</td>
                                        @if($voitMontants)<td class="py-2 px-3 text-right tabular-nums text-primary/60">{{ $fcfa($ligne->article->price) }}</td>@endif
                                        <td class="py-2 text-right">
                                            <label class="sr-only" for="consomme-{{ $ligne->minibar_article_id }}">Consommé — {{ $ligne->article->name }}</label>
                                            <input type="number" inputmode="numeric" min="0" max="{{ $ligne->quantity }}" step="1" required
                                                   id="consomme-{{ $ligne->minibar_article_id }}"
                                                   name="consommes[{{ $ligne->minibar_article_id }}]"
                                                   value="{{ old('consommes.' . $ligne->minibar_article_id) }}"
                                                   class="w-24 px-2.5 py-1.5 text-right text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <label for="notes" class="block text-xs text-primary/50 mb-1.5">Remarque (optionnel)</label>
                        <input type="text" id="notes" name="notes" maxlength="500" value="{{ old('notes') }}"
                               placeholder="Bouteille ouverte, emballage abîmé…"
                               class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                        Enregistrer le relevé
                    </button>
                </form>
            @endif
        </div>
        @enddroit

        {{-- ═══ Contenu et rechargement ═══ --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/20 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-heading font-semibold text-primary text-sm">Contenu du mini-bar</h2>
                    <p class="text-xs text-primary/50 mt-0.5">
                        @if($manque === 0)
                            Complet : chaque article est à sa dotation.
                        @elseif($sejour && !$vide)
                            Client en séjour : le mini-bar ne se recharge que lorsqu'il l'a vidé.
                        @else
                            {{ $manque }} article{{ $manque > 1 ? 's' : '' }} à remettre depuis le dépôt{{ $depot ? ' ' . $depot->name : '' }}.
                        @endif
                    </p>
                </div>
                @droit('housekeeping.minibar.restock')
                    @if($peutRecharger)
                        <form method="POST" action="{{ route('housekeeping.minibar.restock', $room) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold bg-amber-600 text-white hover:bg-amber-700 transition">
                                <i data-lucide="package-plus" class="w-4 h-4"></i> Recharger depuis le dépôt
                            </button>
                        </form>
                    @endif
                @enddroit
            </div>
            <table class="w-full text-sm">
                <thead class="bg-accent/10 text-[11px] uppercase tracking-wider text-primary/50">
                    <tr>
                        <th class="px-5 py-2 text-left font-semibold">Article</th>
                        <th class="px-3 py-2 text-right font-semibold">Dotation</th>
                        <th class="px-3 py-2 text-right font-semibold">Présent</th>
                        <th class="px-5 py-2 text-right font-semibold">À remettre</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($lignes as $ligne)
                        <tr>
                            <td class="px-5 py-2 text-primary">{{ $ligne->article->name }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $ligne->standard_quantity }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $ligne->quantity }}</td>
                            <td class="px-5 py-2 text-right tabular-nums {{ $ligne->manquant() > 0 ? 'text-amber-700 font-semibold' : 'text-primary/30' }}">{{ $ligne->manquant() ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Contrôle d'une chambre dont le dépôt n'a pas pu compléter le mini-bar. --}}
            @if($aControler && $manque > 0)
                @droit('housekeeping.inspect')
                    <form method="POST" action="{{ route('housekeeping.inspect', $room) }}" class="px-5 py-4 border-t border-secondary/20 bg-amber-50/50 flex flex-col sm:flex-row sm:items-end gap-3">
                        @csrf
                        <div class="flex-1">
                            <label for="motif_minibar" class="block text-xs text-primary/60 mb-1.5">Valider la chambre avec un mini-bar incomplet — pourquoi ?</label>
                            <input type="text" id="motif_minibar" name="motif_minibar" maxlength="255" required
                                   placeholder="Rupture au dépôt, demande à l'économat en cours…"
                                   class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-purple-600 text-white hover:bg-purple-700 transition">Valider quand même</button>
                    </form>
                @enddroit
            @endif
        </div>
    @endif

    {{-- ═══ Dotation propre à la chambre ═══ --}}
    @droit('housekeeping.minibar.rooms.modifier')
        @if($articles->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-heading font-semibold text-primary text-sm">Dotation de cette chambre</h2>
                <p class="text-xs text-primary/50 mt-0.5 mb-4">Elle remplace, pour cette chambre seulement, la dotation de son type.</p>
                <form method="POST" action="{{ route('housekeeping.minibar.rooms.update', $room) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    @php $actuelles = $room->minibarLines->pluck('standard_quantity', 'minibar_article_id'); @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach($articles as $article)
                            <label class="block">
                                <span class="block text-xs text-primary/60 mb-1">{{ $article->name }}</span>
                                <input type="number" min="0" max="99" step="1" name="quantites[{{ $article->id }}]"
                                       value="{{ $actuelles[$article->id] ?? 0 }}"
                                       class="w-full px-2.5 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">Enregistrer la dotation</button>
                </form>
            </div>
        @endif
    @enddroit

    {{-- ═══ Historique ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Derniers relevés</h2>
            </div>
            <ul class="divide-y divide-secondary/10">
                @forelse($releves as $releve)
                    <li class="px-5 py-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium text-primary">{{ $releve->checked_at->format('d/m H:i') }} · {{ $releve->occasionLabel() }}</span>
                            @if($voitMontants && $releve->total_amount > 0)
                                <span class="tabular-nums text-primary/70">{{ $fcfa($releve->total_amount) }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-primary/55">{{ $releve->user?->name ?? '—' }} — {{ $releve->resume() }}</p>
                        @if($releve->billing === \App\Models\MinibarCheck::BILLING_A_FACTURER)
                            <p class="text-xs text-red-700 mt-0.5">Consommé après le départ du client : à lui facturer.</p>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-6 text-sm text-primary/40 text-center">Aucun relevé.</li>
                @endforelse
            </ul>
        </div>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Derniers rechargements</h2>
            </div>
            <ul class="divide-y divide-secondary/10">
                @forelse($recharges as $recharge)
                    <li class="px-5 py-3 text-sm">
                        <span class="font-medium text-primary">{{ $recharge->restocked_at->format('d/m H:i') }} · {{ $recharge->reasonLabel() }}</span>
                        <p class="text-xs text-primary/55">{{ $recharge->user?->name ?? '—' }} — depuis {{ $recharge->store?->name }}</p>
                    </li>
                @empty
                    <li class="px-5 py-6 text-sm text-primary/40 text-center">Aucun rechargement.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

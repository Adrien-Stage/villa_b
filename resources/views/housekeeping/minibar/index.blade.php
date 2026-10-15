@extends('layouts.hotel')

@section('title', 'Mini-bars — Housekeeping')

@section('content')
@php
    $fcfa = fn (int $c) => number_format($c / 100, 0, ',', ' ');
    $champ = 'w-full px-2.5 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary';
    $actifs = $articles->where('is_active', true);
    $peutDoter = app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'housekeeping.minibar.standards.modifier');
@endphp

<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="wine" class="w-7 h-7 text-primary"></i>
                <span>Mini-bars</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                Les articles disposés en chambre, leur prix au client, la dotation de chaque type de chambre et
                le dépôt d'où la gouvernante recharge. Ce que l'équipe relève consommé part au folio du client.
            </p>
        </div>
        <a href="{{ route('housekeeping.index') }}"
           class="self-start sm:self-auto inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shrink-0">
            <i data-lucide="sparkles" class="w-4 h-4"></i> Housekeeping
        </a>
    </div>

    @include('housekeeping.partials.flash')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        {{-- ═══ Dépôt ═══ --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm">Dépôt du mini-bar</h2>
            <p class="text-xs text-primary/50 mt-0.5 mb-4">
                Le dépôt de service, alimenté par l'économat, d'où sortent les articles pour recharger les chambres.
            </p>
            @droit('housekeeping.minibar.depot')
                <form method="POST" action="{{ route('housekeeping.minibar.depot') }}" class="space-y-3">
                    @csrf
                    <select name="service_store_id" class="{{ $champ }}" aria-label="Dépôt du mini-bar">
                        <option value="">Aucun</option>
                        @foreach($depots as $d)
                            <option value="{{ $d->id }}" @selected($depot?->id === $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">Enregistrer</button>
                </form>
                @if($depots->isEmpty())
                    <p class="text-xs text-amber-700 mt-3">Aucun dépôt de service : l'économe en crée un (Économat › Dépôts de service).</p>
                @endif
            @else
                <p class="text-sm text-primary">{{ $depot?->name ?? 'Aucun dépôt choisi' }}</p>
            @enddroit
        </div>

        {{-- ═══ Ajout d'un article ═══ --}}
        @droit('housekeeping.minibar.articles.creer')
        <div class="bg-white rounded-xl shadow-sm p-5 xl:col-span-2">
            <h2 class="font-heading font-semibold text-primary text-sm">Ajouter un article</h2>
            <p class="text-xs text-primary/50 mt-0.5 mb-4">Un article de l'économat, compté à l'unité, et son prix de vente au client.</p>
            <form method="POST" action="{{ route('housekeeping.minibar.articles.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                @csrf
                <label class="block sm:col-span-2">
                    <span class="block text-xs text-primary/60 mb-1">Article de l'économat</span>
                    <select name="stock_item_id" required class="{{ $champ }}">
                        <option value="">Choisir…</option>
                        @foreach($articlesLibres as $item)
                            <option value="{{ $item->id }}" @selected((int) old('stock_item_id') === $item->id)>{{ $item->name }} ({{ $item->unit }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-xs text-primary/60 mb-1">Nom sur la note (optionnel)</span>
                    <input type="text" name="name" maxlength="120" value="{{ old('name') }}" class="{{ $champ }}">
                </label>
                <label class="block">
                    <span class="block text-xs text-primary/60 mb-1">Prix au client (FCFA)</span>
                    <input type="number" name="price" min="0" step="1" required value="{{ old('price') }}" class="{{ $champ }}">
                </label>
                <div class="sm:col-span-4">
                    <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">Ajouter au mini-bar</button>
                </div>
            </form>
        </div>
        @enddroit
    </div>

    {{-- ═══ Articles ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between">
            <h2 class="font-heading font-semibold text-primary text-sm">Articles du mini-bar</h2>
            <span class="text-xs text-primary/40">{{ $articles->count() }}</span>
        </div>
        @if($articles->isEmpty())
            <div class="px-5 py-8 text-sm text-primary/40 text-center">Aucun article : ajoutez-en un ci-dessus.</div>
        @else
            <div class="divide-y divide-secondary/10">
                @foreach($articles as $article)
                    @droit('housekeeping.minibar.articles.modifier')
                        <form method="POST" action="{{ route('housekeeping.minibar.articles.update', $article) }}"
                              class="px-5 py-3 grid grid-cols-2 sm:grid-cols-6 gap-3 items-end {{ $article->is_active ? '' : 'bg-gray-50' }}">
                            @csrf
                            @method('PUT')
                            <div class="col-span-2">
                                <span class="block text-[11px] text-primary/45 mb-1">{{ $article->item?->name }} · {{ $article->item?->unit }}</span>
                                <input type="text" name="name" required maxlength="120" value="{{ $article->name }}" class="{{ $champ }}" aria-label="Nom">
                            </div>
                            <label class="block">
                                <span class="block text-[11px] text-primary/45 mb-1">Prix (FCFA)</span>
                                <input type="number" name="price" min="0" step="1" required value="{{ intdiv($article->price, 100) }}" class="{{ $champ }}">
                            </label>
                            <label class="block">
                                <span class="block text-[11px] text-primary/45 mb-1">Ordre</span>
                                <input type="number" name="sort_order" min="0" step="1" value="{{ $article->sort_order }}" class="{{ $champ }}">
                            </label>
                            <label class="flex items-center gap-2 text-xs text-primary pb-2">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($article->is_active) class="rounded border-secondary/40 text-primary focus:ring-primary">
                                En service
                            </label>
                            <button type="submit" class="px-3 py-2 rounded-lg text-xs font-semibold border border-secondary/30 text-primary hover:bg-accent/10 transition-colors">Enregistrer</button>
                        </form>
                    @else
                        <div class="px-5 py-3 flex items-center justify-between text-sm">
                            <span class="text-primary">{{ $article->name }} @unless($article->is_active)<span class="text-xs text-primary/40">(hors service)</span>@endunless</span>
                            <span class="tabular-nums text-primary/70">{{ $fcfa($article->price) }} F</span>
                        </div>
                    @enddroit
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ Dotation par type de chambre ═══ --}}
    @if($actifs->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Dotation par type de chambre</h2>
                <p class="text-xs text-primary/50 mt-0.5">Enregistrer une dotation la reporte sur toutes les chambres du type ; chaque chambre peut ensuite s'en écarter depuis sa page.</p>
            </div>
            <div class="divide-y divide-secondary/10">
                @foreach($types as $type)
                    @php $quantites = $standards[$type->id] ?? collect(); @endphp
                    <form method="POST" action="{{ route('housekeeping.minibar.standards.update', $type) }}" class="px-5 py-4 space-y-3">
                        @csrf
                        @method('PUT')
                        <p class="text-sm font-semibold text-primary">{{ $type->name }}</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                            @foreach($actifs as $article)
                                <label class="block">
                                    <span class="block text-[11px] text-primary/55 mb-1 truncate" title="{{ $article->name }}">{{ $article->name }}</span>
                                    <input type="number" min="0" max="99" step="1" name="quantites[{{ $article->id }}]"
                                           value="{{ $quantites[$article->id] ?? 0 }}" class="{{ $champ }}" @readonly(!$peutDoter)>
                                </label>
                            @endforeach
                        </div>
                        @droit('housekeeping.minibar.standards.modifier')
                            <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                                Enregistrer pour les chambres {{ $type->name }}
                            </button>
                        @enddroit
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ═══ État des mini-bars ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20">
            <h2 class="font-heading font-semibold text-primary text-sm">Mini-bars des chambres</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-accent/10 text-[11px] uppercase tracking-wider text-primary/50">
                    <tr>
                        <th class="px-5 py-2 text-left font-semibold">Chambre</th>
                        <th class="px-3 py-2 text-left font-semibold">Statut</th>
                        <th class="px-3 py-2 text-right font-semibold">Contenu</th>
                        <th class="px-3 py-2 text-left font-semibold">État</th>
                        <th class="px-5 py-2 text-left font-semibold">Dernier relevé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($chambres as $chambre)
                        @php
                            $dotation = $chambre->minibarLines->sum('standard_quantity');
                            $present = $chambre->minibarLines->sum('quantity');
                            $releve = $derniersReleves[$chambre->id] ?? null;
                        @endphp
                        <tr>
                            <td class="px-5 py-2">
                                <a href="{{ route('housekeeping.minibar.rooms.show', $chambre) }}" class="font-semibold text-primary hover:underline">{{ $chambre->number }}</a>
                                <span class="text-xs text-primary/45">{{ $chambre->roomType?->name }}</span>
                            </td>
                            <td class="px-3 py-2 text-primary/70">{{ $chambre->status->label() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $dotation > 0 ? "{$present} / {$dotation}" : '—' }}</td>
                            <td class="px-3 py-2">
                                @if($dotation === 0)
                                    <span class="text-xs text-primary/35">Sans mini-bar</span>
                                @elseif($chambre->minibar_due_booking_id)
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] bg-red-50 text-red-700 border-red-200">Relevé dû</span>
                                @elseif($chambre->miniBarVide())
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] bg-amber-50 text-amber-800 border-amber-200">{{ $chambre->status === \App\Enums\RoomStatus::OCCUPIED ? 'Vidé par le client' : 'À remplir' }}</span>
                                @elseif(!$chambre->miniBarComplet())
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] bg-amber-50 text-amber-800 border-amber-200">Incomplet</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] bg-emerald-50 text-emerald-700 border-emerald-200">Complet</span>
                                @endif
                            </td>
                            <td class="px-5 py-2 text-xs text-primary/60">
                                @if($releve)
                                    {{ $releve->checked_at->format('d/m H:i') }} · {{ $releve->user?->name }} · {{ $releve->total_amount > 0 ? $fcfa($releve->total_amount) . ' F' : 'rien consommé' }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

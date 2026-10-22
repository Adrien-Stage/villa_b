{{--
    Réglages des mini-bars : le dépôt d'où l'on recharge, les articles et leur
    prix au client, la dotation de chaque type de chambre. Le responsable des
    étages les règle ; la gouvernante et le contrôle les consultent.
    Affiché dans Paramètres › Housekeeping.
--}}
@php
    $reglages = app(\App\Services\MinibarService::class)->reglages();
    $articles = $reglages['articles'];
    $depot = $reglages['depot'];
    $depots = $reglages['depots'];
    $types = $reglages['types'];
    $standards = $reglages['standards'];
    $articlesLibres = $reglages['articlesLibres'];
    $fcfa = fn (int $c) => number_format($c / 100, 0, ',', ' ');
    $champ = 'w-full px-2.5 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary';
    $actifs = $articles->where('is_active', true);
    $peutDoter = app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'housekeeping.minibar.standards.modifier');
    $peutChoisirDepot = app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'housekeeping.minibar.depot');
@endphp

<div class="space-y-5">
{{-- Sans dépôt, rien ne garnit les mini-bars : l'ajout d'articles reste fermé
     tant que le dépôt n'est pas choisi. Le dépôt se crée à l'économat. --}}
@if($depot === null)
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 flex items-start gap-3" role="status">
        <i data-lucide="warehouse" class="w-5 h-5 shrink-0 mt-0.5"></i>
        <div class="space-y-1">
            @if($depots->isEmpty())
                <p class="font-semibold">Aucun dépôt « Mini-bar » n'existe encore.</p>
                <p>Demandez à l'économe de créer un dépôt « Mini-bar » dans l'Économat (Dépôts de service). Sans ce dépôt, les mini-bars ne peuvent pas être garnis et aucun article ne peut être ajouté.</p>
            @elseif($peutChoisirDepot)
                <p class="font-semibold">Le dépôt du mini-bar n'est pas choisi.</p>
                <p>Choisissez-le ci-dessous avant d'ajouter des articles. S'il n'existe pas encore de dépôt « Mini-bar », demandez à l'économe de le créer dans l'Économat (Dépôts de service).</p>
            @else
                <p class="font-semibold">Le dépôt du mini-bar n'est pas choisi.</p>
                <p>Le responsable des étages le choisit ; s'il n'existe pas encore, l'économe doit créer un dépôt « Mini-bar » dans l'Économat.</p>
            @endif
        </div>
    </div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    {{-- ═══ Dépôt ═══ --}}
    <div class="bg-white rounded-xl shadow-sm p-5">
        <h2 class="font-heading font-semibold text-primary text-sm">Dépôt du mini-bar</h2>
        <p class="text-xs text-primary/50 mt-0.5 mb-4">
            Le dépôt de service, alimenté par l'économat, d'où sortent les articles pour recharger les chambres.
        </p>
        @droit('housekeeping.minibar.depot')
            <form data-reglages method="POST" action="{{ route('housekeeping.minibar.depot') }}" class="space-y-3">
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
                <p class="text-xs text-amber-700 mt-3">Aucun dépôt à choisir : demandez à l'économe de créer un dépôt « Mini-bar ».</p>
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
        @if($depot === null)
            <p class="rounded-lg border border-dashed border-secondary/30 px-4 py-6 text-center text-sm text-primary/50">
                Ajout fermé tant que le dépôt du mini-bar n'est pas choisi.
            </p>
        @else
        <form data-reglages method="POST" action="{{ route('housekeeping.minibar.articles.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            @csrf
            <label class="block sm:col-span-2">
                <span class="block text-xs text-primary/60 mb-1">Article de l'économat</span>
                <x-article-picker name="stock_item_id" :value="old('stock_item_id', '')" required
                    :items="$articlesLibres->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'reference' => $item->reference, 'unit' => $item->unit])->values()->all()" />
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
        @endif
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
        <div class="px-5 py-8 text-sm text-primary/40 text-center">
            {{ $depot === null ? "Aucun article : le dépôt du mini-bar doit d'abord être choisi." : 'Aucun article : ajoutez-en un ci-dessus.' }}
        </div>
    @else
        <div class="divide-y divide-secondary/10">
            @foreach($articles as $article)
                @droit('housekeeping.minibar.articles.modifier')
                    <form data-reglages method="POST" action="{{ route('housekeeping.minibar.articles.update', $article) }}"
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
                <form data-reglages method="POST" action="{{ route('housekeeping.minibar.standards.update', $type) }}" class="px-5 py-4 space-y-3">
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
</div>

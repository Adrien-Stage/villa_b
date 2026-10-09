{{--
    Onglet Économat : les unités de stockage des articles. L'économe tient la
    liste ; la fiche d'un article y choisit son unité. Renommer une unité
    renomme celle des articles qui l'emploient ; une unité employée se met
    hors service, elle ne se supprime pas.
--}}
@php
    $unites = \App\Models\StockUnit::query()->dansLOrdre()->get();
    $emplois = \App\Models\StockItem::query()->selectRaw('unit, COUNT(*) as total')->groupBy('unit')->pluck('total', 'unit');
@endphp

<div class="max-w-3xl">
    <h2 class="text-lg font-semibold text-primary">Unités de stockage</h2>
    <p class="mt-1 text-sm text-primary/60">
        Les unités dans lesquelles l'économat compte ses articles : kg, litre, pièce, casier…
        La fiche d'un article choisit la sienne dans cette liste, comme la réception directe et l'import des articles.
        Renommer une unité renomme aussi celle des articles qui l'emploient.
    </p>

    @if($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
        </div>
    @endif

    <ul class="mt-5 space-y-2">
        @forelse($unites as $unite)
            @php $articles = (int) ($emplois[$unite->name] ?? 0); @endphp
            <li class="rounded-xl border border-secondary/20 bg-gray-50 px-4 py-3 {{ $unite->is_active ? '' : 'opacity-70' }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @droit('economat.units.modifier')
                        <form method="POST" action="{{ route('economat.units.update', $unite) }}" class="flex flex-1 flex-wrap items-center gap-3">
                            @csrf @method('PUT')
                            <label class="sr-only" for="unite-{{ $unite->id }}">Nom de l'unité</label>
                            <input id="unite-{{ $unite->id }}" type="text" name="name" value="{{ $unite->name }}" required maxlength="20"
                                   class="w-40 rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                            <label class="flex items-center gap-2 text-xs text-primary">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($unite->is_active) class="rounded border-secondary/30">
                                En service
                            </label>
                            <button type="submit" class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-95">Enregistrer</button>
                        </form>
                    @else
                        <p class="flex-1 text-sm font-semibold text-primary">
                            {{ $unite->name }}
                            @unless($unite->is_active)<span class="font-normal text-primary/50">· hors service</span>@endunless
                        </p>
                    @enddroit

                    <div class="flex items-center gap-3 text-[11px] text-primary/50">
                        <span>{{ $articles }} article(s)</span>
                        @if($articles === 0)
                            @droit('economat.units.supprimer')
                                <form method="POST" action="{{ route('economat.units.destroy', $unite) }}" onsubmit="return confirm(@js('Supprimer l\'unité « '.$unite->name.' » ?'))">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="font-semibold text-red-700 hover:underline">Supprimer</button>
                                </form>
                            @enddroit
                        @endif
                    </div>
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-secondary/30 px-4 py-8 text-center text-sm text-primary/50">Aucune unité : ajoutez-en une ci-dessous.</li>
        @endforelse
    </ul>

    @droit('economat.units.creer')
        <form method="POST" action="{{ route('economat.units.store') }}" class="mt-6 rounded-xl border border-secondary/20 p-4">
            @csrf
            <h3 class="text-sm font-semibold text-primary">Ajouter une unité</h3>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs text-primary/60">Nom</span>
                    <input type="text" name="name" required maxlength="20" placeholder="Ex. bidon" value="{{ old('_method') ? '' : old('name') }}"
                           class="mt-1 w-48 rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">Ajouter</button>
            </div>
        </form>
    @enddroit
</div>

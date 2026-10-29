{{--
    Onglet Économat : les unités de mesure & de stockage des articles.
    Chaque unité possède un nom long, un nom court / symbole, et une équivalence
    (facteur et unité de référence : ex. 1 kg = 1 000 g, 1 pièce = 1 pcs).
--}}
@php
    $unites = \App\Models\StockUnit::query()->with('referenceUnit')->dansLOrdre()->get();
    $emplois = \App\Models\StockItem::query()
        ->selectRaw('unit, COUNT(*) as total')
        ->groupBy('unit')
        ->pluck('total', 'unit');
@endphp

<div class="max-w-4xl" x-data="{
    nouveauNom: '',
    nouveauCourt: '',
    nouveauFacteur: 1,
    nouveauRefId: '',
    get libelleApercu() {
        if (!this.nouveauRefId) {
            return (this.nouveauCourt || this.nouveauNom || 'Cette unité') + ' est une unité de base (valeur = 1)';
        }
        let refEl = document.getElementById('ajout-unite-ref');
        let refNom = refEl ? refEl.options[refEl.selectedIndex]?.text : 'unité';
        return '1 ' + (this.nouveauCourt || this.nouveauNom || 'unité') + ' = ' + this.nouveauFacteur + ' ' + refNom;
    }
}">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-primary">Unités de mesure & de stockage</h2>
            <p class="mt-1 text-sm text-primary/60">
                Définissez les unités employées dans tout l'établissement (nom long, symbole court et valeur d'équivalence).
                Tous les formulaires sélectionnent ces unités, et les listes ainsi que les imprimables affichent les quantités et unités dans des colonnes séparées.
            </p>
        </div>
    </div>

    @if($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Liste des unités existantes --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-secondary/20 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/15 text-xs">
                <thead class="bg-gray-50/80 text-[11px] font-semibold uppercase tracking-wider text-primary/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Nom long</th>
                        <th class="px-3 py-3 text-center">Symbole</th>
                        <th class="px-4 py-3 text-left">Valeur & Équivalence</th>
                        <th class="px-3 py-3 text-center">Articles</th>
                        <th class="px-3 py-3 text-center">Statut</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @forelse($unites as $unite)
                        @php
                            $articles = (int) (($emplois[$unite->name] ?? 0) + ($emplois[$unite->short_name] ?? 0));
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition-colors {{ $unite->is_active ? '' : 'opacity-65 bg-gray-50/30' }}"
                            x-data="{ modeEdition: false }">
                            <td class="px-4 py-3">
                                <template x-if="!modeEdition">
                                    <div class="font-semibold text-primary text-sm">{{ $unite->name }}</div>
                                </template>
                                <template x-if="modeEdition">
                                    <input type="text" form="form-unite-{{ $unite->id }}" name="name" value="{{ $unite->name }}" required maxlength="50"
                                           class="w-36 rounded-md border border-secondary/30 px-2 py-1 text-xs text-primary outline-none focus:border-primary">
                                </template>
                            </td>

                            <td class="px-3 py-3 text-center font-mono">
                                <template x-if="!modeEdition">
                                    <span class="inline-block rounded bg-secondary/15 px-2 py-0.5 font-bold text-primary">{{ $unite->short_name ?: $unite->name }}</span>
                                </template>
                                <template x-if="modeEdition">
                                    <input type="text" form="form-unite-{{ $unite->id }}" name="short_name" value="{{ $unite->short_name }}" required maxlength="20"
                                           class="w-20 rounded-md border border-secondary/30 px-2 py-1 text-xs text-center font-mono font-bold text-primary outline-none focus:border-primary">
                                </template>
                            </td>

                            <td class="px-4 py-3">
                                <template x-if="!modeEdition">
                                    <span class="text-primary/75 font-medium">{{ $unite->equivalenceLabel() }}</span>
                                </template>
                                <template x-if="modeEdition">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span>=</span>
                                        <input type="number" step="any" min="0.0001" form="form-unite-{{ $unite->id }}" name="factor" value="{{ (float) $unite->factor }}"
                                               class="w-20 rounded-md border border-secondary/30 px-2 py-1 text-xs font-mono text-right text-primary outline-none focus:border-primary">
                                        <select form="form-unite-{{ $unite->id }}" name="reference_unit_id"
                                                class="rounded-md border border-secondary/30 px-2 py-1 text-xs text-primary outline-none focus:border-primary">
                                            <option value="">— Unité de base (1) —</option>
                                            @foreach($unites as $uRef)
                                                @if($uRef->id !== $unite->id)
                                                    <option value="{{ $uRef->id }}" @selected($unite->reference_unit_id === $uRef->id)>{{ $uRef->name }} ({{ $uRef->short_name }})</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </template>
                            </td>

                            <td class="px-3 py-3 text-center text-primary/60 font-mono">
                                {{ $articles }}
                            </td>

                            <td class="px-3 py-3 text-center">
                                <template x-if="!modeEdition">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold {{ $unite->is_active ? 'text-green-700' : 'text-gray-500' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $unite->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ $unite->is_active ? 'En service' : 'Hors service' }}
                                    </span>
                                </template>
                                <template x-if="modeEdition">
                                    <label class="inline-flex items-center gap-1.5 text-xs text-primary cursor-pointer">
                                        <input type="hidden" form="form-unite-{{ $unite->id }}" name="is_active" value="0">
                                        <input type="checkbox" form="form-unite-{{ $unite->id }}" name="is_active" value="1" @checked($unite->is_active) class="rounded border-secondary/30">
                                        <span>Actif</span>
                                    </label>
                                </template>
                            </td>

                            <td class="px-4 py-3 text-right">
                                @droit('economat.units.modifier')
                                    <form id="form-unite-{{ $unite->id }}" method="POST" action="{{ route('economat.units.update', $unite) }}" class="hidden">
                                        @csrf @method('PUT')
                                    </form>
                                @enddroit

                                <div class="flex items-center justify-end gap-2">
                                    @droit('economat.units.modifier')
                                        <template x-if="!modeEdition">
                                            <button type="button" @click="modeEdition = true" class="rounded px-2 py-1 text-xs font-semibold text-primary hover:bg-secondary/15">
                                                Modifier
                                            </button>
                                        </template>
                                        <template x-if="modeEdition">
                                            <div class="flex items-center gap-1.5">
                                                <button type="submit" form="form-unite-{{ $unite->id }}" class="rounded bg-primary px-2.5 py-1 text-xs font-semibold text-white hover:bg-surface-dark">
                                                    Enregistrer
                                                </button>
                                                <button type="button" @click="modeEdition = false" class="rounded border border-secondary/30 px-2 py-1 text-xs text-primary/70 hover:bg-gray-100">
                                                    Annuler
                                                </button>
                                            </div>
                                        </template>
                                    @enddroit

                                    @if($articles === 0 && $unite->subUnits->isEmpty())
                                        @droit('economat.units.supprimer')
                                            <template x-if="!modeEdition">
                                                <form method="POST" action="{{ route('economat.units.destroy', $unite) }}" onsubmit="return confirm(@js('Supprimer l\'unité « '.$unite->name.' » ?'))">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="rounded px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">
                                                        Supprimer
                                                    </button>
                                                </form>
                                            </template>
                                        @enddroit
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-primary/50">Aucune unité configurée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Formulaire d'ajout d'une unité --}}
    @droit('economat.units.creer')
        <form data-reglages method="POST" action="{{ route('economat.units.store') }}" class="mt-6 rounded-xl border border-secondary/20 bg-white p-5 shadow-xs">
            @csrf
            <h3 class="text-sm font-semibold text-primary flex items-center gap-2">
                <i data-lucide="scale" class="w-4 h-4 text-primary"></i> Ajouter une unité de mesure
            </h3>
            <p class="text-xs text-primary/60 mt-0.5">Renseignez le nom complet, le symbole court ainsi que son équivalence.</p>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1" for="ajout-unite-nom">Nom complet <span class="text-red-500">*</span></label>
                    <input id="ajout-unite-nom" type="text" name="name" x-model="nouveauNom" required maxlength="50" placeholder="Ex: Kilogramme"
                           class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-xs text-primary outline-none focus:border-primary">
                </div>

                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1" for="ajout-unite-court">Nom court / Symbole <span class="text-red-500">*</span></label>
                    <input id="ajout-unite-court" type="text" name="short_name" x-model="nouveauCourt" required maxlength="20" placeholder="Ex: kg, pcs, l"
                           class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-xs font-mono font-bold text-primary outline-none focus:border-primary">
                </div>

                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1" for="ajout-unite-ref">Unité de référence</label>
                    <select id="ajout-unite-ref" name="reference_unit_id" x-model="nouveauRefId"
                            class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-xs text-primary outline-none focus:border-primary">
                        <option value="">— Unité de base (valeur = 1) —</option>
                        @foreach($unites as $uRef)
                            <option value="{{ $uRef->id }}">{{ $uRef->name }} ({{ $uRef->short_name }})</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="nouveauRefId" x-cloak>
                    <label class="block text-xs font-medium text-primary/70 mb-1" for="ajout-unite-facteur">Facteur d'équivalence</label>
                    <input id="ajout-unite-facteur" type="number" step="any" min="0.0001" name="factor" x-model="nouveauFacteur" placeholder="Ex: 1000"
                           class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-xs font-mono text-primary outline-none focus:border-primary">
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-secondary/15">
                <div class="text-xs text-primary/65 font-medium flex items-center gap-1.5">
                    <span class="text-primary/45">Aperçu équivalence :</span>
                    <strong class="font-mono text-primary" x-text="libelleApercu"></strong>
                </div>
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-surface-dark shadow-sm">
                    Ajouter l'unité
                </button>
            </div>
        </form>
    @enddroit
</div>

@extends('layouts.hotel')

@section('title', 'Catégories — Économat')

@section('content')
<div class="max-w-5xl mx-auto" x-data="stockCategories()">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Catégories d'articles</h1>
            <p class="text-sm text-primary/60 mt-0.5">Chaque catégorie valorise ses articles sur un compte de stock du grand livre.</p>
        </div>
        @if($canManage)
            <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle catégorie
            </button>
        @endif
    </div>

    @include('economat.partials.flash')

    <div class="mb-4 px-4 py-3 bg-accent/10 border border-secondary/20 text-primary/70 text-xs rounded-lg flex gap-2">
        <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
        <p>
            Sans compte choisi, une catégorie est valorisée en <strong>332000 — Fournitures d'économat</strong>.
            Changer le compte d'une catégorie qui a du stock passe aussitôt une écriture de reclassement
            de sa valeur vers le nouveau compte, sans effet sur les charges.
        </p>
    </div>

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        @if($categories->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-primary/40">Aucune catégorie. Créez-en une pour classer les articles.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Catégorie</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Compte de stock</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Valeur en stock</th>
                            @if($canManage)<th class="px-5 py-3"><span class="sr-only">Actions</span></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($categories as $category)
                            @php
                                $valeur = (int) ($valeurs[$category->id] ?? 0);
                                $editPayload = [
                                    'id' => $category->id, 'name' => $category->name,
                                    'stock_account' => $category->stock_account ?? '',
                                    'sort_order' => $category->sort_order, 'value' => $valeur,
                                ];
                            @endphp
                            <tr>
                                <td class="px-5 py-3 font-medium text-primary">{{ $category->name }}</td>
                                <td class="px-5 py-3 text-primary/70 text-xs">
                                    <span class="font-mono">{{ $category->effectiveStockAccount() }}</span>
                                    — {{ $comptes[$category->effectiveStockAccount()] ?? '' }}
                                    @unless($category->stock_account)<span class="text-primary/40">(par défaut)</span>@endunless
                                </td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ $category->items_count }}</td>
                                <td class="px-5 py-3 text-right font-medium text-primary">{{ number_format($valeur / 100, 0, ',', ' ') }}</td>
                                @if($canManage)
                                    <td class="px-5 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:bg-accent/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary"
                                                aria-label="Modifier {{ $category->name }}">
                                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                            </button>
                                            @if($category->items_count === 0)
                                                <form method="POST" action="{{ route('economat.categories.destroy', $category) }}"
                                                      onsubmit="return confirm('Supprimer la catégorie « {{ addslashes($category->name) }} » ?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600/70 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary"
                                                        aria-label="Supprimer {{ $category->name }}">
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($canManage)
    {{-- Modal création / édition --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,2,1,0.5); backdrop-filter:blur(4px);"
         @keydown.escape.window="open = false">
        <div class="absolute inset-0" @click="open = false"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 flex flex-col max-h-[90vh]" role="dialog" aria-modal="true" aria-labelledby="titre-categorie">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                <h3 id="titre-categorie" class="font-heading font-semibold text-primary" x-text="editing ? 'Modifier la catégorie' : 'Nouvelle catégorie'"></h3>
                <button type="button" @click="open = false" class="text-primary/30 hover:text-primary" aria-label="Fermer"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                <div class="px-6 py-5 space-y-4 overflow-y-auto">
                    <div>
                        <label for="categorie-nom" class="block text-xs font-medium text-primary/70 mb-1.5">Nom <span class="text-red-500">*</span></label>
                        <input id="categorie-nom" type="text" name="name" x-model="form.name" required maxlength="120" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="categorie-compte" class="block text-xs font-medium text-primary/70 mb-1.5">Compte de stock</label>
                            <select id="categorie-compte" name="stock_account" x-model="form.stock_account" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <option value="">Par défaut — 332000 Fournitures d'économat</option>
                                @foreach($comptes as $code => $libelle)
                                    <option value="{{ $code }}">{{ $code }} — {{ $libelle }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="categorie-ordre" class="block text-xs font-medium text-primary/70 mb-1.5">Ordre</label>
                            <input id="categorie-ordre" type="number" min="0" max="9999" name="sort_order" x-model="form.sort_order" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                    </div>
                    <p x-show="accountChanges()" x-cloak class="px-3 py-2 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg">
                        Le stock de cette catégorie (<span x-text="formatFcfa(form.value)"></span> FCFA) sera reclassé vers le nouveau compte par une écriture datée d'aujourd'hui.
                    </p>
                </div>
                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark"><span x-text="editing ? 'Enregistrer' : 'Créer'"></span></button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function stockCategories() {
        const storeUrl = @js(route('economat.categories.store'));
        const updateUrl = @js(route('economat.categories.update', ['category' => '__ID__']));
        const vide = { id: null, name: '', stock_account: '', sort_order: 0, value: 0, original_account: '' };

        return {
            open: false,
            editing: false,
            form: { ...vide },
            get formAction() {
                return this.editing ? updateUrl.replace('__ID__', this.form.id) : storeUrl;
            },
            openCreate() {
                this.editing = false;
                this.form = { ...vide };
                this.open = true;
            },
            openEdit(categorie) {
                this.editing = true;
                this.form = { ...vide, ...categorie, original_account: categorie.stock_account };
                this.open = true;
            },
            // Le compte vide vaut 332000 : passer de « par défaut » à 332000 ne reclasse rien.
            accountChanges() {
                const effectif = (compte) => compte || '332000';
                return this.editing && this.form.value > 0
                    && effectif(this.form.stock_account) !== effectif(this.form.original_account);
            },
            formatFcfa(centimes) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(centimes / 100));
            },
        };
    }
</script>
@endpush

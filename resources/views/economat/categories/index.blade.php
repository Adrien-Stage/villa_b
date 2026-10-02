@extends('layouts.hotel')

@section('title', 'Catégories — Économat')

@section('content')
<div class="max-w-5xl mx-auto" x-data="stockCategories({{ (int) $nextSortOrder }}, {{ Js::from($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'sort_order' => (int) $c->sort_order])->values()) }})">
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
                            <th class="px-5 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-primary/50 w-20">Ordre</th>
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
                                <td class="px-5 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-gray-100 text-primary/70 border border-secondary/20">
                                        {{ $category->sort_order }}
                                    </span>
                                </td>
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
         @keydown.escape.window="closeModal()">
        <div class="absolute inset-0" @click="closeModal()"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 flex flex-col max-h-[90vh]" role="dialog" aria-modal="true" aria-labelledby="titre-categorie">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                <h3 id="titre-categorie" class="font-heading font-semibold text-primary" x-text="editing ? 'Modifier la catégorie' : 'Nouvelle catégorie'"></h3>
                <button type="button" @click="closeModal()" class="text-primary/30 hover:text-primary" aria-label="Fermer"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                <div class="px-6 py-5 space-y-4 overflow-y-auto">
                    {{-- Alertes de notification en direct --}}
                    <template x-if="successMessage">
                        <div class="px-3.5 py-2.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center justify-between gap-2 shadow-xs">
                            <div class="flex items-center gap-2">
                                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                <span x-text="successMessage" class="font-medium"></span>
                            </div>
                            <button type="button" @click="successMessage = ''" class="text-emerald-700/60 hover:text-emerald-900"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </template>
                    <template x-if="errorMessage">
                        <div class="px-3.5 py-2.5 bg-red-50 border border-red-200 text-red-800 text-xs rounded-xl flex items-center justify-between gap-2 shadow-xs">
                            <div class="flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="w-4 h-4 text-red-600 shrink-0"></i>
                                <span x-text="errorMessage" class="font-medium"></span>
                            </div>
                            <button type="button" @click="errorMessage = ''" class="text-red-700/60 hover:text-red-900"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </template>

                    <div>
                        <label for="categorie-nom" class="block text-xs font-medium text-primary/70 mb-1.5">Nom <span class="text-red-500">*</span></label>
                        <input id="categorie-nom" x-ref="nameInput" type="text" name="name" x-model="form.name" required maxlength="120" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
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
                            <label for="categorie-ordre" class="block text-xs font-medium text-primary/70 mb-1.5 flex items-center justify-between">
                                <span>Ordre <span class="text-red-500">*</span></span>
                                <span class="text-[10px] text-primary/40 font-normal">Auto</span>
                            </label>
                            <input id="categorie-ordre" type="number" min="0" max="9999" required name="sort_order"
                                   x-model.number="form.sort_order"
                                   :class="isOrderTaken() ? 'border-red-500 text-red-900 focus:border-red-500 bg-red-50/40 ring-1 ring-red-500' : 'border-secondary/30 text-primary focus:border-secondary bg-white'"
                                   class="w-full px-3 py-2.5 text-sm border rounded-lg outline-none font-mono font-semibold transition-colors">
                            <template x-if="isOrderTaken()">
                                <p class="text-[11px] text-red-600 font-medium mt-1.5 flex items-start gap-1">
                                    <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
                                    <span>Ordre déjà attribué à « <strong x-text="takenCategoryName()"></strong> ».</span>
                                </p>
                            </template>
                        </div>
                    </div>
                    <p x-show="accountChanges()" x-cloak class="px-3 py-2 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg">
                        Le stock de cette catégorie (<span x-text="formatFcfa(form.value)"></span> FCFA) sera reclassé vers le nouveau compte par une écriture datée d'aujourd'hui.
                    </p>
                </div>
                {{-- Pied de formulaire avec les 3 boutons : Annuler, Enregistrer et Créer, Enregistrer --}}
                <div class="px-6 py-4 border-t border-secondary/20 flex flex-wrap items-center justify-between gap-3 bg-gray-50 rounded-b-2xl">
                    <div>
                        <button type="button" @click="closeModal()" class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                            Annuler
                        </button>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <template x-if="!editing">
                            <button type="button"
                                    @click="saveAndCreate()"
                                    :disabled="isOrderTaken() || saving"
                                    :class="(isOrderTaken() || saving) ? 'opacity-50 cursor-not-allowed bg-secondary/60 text-primary/60' : 'bg-secondary text-primary hover:bg-secondary/80'"
                                    class="px-4 py-2 text-sm font-semibold rounded-lg transition-all inline-flex items-center gap-1.5 shadow-xs border border-secondary/30">
                                <i data-lucide="plus-circle" class="w-4 h-4" x-show="!saving"></i>
                                <svg x-show="saving" class="animate-spin h-4 w-4 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Enregistrer et créer</span>
                            </button>
                        </template>
                        <button type="submit"
                                :disabled="isOrderTaken() || saving"
                                :class="(isOrderTaken() || saving) ? 'opacity-50 cursor-not-allowed bg-primary/60' : 'bg-primary hover:bg-surface-dark'"
                                class="px-4 py-2 text-white text-sm font-medium rounded-lg transition-colors inline-flex items-center gap-1.5 shadow-xs">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            <span x-text="editing ? 'Enregistrer' : 'Enregistrer'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function stockCategories(nextOrder, existingCategories) {
        const storeUrl = @js(route('economat.categories.store'));
        const updateUrl = @js(route('economat.categories.update', ['category' => '__ID__']));
        const defaultNextOrder = nextOrder;
        const vide = { id: null, name: '', stock_account: '', sort_order: defaultNextOrder, value: 0, original_account: '' };
        const csrfToken = @js(csrf_token());

        return {
            open: false,
            editing: false,
            saving: false,
            hasCreatedAny: false,
            successMessage: '',
            errorMessage: '',
            nextSortOrder: defaultNextOrder,
            categoriesList: existingCategories || [],
            form: { ...vide },
            get formAction() {
                return this.editing ? updateUrl.replace('__ID__', this.form.id) : storeUrl;
            },
            openCreate() {
                this.editing = false;
                this.saving = false;
                this.successMessage = '';
                this.errorMessage = '';
                this.form = { ...vide, sort_order: this.nextSortOrder };
                this.open = true;
                this.$nextTick(() => {
                    if (this.$refs.nameInput) this.$refs.nameInput.focus();
                    if (window.lucide) window.lucide.createIcons();
                });
            },
            openEdit(categorie) {
                this.editing = true;
                this.saving = false;
                this.successMessage = '';
                this.errorMessage = '';
                this.form = { ...vide, ...categorie, original_account: categorie.stock_account };
                this.open = true;
                this.$nextTick(() => {
                    if (this.$refs.nameInput) this.$refs.nameInput.focus();
                    if (window.lucide) window.lucide.createIcons();
                });
            },
            closeModal() {
                this.open = false;
                if (this.hasCreatedAny) {
                    window.location.reload();
                }
            },
            async saveAndCreate() {
                if (!this.form.name || !this.form.name.trim()) {
                    if (this.$refs.nameInput) {
                        this.$refs.nameInput.focus();
                        this.$refs.nameInput.reportValidity();
                    }
                    return;
                }
                if (this.isOrderTaken()) return;

                this.saving = true;
                this.errorMessage = '';
                this.successMessage = '';

                try {
                    const response = await fetch(storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            name: this.form.name.trim(),
                            stock_account: this.form.stock_account || null,
                            sort_order: parseInt(this.form.sort_order, 10),
                        }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        if (data.errors) {
                            const firstError = Object.values(data.errors)[0][0];
                            this.errorMessage = firstError;
                        } else {
                            this.errorMessage = data.message || 'Une erreur est survenue lors de l\'enregistrement.';
                        }
                        return;
                    }

                    // Succès : marquer qu'au moins une catégorie a été créée
                    this.hasCreatedAny = true;
                    const createdCat = data.category;
                    this.categoriesList.push(createdCat);

                    this.successMessage = `Catégorie « ${createdCat.name} » enregistrée (Ordre ${createdCat.sort_order}). Prêt pour la suivante.`;

                    // Mettre à jour l'ordre pour la prochaine catégorie
                    this.nextSortOrder = data.nextSortOrder;
                    this.form.name = '';
                    this.form.stock_account = '';
                    this.form.sort_order = this.nextSortOrder;

                    this.$nextTick(() => {
                        if (this.$refs.nameInput) this.$refs.nameInput.focus();
                        if (window.lucide) window.lucide.createIcons();
                    });
                } catch (err) {
                    this.errorMessage = 'Erreur réseau lors de l\'enregistrement.';
                } finally {
                    this.saving = false;
                }
            },
            isOrderTaken() {
                if (this.form.sort_order === null || this.form.sort_order === '' || isNaN(this.form.sort_order)) {
                    return false;
                }
                const target = parseInt(this.form.sort_order, 10);
                return this.categoriesList.some(c => c.sort_order === target && c.id !== this.form.id);
            },
            takenCategoryName() {
                const target = parseInt(this.form.sort_order, 10);
                const found = this.categoriesList.find(c => c.sort_order === target && c.id !== this.form.id);
                return found ? found.name : '';
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

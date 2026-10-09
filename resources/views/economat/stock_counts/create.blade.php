@extends('layouts.hotel')

@section('title', 'Nouvel inventaire — Économat')

@section('content')
<div class="max-w-xl mx-auto">
    <a href="{{ route('economat.stock_counts.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary mb-4 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux inventaires
    </a>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm">
        <div class="flex items-center gap-3 mb-5 pb-4 border-b border-secondary/15">
            <div class="p-2.5 rounded-lg bg-primary/5 text-primary">
                <i data-lucide="clipboard-list" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-lg font-heading font-semibold text-primary">Ouvrir un inventaire physique</h1>
                <p class="text-xs text-primary/60 mt-0.5">Fige le stock théorique et prépare la feuille de comptage contradictoire.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('economat.stock_counts.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-primary/70 uppercase tracking-wider mb-1.5">Date du comptage <span class="text-red-500">*</span></label>
                    <input type="date" name="count_date" value="{{ old('count_date', now()->toDateString()) }}" required
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    @error('count_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-primary/70 uppercase tracking-wider mb-1.5">Périmètre / Catégorie</label>
                    <select name="stock_category_id" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                        <option value="">Tous les articles de l'économat (Inventaire général)</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('stock_category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-primary/45 mt-1">Vous pouvez isoler une seule famille d'articles (ex: Boissons, Épicerie) pour un comptage tournant.</p>
                    @error('stock_category_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-primary/70 uppercase tracking-wider mb-1.5">Notes & Contexte (optionnel)</label>
                    <textarea name="notes" rows="3" placeholder="Ex: Inventaire mensuel de clôture, contrôle inopiné suite à rotation d'équipe..."
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Le comptage déjà fait sur papier s'importe dès l'ouverture. --}}
                @droit('economat.stock_counts.import')
                    <div class="rounded-lg border border-emerald-200 p-3 space-y-2" x-data>
                        <p class="text-xs font-semibold text-primary/70 uppercase tracking-wider">Fichier de comptage (facultatif)</p>
                        <p class="text-[11px] text-primary/55">
                            Le comptage est déjà fait ? Importez le fichier rempli : les quantités comptées seront saisies à l'ouverture.
                            @droit('economat.stock_counts.export')
                                <a href="{{ route('economat.stock_counts.export') }}" class="font-semibold text-emerald-800 underline"
                                   {{-- Le fichier suit le périmètre choisi plus haut. --}}
                                   x-on:click="const c = document.querySelector('[name=stock_category_id]').value; $el.href = @js(route('economat.stock_counts.export')) + (c ? '?categorie=' + c : '')">Télécharger le fichier de comptage</a>
                            @enddroit
                        </p>
                        <label for="fichier-ouverture" class="sr-only">Fichier de comptage</label>
                        <input id="fichier-ouverture" type="file" name="fichier" accept=".xlsx,.xls,.csv"
                               class="block w-full text-xs text-primary file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-emerald-800">
                        @error('fichier')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                        @droit('economat.stock_counts.close')
                            <label class="inline-flex items-center gap-2 text-xs text-primary">
                                <input type="checkbox" name="cloturer" value="1" class="rounded border-secondary/40 text-primary">
                                Clôturer aussitôt : ajuster le stock sur les quantités comptées
                            </label>
                        @enddroit
                    </div>
                @enddroit

                <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-xs text-blue-800">
                    <div class="flex items-start gap-2">
                        <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5 text-blue-600"></i>
                        <p>
                            À la création de la feuille, le système fige instantanément le <strong>stock théorique</strong> et le <strong>CUMP</strong> de chaque article sélectionné. Vous pourrez ensuite saisir à votre rythme les quantités réelles comptées avant de valider la régularisation.
                        </p>
                    </div>
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-secondary/15">
                    <a href="{{ route('economat.stock_counts.index') }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                        Annuler
                    </a>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Ouvrir la feuille d'inventaire
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

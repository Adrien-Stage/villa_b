@extends('layouts.hotel')

@section('title', 'Fournisseurs — Économat')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="suppliersManagement({{ Js::from($availableItems->map(fn($i) => [
    'id' => $i->id,
    'name' => $i->name,
    'reference' => $i->reference,
    'unit' => $i->unit,
    'price' => $i->last_purchase_price ? ($i->last_purchase_price / 100) : ($i->average_cost / 100),
    'category_name' => $i->category?->name ?? 'Général',
    'supplier_id' => $i->supplier_id
])->values()) }})">

    {{-- En-tête de page --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary/10 text-primary">
                    <i data-lucide="truck" class="w-5 h-5"></i>
                </span>
                <h1 class="text-2xl font-heading font-semibold text-primary">Fournisseurs de l'Économat</h1>
            </div>
            <p class="text-sm text-primary/60 mt-1">
                Répertoire des partenaires d'approvisionnement, conditions commerciales, délais de livraison et catalogues d'articles.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" @click="openCreate()"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-surface-dark transition-all shadow-sm">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouveau fournisseur
            </button>
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- 4 Cartes d'indicateurs KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-primary/50 text-xs font-medium uppercase tracking-wider mb-1">
                <span>Total Partenaires</span>
                <i data-lucide="building-2" class="w-4 h-4 text-primary/40"></i>
            </div>
            <div class="text-2xl font-heading font-bold text-primary">{{ $kpis['total_suppliers'] }}</div>
            <p class="text-xs text-primary/60 mt-1">{{ $kpis['active_suppliers'] }} actifs</p>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-primary/50 text-xs font-medium uppercase tracking-wider mb-1">
                <span>Actifs & Opérationnels</span>
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
            </div>
            <div class="text-2xl font-heading font-bold text-emerald-600">{{ $kpis['active_suppliers'] }}</div>
            <p class="text-xs text-primary/60 mt-1">Fournisseurs qualifiés</p>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-primary/50 text-xs font-medium uppercase tracking-wider mb-1">
                <span>Avec Email Commande</span>
                <i data-lucide="mail-check" class="w-4 h-4 text-sky-600"></i>
            </div>
            <div class="text-2xl font-heading font-bold text-sky-600">{{ $kpis['with_email'] }}</div>
            <p class="text-xs text-primary/60 mt-1">Prêts pour envoi direct PO</p>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-primary/50 text-xs font-medium uppercase tracking-wider mb-1">
                <span>Articles Référencés</span>
                <i data-lucide="boxes" class="w-4 h-4 text-purple-600"></i>
            </div>
            <div class="text-2xl font-heading font-bold text-purple-600">{{ $kpis['total_items_linked'] }}</div>
            <p class="text-xs text-primary/60 mt-1">Produits avec fournisseur attitré</p>
        </div>
    </div>

    {{-- Formulaire de Recherche et Filtres multi-critères --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-4 shadow-xs">
        <form method="GET" action="{{ route('economat.suppliers.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            <div class="sm:col-span-5">
                <label class="block text-xs font-medium text-primary/70 mb-1">Recherche textuelle</label>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-3 text-primary/40"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Nom, code, contact, email, NIF, ville..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                </div>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-xs font-medium text-primary/70 mb-1">Secteur / Domaine</label>
                <select name="category" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    <option value="">Tous les domaines</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-primary/70 mb-1">Statut</label>
                <select name="status" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    <option value="">Tous</option>
                    <option value="active" @selected(request('status') === 'active')>Actifs</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactifs</option>
                </select>
            </div>

            <div class="sm:col-span-2 flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors inline-flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i> Filtrer
                </button>
                @if(request()->hasAny(['search', 'category', 'status']))
                    <a href="{{ route('economat.suppliers.index') }}" title="Réinitialiser" class="px-3 py-2 border border-secondary/30 text-primary/60 hover:text-primary rounded-lg flex items-center justify-center">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tableau des Fournisseurs --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-xs">
        @if($suppliers->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-primary/5 text-primary/40 mb-3">
                    <i data-lucide="truck" class="w-7 h-7"></i>
                </div>
                <h3 class="text-base font-semibold text-primary mb-1">Aucun fournisseur trouvé</h3>
                <p class="text-sm text-primary/50 max-w-md mx-auto mb-4">
                    {{ request()->hasAny(['search', 'category', 'status']) ? 'Aucun partenaire ne correspond à vos critères de recherche.' : 'Commencez par ajouter votre premier fournisseur pour pouvoir émettre des bons de commande.' }}
                </p>
                <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark">
                    <i data-lucide="plus" class="w-4 h-4"></i> Ajouter un fournisseur
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/80 border-b border-secondary/20">
                        <tr>
                            <th class="px-5 py-3.5 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/60">Fournisseur & Secteur</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/60">Interlocuteur & Contacts</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/60">Conditions Commerciales</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-semibold uppercase tracking-wider text-primary/60">Articles Liés</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-semibold uppercase tracking-wider text-primary/60">Bons (PO)</th>
                            <th class="px-5 py-3.5 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/60">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($suppliers as $supplier)
                            @php
                                $editPayload = [
                                    'id'                      => $supplier->id,
                                    'name'                    => $supplier->name,
                                    'code'                    => $supplier->code ?? '',
                                    'category'                => $supplier->category ?? '',
                                    'tax_id'                  => $supplier->tax_id ?? '',
                                    'rccm'                    => $supplier->rccm ?? '',
                                    'city'                    => $supplier->city ?? '',
                                    'contact_name'            => $supplier->contact_name ?? '',
                                    'email'                   => $supplier->email ?? '',
                                    'phone'                   => $supplier->phone ?? '',
                                    'address'                 => $supplier->address ?? '',
                                    'payment_terms'           => $supplier->payment_terms ?? '',
                                    'payment_method'          => $supplier->payment_method ?? '',
                                    'delivery_lead_time_days' => $supplier->delivery_lead_time_days ?? '',
                                    'bank_details'            => $supplier->bank_details ?? '',
                                    'notes'                   => $supplier->notes ?? '',
                                    'is_active'               => (bool) $supplier->is_active,
                                    'linked_items'            => $supplier->stockItems->map(fn($item) => [
                                        'id'        => $item->id,
                                        'name'      => $item->name,
                                        'reference' => $item->reference,
                                        'unit'      => $item->unit,
                                        'price'     => $item->last_purchase_price ? ($item->last_purchase_price / 100) : ($item->average_cost / 100),
                                    ])->values()->all(),
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition-colors {{ $supplier->is_active ? '' : 'opacity-60 bg-gray-50/30' }}">
                                {{-- Fournisseur & Secteur --}}
                                <td class="px-5 py-4">
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-secondary/15 flex items-center justify-center text-primary font-bold text-xs flex-shrink-0 mt-0.5">
                                            {{ strtoupper(substr($supplier->name, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-semibold text-primary">{{ $supplier->name }}</span>
                                                @if($supplier->code)
                                                    <span class="px-1.5 py-0.5 text-[10px] font-mono bg-gray-100 text-primary/70 rounded">{{ $supplier->code }}</span>
                                                @endif
                                                @if(!$supplier->is_active)
                                                    <span class="px-1.5 py-0.5 text-[10px] font-medium bg-red-100 text-red-700 rounded">Inactif</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-primary/50 mt-1 flex-wrap">
                                                @if($supplier->category)
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-primary/70">
                                                        <i data-lucide="tag" class="w-3 h-3 text-secondary"></i> {{ $supplier->category_label }}
                                                    </span>
                                                @endif
                                                @if($supplier->city)
                                                    <span class="text-primary/40">•</span>
                                                    <span>{{ $supplier->city }}</span>
                                                @endif
                                                @if($supplier->tax_id)
                                                    <span class="text-primary/40">•</span>
                                                    <span class="font-mono text-[11px]">NIF: {{ $supplier->tax_id }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Interlocuteur & Contacts --}}
                                <td class="px-5 py-4">
                                    <div class="space-y-1 text-xs">
                                        @if($supplier->contact_name)
                                            <div class="font-medium text-primary flex items-center gap-1">
                                                <i data-lucide="user" class="w-3 h-3 text-primary/40"></i>
                                                {{ $supplier->contact_name }}
                                            </div>
                                        @endif
                                        @if($supplier->phone)
                                            <div class="text-primary/60 flex items-center gap-1">
                                                <i data-lucide="phone" class="w-3 h-3 text-primary/40"></i>
                                                {{ $supplier->phone }}
                                            </div>
                                        @endif
                                        @if($supplier->email)
                                            <div class="text-sky-700 font-mono text-[11px] flex items-center gap-1">
                                                <i data-lucide="mail" class="w-3 h-3 text-sky-500"></i>
                                                {{ $supplier->email }}
                                            </div>
                                        @else
                                            <div class="text-amber-600 text-[10px] flex items-center gap-1">
                                                <i data-lucide="mail-x" class="w-3 h-3"></i> Sans email
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                {{-- Conditions Commerciales --}}
                                <td class="px-5 py-4">
                                    <div class="text-xs space-y-1">
                                        <div class="text-primary/70">
                                            <span class="text-primary/40 text-[11px]">Règlement :</span>
                                            <span class="font-medium">{{ $supplier->payment_terms_label }}</span>
                                        </div>
                                        @if($supplier->payment_method)
                                            <div class="text-primary/50 text-[11px]">
                                                {{ $supplier->payment_method_label }}
                                            </div>
                                        @endif
                                        @if($supplier->delivery_lead_time_days)
                                            <div class="text-[11px] text-emerald-700 flex items-center gap-1">
                                                <i data-lucide="clock" class="w-3 h-3"></i>
                                                Livraison ~{{ $supplier->delivery_lead_time_days }} jour(s)
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                {{-- Articles Liés --}}
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold {{ $supplier->stock_items_count > 0 ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-gray-100 text-gray-500' }}">
                                        <i data-lucide="package" class="w-3.5 h-3.5"></i>
                                        {{ $supplier->stock_items_count }} article(s)
                                    </span>
                                </td>

                                {{-- Bons de commande --}}
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold {{ $supplier->purchase_orders_count > 0 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-gray-100 text-gray-500' }}">
                                        <i data-lucide="clipboard-list" class="w-3.5 h-3.5"></i>
                                        {{ $supplier->purchase_orders_count }} bon(s)
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- Créer un bon de commande direct --}}
                                        <a href="{{ route('economat.orders.create', ['fournisseur' => $supplier->id]) }}"
                                            title="Émettre un bon de commande à ce fournisseur"
                                            class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-lg border border-primary/20 text-primary hover:bg-primary hover:text-white transition-colors text-xs font-medium">
                                            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Commander
                                        </a>

                                        {{-- Éditer le fournisseur --}}
                                        <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                            title="Modifier la fiche et les articles associés"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/25 text-primary/70 hover:bg-accent/20 transition-colors">
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </button>

                                        {{-- Supprimer --}}
                                        <form method="POST" action="{{ route('economat.suppliers.destroy', $supplier) }}"
                                            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement le fournisseur « {{ $supplier->name }} » ?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Supprimer" class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-secondary/15">{{ $suppliers->links() }}</div>
        @endif
    </div>

    {{-- MODALE ENRICHIE : NOUVEAU / MODIFIER FOURNISSEUR --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6" style="background:rgba(15,23,42,0.6); backdrop-filter:blur(4px);">
        <div class="absolute inset-0" @click="open = false"></div>

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl relative z-10 flex flex-col max-h-[92vh] overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {{-- Entête de la Modale --}}
            <div class="px-6 py-4 border-b border-secondary/20 flex items-center justify-between bg-gray-50/60">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-primary text-white">
                        <i data-lucide="truck" class="w-5 h-5"></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-heading font-semibold text-primary" x-text="editing ? 'Fiche Fournisseur : ' + form.name : 'Créer un Nouveau Fournisseur'"></h3>
                        <p class="text-xs text-primary/60">Renseignez l'identité, les coordonnées, les conditions commerciales et les articles livrés.</p>
                    </div>
                </div>
                <button type="button" @click="open = false" class="text-primary/40 hover:text-primary p-1.5 rounded-lg hover:bg-gray-200/50">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Barre d'onglets de navigation interne --}}
            <div class="flex border-b border-secondary/20 bg-gray-50/30 px-6 overflow-x-auto hide-scrollbar">
                <button type="button" @click="activeTab = 'identity'"
                    class="py-3 px-4 text-xs font-semibold border-b-2 flex items-center gap-2 whitespace-nowrap transition-colors"
                    :class="activeTab === 'identity' ? 'border-primary text-primary' : 'border-transparent text-primary/50 hover:text-primary'">
                    <i data-lucide="building-2" class="w-4 h-4"></i>
                    1. Identité & Fisc
                </button>

                <button type="button" @click="activeTab = 'contact'"
                    class="py-3 px-4 text-xs font-semibold border-b-2 flex items-center gap-2 whitespace-nowrap transition-colors"
                    :class="activeTab === 'contact' ? 'border-primary text-primary' : 'border-transparent text-primary/50 hover:text-primary'">
                    <i data-lucide="phone-call" class="w-4 h-4"></i>
                    2. Contacts & Commande
                </button>

                <button type="button" @click="activeTab = 'commercial'"
                    class="py-3 px-4 text-xs font-semibold border-b-2 flex items-center gap-2 whitespace-nowrap transition-colors"
                    :class="activeTab === 'commercial' ? 'border-primary text-primary' : 'border-transparent text-primary/50 hover:text-primary'">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                    3. Règlements & Délais
                </button>

                <button type="button" @click="activeTab = 'catalog'"
                    class="py-3 px-4 text-xs font-semibold border-b-2 flex items-center gap-2 whitespace-nowrap transition-colors"
                    :class="activeTab === 'catalog' ? 'border-primary text-primary' : 'border-transparent text-primary/50 hover:text-primary'">
                    <i data-lucide="boxes" class="w-4 h-4"></i>
                    4. Articles & Tarifs (<span x-text="form.linked_items.length"></span>)
                </button>

                <button type="button" @click="activeTab = 'notes'"
                    class="py-3 px-4 text-xs font-semibold border-b-2 flex items-center gap-2 whitespace-nowrap transition-colors"
                    :class="activeTab === 'notes' ? 'border-primary text-primary' : 'border-transparent text-primary/50 hover:text-primary'">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    5. Notes & Accords
                </button>
            </div>

            {{-- Formulaire Multi-Onglets --}}
            <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                <div class="px-6 py-5 overflow-y-auto space-y-6 flex-1">
                    
                    {{-- ONGLET 1 : IDENTITÉ & FISC --}}
                    <div x-show="activeTab === 'identity'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-primary mb-1">
                                    Nom / Raison Sociale <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" x-model="form.name" @input="applyAutoCode()" required maxlength="160"
                                    placeholder="Ex : Prodimex Hygiène SARL, Brasseries du Cameroun..."
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">
                                    Code Fournisseur
                                </label>
                                <input type="text" name="code" x-model="form.code" @input="autoCode = false" maxlength="30"
                                    placeholder="Ex : FOU-PRODIMEX"
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary font-mono uppercase">
                                <p class="text-[10px] text-primary/50 mt-1">Identifiant court pour bons et exports.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">Secteur / Domaine</label>
                                <select name="category" x-model="form.category"
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                                    <option value="">Sélectionner un domaine</option>
                                    @foreach($categories as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">NIF / NIU (Fiscal)</label>
                                <input type="text" name="tax_id" x-model="form.tax_id" maxlength="60"
                                    placeholder="Ex : M051200000000X"
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary font-mono">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">RCCM</label>
                                <input type="text" name="rccm" x-model="form.rccm" maxlength="60"
                                    placeholder="Ex : RC/DLA/2020/B/..."
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">Ville</label>
                                <input type="text" name="city" x-model="form.city" maxlength="100"
                                    placeholder="Ex : Douala, Yaoundé, Bafoussam..."
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-primary mb-1">Adresse Géographique</label>
                                <input type="text" name="address" x-model="form.address" maxlength="255"
                                    placeholder="Ex : Akwa, Rue Pau, Face Direction Générale..."
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                            </div>
                        </div>
                    </div>

                    {{-- ONGLET 2 : CONTACT & COMMANDE --}}
                    <div x-show="activeTab === 'contact'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">Interlocuteur / Contact Principal</label>
                                <input type="text" name="contact_name" x-model="form.contact_name" maxlength="120"
                                    placeholder="Ex : M. Essomba — Responsable Grands Comptes"
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">Téléphone Principal / WhatsApp</label>
                                <input type="text" name="phone" x-model="form.phone" maxlength="30"
                                    placeholder="Ex : +237 670 00 00 00"
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-primary mb-1">
                                Email Officiel de Commande <span class="text-sky-600 font-normal">(Essentiel pour l'envoi auto des bons)</span>
                            </label>
                            <div class="relative">
                                <i data-lucide="mail" class="w-4 h-4 absolute left-3.5 top-3.5 text-sky-600"></i>
                                <input type="email" name="email" x-model="form.email" maxlength="150"
                                    placeholder="commandes@fournisseur.com"
                                    class="w-full pl-10 pr-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary font-mono">
                            </div>
                            <p class="text-[11px] text-primary/50 mt-1">
                                Les bons de commande approuvés par l'Économe pourront être expédiés d'un simple clic à cette adresse.
                            </p>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-3 p-3.5 border border-secondary/30 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                                <input type="checkbox" x-model="form.is_active" class="w-4 h-4 rounded text-primary border-secondary/40 focus:ring-primary">
                                <div>
                                    <span class="text-sm font-semibold text-primary block">Fournisseur Actif</span>
                                    <span class="text-xs text-primary/50">Un fournisseur inactif n'apparaît plus dans la sélection lors de la création d'un bon de commande.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- ONGLET 3 : RÈGLEMENTS & DÉLAIS --}}
                    <div x-show="activeTab === 'commercial'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">Délai de Paiement Accordé</label>
                                <select name="payment_terms" x-model="form.payment_terms"
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                                    <option value="">Sélectionner un délai</option>
                                    @foreach($paymentTerms as $k => $lbl)
                                        <option value="{{ $k }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">Mode de Règlement Préféré</label>
                                <select name="payment_method" x-model="form.payment_method"
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                                    <option value="">Sélectionner un mode</option>
                                    @foreach($paymentMethods as $k => $lbl)
                                        <option value="{{ $k }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary mb-1">Délai Moyen de Livraison</label>
                                <div class="relative">
                                    <input type="number" name="delivery_lead_time_days" x-model="form.delivery_lead_time_days" min="0" max="365"
                                        placeholder="Ex : 2"
                                        class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary">
                                    <span class="absolute right-3 top-2.5 text-xs text-primary/40 font-medium">jour(s)</span>
                                </div>
                                <p class="text-[10px] text-primary/50 mt-1">Permet de calculer automatiquement la date d'échéance du bon.</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-primary mb-1">Coordonnées de Règlement (RIB / Mobile Money)</label>
                                <input type="text" name="bank_details" x-model="form.bank_details" maxlength="255"
                                    placeholder="Ex : UBA CM21 10033... ou Compte Marchand Orange Money 699..."
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary font-mono text-xs">
                            </div>
                        </div>
                    </div>

                    {{-- ONGLET 4 : ARTICLES & TARIFS CONVENUS --}}
                    <div x-show="activeTab === 'catalog'" class="space-y-4">
                        <div class="p-4 bg-purple-50/70 border border-purple-200/80 rounded-xl">
                            <div class="flex items-start gap-3">
                                <i data-lucide="sparkles" class="w-5 h-5 text-purple-700 flex-shrink-0 mt-0.5"></i>
                                <div class="text-xs text-purple-900">
                                    <p class="font-semibold text-sm mb-0.5">Catalogue des Articles Fournis</p>
                                    <p class="text-purple-800/80">
                                        Associez les articles que ce fournisseur livre habituellement. Pour chaque article, spécifiez le 
                                        <strong>prix unitaire d'achat convenu (en FCFA)</strong> et l'<strong>unité de mesure contractuelle</strong>.
                                        Lors de la création d'un bon de commande pour ce fournisseur, seuls ses articles seront proposés.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Sélecteur d'article rapide --}}
                        <div class="bg-gray-50 border border-secondary/20 p-3.5 rounded-xl">
                            <label class="block text-xs font-semibold text-primary mb-1.5">Ajouter un article à ce fournisseur</label>
                            <div class="flex gap-2">
                                <select x-model="selectedItemIdToAdd" class="flex-1 px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                    <option value="">-- Choisir un article dans le magasin --</option>
                                    <template x-for="item in availableItemsForSelect" :key="item.id">
                                        <option :value="item.id" x-text="`${item.name} (${item.category_name}) - Réf: ${item.reference || 'N/A'}`"></option>
                                    </template>
                                </select>
                                <button type="button" @click="addItemToSupplier()" :disabled="!selectedItemIdToAdd"
                                    class="px-4 py-2 bg-purple-700 text-white text-sm font-semibold rounded-lg hover:bg-purple-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors inline-flex items-center gap-1.5">
                                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Associer
                                </button>
                            </div>
                        </div>

                        {{-- Liste des articles associés --}}
                        <div class="border border-secondary/20 rounded-xl overflow-hidden">
                            <div class="bg-gray-100/70 px-4 py-2.5 border-b border-secondary/20 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-primary/70">Articles Actuellement Rattachés</span>
                                <span class="text-xs font-semibold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full" x-text="`${form.linked_items.length} article(s)`"></span>
                            </div>

                            <template x-if="form.linked_items.length === 0">
                                <div class="p-8 text-center text-primary/40 text-sm">
                                    <i data-lucide="box" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                    Aucun article rattaché pour l'instant. Utilisez le sélecteur ci-dessus pour associer des produits.
                                </div>
                            </template>

                            <template x-if="form.linked_items.length > 0">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs">
                                        <thead class="bg-gray-50 border-b border-secondary/15 text-primary/60 font-semibold uppercase">
                                            <tr>
                                                <th class="px-4 py-2 text-left">Article</th>
                                                <th class="px-4 py-2 text-left w-36">Unité Convenu</th>
                                                <th class="px-4 py-2 text-right w-44">Prix Unitaire Convenu (FCFA)</th>
                                                <th class="px-3 py-2 w-12 text-center"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-secondary/10">
                                            <template x-for="(line, idx) in form.linked_items" :key="line.id">
                                                <tr class="hover:bg-gray-50/50">
                                                    {{-- Nom & ID --}}
                                                    <td class="px-4 py-2.5">
                                                        <input type="hidden" :name="`linked_items[${idx}][id]`" :value="line.id">
                                                        <div class="font-semibold text-primary text-sm" x-text="line.name"></div>
                                                        <div class="text-[11px] text-primary/40" x-text="line.reference ? 'Réf: ' + line.reference : 'Sans référence'"></div>
                                                    </td>

                                                    {{-- Unité --}}
                                                    <td class="px-4 py-2.5">
                                                        <input type="text" :name="`linked_items[${idx}][unit]`" x-model="line.unit" required
                                                            class="w-full px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                                    </td>

                                                    {{-- Prix Unitaire --}}
                                                    <td class="px-4 py-2.5 text-right">
                                                        <div class="relative">
                                                            <input type="number" :name="`linked_items[${idx}][price]`" x-model="line.price" min="0" step="1" required
                                                                class="w-full px-2.5 py-1.5 text-right text-xs font-mono font-semibold border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                                        </div>
                                                    </td>

                                                    {{-- Supprimer --}}
                                                    <td class="px-3 py-2.5 text-center">
                                                        <button type="button" @click="removeItemFromSupplier(idx)" title="Retirer cet article"
                                                            class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- ONGLET 5 : NOTES & ACCORDS --}}
                    <div x-show="activeTab === 'notes'" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-primary mb-1">Notes Internes & Accords Négociés</label>
                            <textarea name="notes" x-model="form.notes" rows="6" maxlength="1000"
                                placeholder="Conditions particulières, remises sur volume, franco de port, jours fixes de passage du livreur, personnes de secours..."
                                class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary"></textarea>
                            <p class="text-[11px] text-primary/50 mt-1">Ces notes sont strictement confidentielles et internes à l'équipe de l'économat.</p>
                        </div>
                    </div>

                </div>

                {{-- Pied de Modale avec navigation entre onglets et boutons d'action --}}
                <div class="px-6 py-4 border-t border-secondary/20 bg-gray-50 flex items-center justify-between rounded-b-2xl">
                    <div>
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                            Annuler
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-surface-dark transition-all shadow-sm inline-flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            <span x-text="editing ? 'Mettre à jour le fournisseur' : 'Enregistrer le fournisseur'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function suppliersManagement(allAvailableItems) {
        const storeUrl = @js(route('economat.suppliers.store'));
        const baseUrl = @js(url('/economat/fournisseurs'));

        return {
            open: false,
            editing: false,
            activeTab: 'identity',
            formAction: storeUrl,
            autoCode: true,
            selectedItemIdToAdd: '',
            availableItems: allAvailableItems,
            form: {
                id: null,
                name: '',
                code: '',
                category: '',
                tax_id: '',
                rccm: '',
                city: '',
                contact_name: '',
                email: '',
                phone: '',
                address: '',
                payment_terms: '',
                payment_method: '',
                delivery_lead_time_days: '',
                bank_details: '',
                notes: '',
                is_active: true,
                linked_items: [],
            },

            get availableItemsForSelect() {
                const linkedIds = this.form.linked_items.map(li => li.id);
                return this.availableItems.filter(i => !linkedIds.includes(i.id));
            },

            blank() {
                return {
                    id: null,
                    name: '',
                    code: '',
                    category: '',
                    tax_id: '',
                    rccm: '',
                    city: '',
                    contact_name: '',
                    email: '',
                    phone: '',
                    address: '',
                    payment_terms: '',
                    payment_method: '',
                    delivery_lead_time_days: '',
                    bank_details: '',
                    notes: '',
                    is_active: true,
                    linked_items: [],
                };
            },

            applyAutoCode() {
                if (this.autoCode && this.form.name) {
                    const clean = this.form.name
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toUpperCase()
                        .replace(/[^A-Z0-9]/g, '')
                        .substring(0, 10);
                    this.form.code = clean ? 'FOU-' + clean : '';
                }
            },

            openCreate() {
                this.form = this.blank();
                this.autoCode = true;
                this.editing = false;
                this.activeTab = 'identity';
                this.selectedItemIdToAdd = '';
                this.formAction = storeUrl;
                this.open = true;
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
            },

            openEdit(supplierData) {
                this.form = {
                    ...this.blank(),
                    ...supplierData,
                    linked_items: Array.isArray(supplierData.linked_items) ? [...supplierData.linked_items] : [],
                };
                this.autoCode = false;
                this.editing = true;
                this.activeTab = 'identity';
                this.selectedItemIdToAdd = '';
                this.formAction = `${baseUrl}/${supplierData.id}`;
                this.open = true;
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
            },

            addItemToSupplier() {
                if (!this.selectedItemIdToAdd) return;
                const item = this.availableItems.find(i => i.id === parseInt(this.selectedItemIdToAdd));
                if (item) {
                    this.form.linked_items.push({
                        id: item.id,
                        name: item.name,
                        reference: item.reference || '',
                        unit: item.unit || 'Pièce',
                        price: item.price || 0,
                    });
                    this.selectedItemIdToAdd = '';
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                }
            },

            removeItemFromSupplier(index) {
                this.form.linked_items.splice(index, 1);
            }
        };
    }
</script>
@endpush

@extends('layouts.hotel')

@section('title', 'Commandes')

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Commandes restaurant</h1>
        <p class="text-sm text-primary/50 mt-0.5">Commandes du portail client et commandes saisies par le staff</p>
    </div>

    @role('restaurant_chief', 'restaurant_staff')
    <button type="button"
        onclick="openCreateOrderModal()"
        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
        Nouvelle commande
    </button>
    @endrole
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold mb-1">Validation impossible :</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('restaurant.orders.index', request()->except('status', 'page')) }}"
            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ request('status') ? 'bg-white text-primary/60 hover:text-primary border border-secondary/30' : 'bg-primary text-white' }}">
            Toutes
        </a>
        @foreach($statuses as $status)
            <a href="{{ route('restaurant.orders.index', array_merge(request()->except('status', 'page'), ['status' => $status])) }}"
                class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ request('status') === $status ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ strtoupper($status) }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('restaurant.orders.index') }}" class="flex items-center gap-2">
        <input type="hidden" name="status" value="{{ request('status') }}">

        <div class="relative">
            <input type="text"
                id="table-input"
                name="table"
                value="{{ request('table') }}"
                placeholder="Table..."
                autocomplete="off"
                class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-40 transition-all">
            <i data-lucide="hash" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
    @if($orders->isEmpty())
        <div class="py-16 text-center text-primary/35">
            <i data-lucide="receipt" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
            <p class="text-sm font-medium">Aucune commande</p>
            <p class="text-xs mt-1">Les commandes du portail apparaitront ici.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/20">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Commande</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Table</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Source</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Statut</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Total</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($orders as $order)
                        <tr class="hover:bg-accent/10">
                            <td class="px-4 py-3">
                                <a href="{{ route('restaurant.orders.show', $order) }}" class="text-sm font-semibold text-primary hover:underline">
                                    #{{ $order->id }}
                                </a>
                                <p class="text-xs text-primary/45 mt-0.5">
                                    {{ $order->items_count }} item{{ $order->items_count > 1 ? 's' : '' }} · {{ $order->placed_at?->format('d/m H:i') }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-sm text-primary/70">
                                {{ $order->table_number ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-accent/30 text-primary border border-secondary/15">
                                    {{ strtoupper($order->source ?? 'portal') }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white text-primary border border-secondary/25">
                                    {{ strtoupper($order->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-primary">
                                {{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('restaurant.orders.show', $order) }}"
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-4 border-t border-secondary/15">
            {{ $orders->links() }}
        </div>
    @endif
</div>

@role('restaurant_chief', 'restaurant_staff')
{{-- Create order modal --}}
<x-modal id="create-order-modal" title="Nouvelle commande" subtitle="Saisie manuelle (client sans QR)" max-width="max-w-4xl" closeAction="closeCreateOrderModal()">

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] h-full">
        <div class="p-5 border-b lg:border-b-0 lg:border-r border-secondary/15 flex flex-col h-[50vh] lg:h-full">
            <div class="flex items-center justify-between gap-3 mb-4 shrink-0">
                <div class="relative flex-1">
                    <input id="menu-search" type="text" placeholder="Rechercher un article..."
                        class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-secondary/25 bg-white text-sm outline-none focus:border-secondary">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
                </div>
                <select id="cat-filter" class="px-3 py-2.5 text-sm border border-secondary/25 rounded-xl bg-white text-primary outline-none focus:border-secondary">
                    <option value="all">Toutes</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                    <option value="none">Autres</option>
                </select>
            </div>

            <div id="menu-grid" class="grid grid-cols-1 md:grid-cols-2 gap-3 flex-1 overflow-y-auto pr-1">
                @foreach($menuItems as $item)
                    <button type="button"
                        data-item
                        data-id="{{ $item->id }}"
                        data-name="{{ $item->name }}"
                        data-price="{{ $item->price }}"
                        data-category="{{ $item->restaurant_menu_category_id ?? 'none' }}"
                        onclick="addToCart({{ $item->id }})"
                        class="text-left rounded-2xl border border-secondary/15 bg-white p-4 hover:bg-accent/10 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-heading text-sm font-semibold text-primary truncate">{{ $item->name }}</p>
                                @if($item->description)
                                    <p class="text-xs text-primary/45 mt-1 truncate">{{ $item->description }}</p>
                                @endif
                                <div class="mt-2 text-xs text-primary/45">
                                    {{ $item->category?->name ?? '—' }} · {{ strtoupper($item->type) }}
                                </div>
                            </div>
                            <p class="text-sm font-semibold text-primary flex-shrink-0">
                                {{ number_format($item->price / 100, 0, ',', ' ') }} FCFA
                            </p>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="p-5 bg-accent/10 flex flex-col h-[50vh] lg:h-full">
            <form id="order-form" method="POST" action="{{ route('restaurant.orders.store') }}" class="flex flex-col flex-1 min-h-0">
                @csrf
                <input type="hidden" name="items_json" id="items-json">

                <div class="space-y-4 flex-1 overflow-y-auto pr-1">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/45 mb-1.5">Table</label>
                            <input name="table_number" type="text" maxlength="10" required class="w-full px-3 py-2 text-sm rounded-xl border border-secondary/25 outline-none focus:border-secondary" placeholder="12">
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-semibold uppercase tracking-widest text-primary/45 mb-1.5">Total</p>
                            <p id="cart-total" class="font-heading text-lg font-semibold text-primary">0 FCFA</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/45 mb-1.5">Nom (optionnel)</label>
                        <input name="customer_name" type="text" maxlength="120" class="w-full px-3 py-2 text-sm rounded-xl border border-secondary/25 outline-none focus:border-secondary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/45 mb-1.5">Téléphone (optionnel)</label>
                        <input name="customer_phone" type="text" maxlength="30" class="w-full px-3 py-2 text-sm rounded-xl border border-secondary/25 outline-none focus:border-secondary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/45 mb-1.5">Note (optionnel)</label>
                        <textarea name="notes" rows="2" maxlength="2000" class="w-full px-3 py-2 text-sm rounded-xl border border-secondary/25 outline-none focus:border-secondary"></textarea>
                    </div>

                    <div class="rounded-2xl border border-secondary/15 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-primary/45">Panier</p>
                        <div id="cart-lines" class="mt-2 space-y-2"></div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 mt-2 border-t border-secondary/15 shrink-0">
                    <button type="button" onclick="closeCreateOrderModal()" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                    <button id="submit-order" type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Valider</button>
                </div>
            </form>
        </div>
    </div>
</x-modal>
@endrole

<script>
const cart = new Map(); // id -> qty

const formatFCFA = (centimes) => {
    const fcfa = Math.round(Number(centimes || 0) / 100);
    return new Intl.NumberFormat('fr-FR').format(fcfa) + ' FCFA';
};

function getItemEl(id) {
    return document.querySelector(`[data-item][data-id="${id}"]`);
}

function computeTotal() {
    let total = 0;
    cart.forEach((qty, id) => {
        const el = getItemEl(id);
        if (!el) return;
        const price = Number(el.dataset.price || 0);
        total += price * Number(qty);
    });
    return total;
}

function syncTotalsUI() {
    const total = computeTotal();
    document.getElementById('cart-total').textContent = formatFCFA(total);
}

function renderCartLines() {
    const container = document.getElementById('cart-lines');
    if (!container) return;

    if (cart.size === 0) {
        container.innerHTML = `<p class="text-sm text-primary/45">Panier vide</p>`;
        syncTotalsUI();
        return;
    }

    const lines = [];
    cart.forEach((qty, id) => {
        const el = getItemEl(id);
        if (!el) return;
        const name = el.dataset.name || 'Article';
        const price = Number(el.dataset.price || 0);
        const lineTotal = price * Number(qty);
        lines.push(`
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-primary truncate">${escapeHtml(name)}</p>
                    <p class="text-xs text-primary/45">${formatFCFA(price)} x ${qty}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button type="button" onclick="decItem(${Number(id)})" class="h-8 w-8 rounded-lg border border-secondary/25 bg-white hover:bg-accent/20 inline-flex items-center justify-center">
                        <i data-lucide="minus" class="w-4 h-4"></i>
                    </button>
                    <span class="w-6 text-center text-sm font-semibold text-primary">${qty}</span>
                    <button type="button" onclick="incItem(${Number(id)})" class="h-8 w-8 rounded-lg bg-primary text-secondary hover:bg-surface-dark inline-flex items-center justify-center">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </button>
                    <span class="w-20 text-right text-sm font-semibold text-primary">${formatFCFA(lineTotal)}</span>
                </div>
            </div>
        `);
    });

    container.innerHTML = lines.join('');
    if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
    syncTotalsUI();
}

function addToCart(id) {
    const key = String(id);
    cart.set(key, Math.min(99, (cart.get(key) || 0) + 1));
    renderCartLines();
}

function incItem(id) {
    const key = String(id);
    cart.set(key, Math.min(99, (cart.get(key) || 0) + 1));
    renderCartLines();
}

function decItem(id) {
    const key = String(id);
    const next = Math.max(0, (cart.get(key) || 0) - 1);
    if (next <= 0) cart.delete(key);
    else cart.set(key, next);
    renderCartLines();
}

function escapeHtml(value) {
    return (value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function applyFilters() {
    const q = String(document.getElementById('menu-search')?.value || '').trim().toLowerCase();
    const cat = String(document.getElementById('cat-filter')?.value || 'all');

    document.querySelectorAll('[data-item]').forEach((el) => {
        const name = String(el.dataset.name || '').toLowerCase();
        const okQ = !q || name.includes(q) || String(el.textContent || '').toLowerCase().includes(q);
        const okCat = cat === 'all' || String(el.dataset.category || 'none') === cat;
        el.classList.toggle('hidden', !(okQ && okCat));
    });
}

document.getElementById('menu-search')?.addEventListener('input', applyFilters);
document.getElementById('cat-filter')?.addEventListener('change', applyFilters);

window.openCreateOrderModal = function() {
    document.getElementById('create-order-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    renderCartLines();
};

window.closeCreateOrderModal = function() {
    document.getElementById('create-order-modal').classList.add('hidden');
    document.body.style.overflow = '';
};

document.getElementById('order-form')?.addEventListener('submit', function (e) {
    if (cart.size === 0) {
        e.preventDefault();
        alert('Ajoute au moins un article.');
        return;
    }
    const items = [];
    cart.forEach((qty, id) => items.push({ id: Number(id), qty: Number(qty) }));
    document.getElementById('items-json').value = JSON.stringify(items);
    document.getElementById('submit-order')?.setAttribute('disabled', 'disabled');
});

let tableTimer;
const tableInput = document.getElementById('table-input');
if (tableInput) {
    tableInput.addEventListener('input', function() {
        clearTimeout(tableTimer);
        tableTimer = setTimeout(() => this.closest('form').submit(), 400);
    });
}
</script>
@endsection

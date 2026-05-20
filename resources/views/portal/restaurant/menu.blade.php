<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu — {{ $tenant->name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #faf6f0; }
        .font-serif { font-family: 'Playfair Display', serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .bg-brand-dark { background-color: #3e2a22; }
        .text-brand-dark { color: #3e2a22; }
        .border-brand-dark { border-color: #3e2a22; }
    </style>
</head>

<body class="min-h-screen text-gray-800 pb-32">
    <!-- Header -->
    <header class="sticky top-0 z-40 bg-[#faf6f0] px-4 md:px-8 py-5 flex flex-wrap items-center justify-between gap-4 border-b border-gray-200/50">
        <!-- Logo -->
        <div class="flex flex-col">
            <h1 class="font-serif text-2xl md:text-3xl tracking-wide uppercase text-brand-dark">{{ $tenant->name }}</h1>
            <span class="text-[10px] tracking-[0.25em] uppercase text-gray-500 mt-1">Restaurant</span>
            @if($tableNumber)
                <p class="text-xs text-brand-dark/70 mt-1">Table {{ $tableNumber }}</p>
            @endif
        </div>

        <!-- Desktop Search & Actions -->
        <div class="flex items-center gap-3 ml-auto">
            <div class="relative hidden md:block w-72">
                <input id="menu-search-desktop" type="text" placeholder="Rechercher un plat, une boisson..."
                    class="w-full pl-9 pr-3 py-2 rounded-none border border-gray-300 bg-transparent text-sm outline-none focus:border-brand-dark transition-colors">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
            
            <button type="button" onclick="toggleCategories()"
                class="hidden md:inline-flex items-center justify-center h-10 w-10 border border-gray-300 bg-transparent hover:bg-white transition-colors">
                <i data-lucide="list-filter" class="w-4 h-4 text-gray-600"></i>
            </button>
            
            <a href="#cart" onclick="openCart()" class="relative inline-flex items-center justify-center h-10 w-10">
                <i data-lucide="shopping-cart" class="w-5 h-5 text-gray-700"></i>
                <span id="cart-count-badge" class="hidden absolute top-0 right-0 h-4 min-w-[1rem] px-1 rounded-full flex items-center justify-center text-[9px] font-bold bg-gray-600 text-white translate-x-1/4 -translate-y-1/4"></span>
            </a>
        </div>

        <!-- Mobile Search (Full width on small screens) -->
        <div class="w-full md:hidden relative mt-2">
            <input id="menu-search-mobile" type="text" placeholder="Rechercher un plat, une boisson..."
                class="w-full pl-9 pr-3 py-2.5 rounded-none border border-gray-300 bg-transparent text-sm outline-none focus:border-brand-dark transition-colors">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
    </header>

    <!-- Categories -->
    <div id="category-bar" class="px-4 md:px-8 py-4">
        <div class="flex items-center gap-3 overflow-x-auto no-scrollbar pb-2">
            <button type="button" data-cat="all" onclick="filterCategory('all')"
                class="cat-chip px-5 py-1.5 rounded-full text-sm font-medium border border-brand-dark bg-brand-dark text-white whitespace-nowrap transition-colors">
                Tout
            </button>
            @foreach($categories as $category)
                <button type="button" data-cat="{{ $category->id }}" onclick="filterCategory('{{ $category->id }}')"
                    class="cat-chip px-5 py-1.5 rounded-full text-sm font-medium border border-gray-300 bg-transparent text-gray-600 hover:border-gray-400 whitespace-nowrap transition-colors">
                    {{ $category->name }}
                </button>
            @endforeach
            <button type="button" data-cat="none" onclick="filterCategory('none')"
                class="cat-chip px-5 py-1.5 rounded-full text-sm font-medium border border-gray-300 bg-transparent text-gray-600 hover:border-gray-400 whitespace-nowrap transition-colors">
                Autres
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <main class="px-4 md:px-8 py-2">
        @if($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <div id="menu-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @php
                $unsplashImages = [
                    'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80&w=800',
                    'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?auto=format&fit=crop&q=80&w=800',
                    'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&q=80&w=800',
                    'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?auto=format&fit=crop&q=80&w=800',
                    'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&q=80&w=800',
                    'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&q=80&w=800',
                ];
            @endphp
            @foreach($items as $index => $item)
                @php
                    if ($item->image_path) {
                        $imgUrl = Storage::url($item->image_path);
                    } else {
                        $imgUrl = $unsplashImages[$index % count($unsplashImages)];
                    }
                    $badge = null;
                    if (str_contains(strtolower($item->name), 'vegan') || str_contains(strtolower($item->type), 'vegan')) $badge = 'VEGAN';
                    elseif (str_contains(strtolower($item->name), 'chef') || $index % 5 == 0) $badge = '☆ CHEF';
                @endphp
                <article
                    data-item
                    data-id="{{ $item->id }}"
                    data-name="{{ $item->name }}"
                    data-price="{{ $item->price }}"
                    data-category="{{ $item->restaurant_menu_category_id ?? 'none' }}"
                    class="bg-white rounded-2xl overflow-hidden shadow-sm flex flex-col transition-transform hover:-translate-y-1 hover:shadow-md duration-300">
                    
                    <!-- Image Area -->
                    <div class="h-48 w-full relative bg-gray-100">
                        <img src="{{ $imgUrl }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
                        @if($badge)
                            <div class="absolute top-3 left-3 bg-white/90 backdrop-blur px-2.5 py-1 rounded text-[10px] font-bold tracking-wider text-gray-800 shadow-sm">
                                {{ $badge }}
                            </div>
                        @endif
                    </div>

                    <!-- Content Area -->
                    <div class="p-5 flex-1 flex flex-col">
                        <h2 class="font-serif text-[1.15rem] leading-snug text-brand-dark font-medium">{{ $item->name }}</h2>
                        @if($item->description)
                            <p class="text-xs text-gray-500 mt-1.5 line-clamp-2">{{ $item->description }}</p>
                        @endif
                        <p class="text-sm text-brand-dark mt-2 font-medium">
                            {{ number_format($item->price / 100, 0, ',', ' ') }} FCFA
                        </p>
                        
                        <div class="mt-auto pt-6">
                            <hr class="border-gray-100 mb-4">
                            <div class="flex items-center justify-between gap-2">
                                <!-- Quantity Controls -->
                                <div class="flex items-center gap-3 bg-[#f5f1eb] rounded-full px-1 py-1">
                                    <button type="button" onclick="decItem({{ $item->id }})"
                                        class="h-8 w-8 rounded-full flex items-center justify-center text-gray-600 hover:bg-white hover:shadow-sm transition-all">
                                        <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <span id="qty-{{ $item->id }}" class="w-4 text-center text-sm font-medium text-brand-dark">0</span>
                                    <button type="button" onclick="incItem({{ $item->id }})"
                                        class="h-8 w-8 rounded-full flex items-center justify-center text-gray-600 hover:bg-white hover:shadow-sm transition-all">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                                <!-- Action Button -->
                                <button type="button" id="btn-add-{{ $item->id }}" onclick="incItem({{ $item->id }})"
                                    class="bg-brand-dark text-white px-5 py-2.5 rounded-full text-sm font-medium hover:bg-[#2c1e16] transition-colors whitespace-nowrap">
                                    Ajouter
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </main>

    <!-- Floating Action Bar -->
    <div id="floating-cart" class="fixed bottom-6 inset-x-0 z-40 flex justify-center px-4 transition-transform translate-y-24 opacity-0 duration-300">
        <div class="bg-brand-dark text-white rounded-full px-2 py-2 pr-2 w-full max-w-md shadow-2xl flex items-center justify-between">
            <div class="flex items-center gap-3 pl-2">
                <div id="floating-cart-count" class="bg-white/20 text-white rounded-full h-8 w-8 flex items-center justify-center text-sm font-bold">
                    0
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] uppercase tracking-wider text-white/70">Total</span>
                    <span id="floating-cart-total" class="font-serif text-sm font-medium">0 FCFA</span>
                </div>
            </div>
            <button type="button" onclick="openCart()"
                class="bg-white text-brand-dark px-5 py-2.5 rounded-full text-sm font-semibold flex items-center gap-2 hover:bg-gray-100 transition-colors">
                Voir le panier
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>
    </div>

    <!-- Cart Modal -->
    <div id="cart-modal" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onclick="closeCart()"></div>
        <div id="cart" class="relative w-full sm:max-w-xl bg-[#faf6f0] rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
            <div class="px-6 py-5 bg-white border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-serif text-xl font-medium text-brand-dark">Votre commande</h3>
                    <p class="text-xs text-gray-500 mt-1">Vérifiez vos articles avant de valider.</p>
                </div>
                <button type="button" onclick="closeCart()" class="h-10 w-10 rounded-full border border-gray-200 bg-white inline-flex items-center justify-center hover:bg-gray-50 transition-colors text-gray-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1 space-y-6 bg-[#faf6f0]">
                <div id="cart-lines" class="space-y-3"></div>

                <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4">Informations (Optionnel)</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <input id="customer-name" type="text" class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 bg-gray-50 outline-none focus:border-brand-dark focus:bg-white transition-colors" placeholder="Votre nom">
                        </div>
                        <div>
                            <input id="customer-phone" type="text" class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-200 bg-gray-50 outline-none focus:border-brand-dark focus:bg-white transition-colors" placeholder="Numéro de téléphone">
                        </div>
                    </div>
                    <div class="mt-4">
                        <textarea id="order-notes" rows="2" class="w-full px-4 py-3 text-sm rounded-xl border border-gray-200 bg-gray-50 outline-none focus:border-brand-dark focus:bg-white transition-colors" placeholder="Instructions spéciales (ex: sans oignon...)"></textarea>
                    </div>
                </div>
            </div>

            <form id="order-form" method="POST" action="{{ route('portal.restaurant.store', ['tenant' => $tenant->slug]) }}" class="p-6 bg-white border-t border-gray-100 shadow-[0_-10px_30px_rgba(0,0,0,0.03)]">
                @csrf
                <input type="hidden" name="customer_name" id="customer-name-hidden">
                <input type="hidden" name="customer_phone" id="customer-phone-hidden">
                <input type="hidden" name="notes" id="order-notes-hidden">
                <input type="hidden" name="items_json" id="items-json">

                <div class="mb-5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Numéro de Table <span class="text-red-500">*</span></label>
                    <input id="table-number"
                           name="table_number"
                           type="text"
                           maxlength="10"
                           value="{{ $tableNumber }}"
                           placeholder="Ex: 12"
                           required
                           class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 bg-white outline-none focus:border-brand-dark focus:ring-1 focus:ring-brand-dark transition-all">
                </div>

                <div class="flex items-center justify-between gap-4 mt-2">
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">Total de la commande</span>
                        <span id="cart-total-modal" class="font-serif text-2xl font-medium text-brand-dark">0 FCFA</span>
                    </div>
                    <button id="submit-order" type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-brand-dark text-white px-8 py-3.5 text-sm font-medium hover:bg-[#2c1e16] transition-colors shadow-md">
                        Valider
                        <i data-lucide="check" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
const cart = new Map();

const formatFCFA = (centimes) => {
    const fcfa = Math.round(Number(centimes || 0) / 100);
    return new Intl.NumberFormat('fr-FR').format(fcfa) + ' FCFA';
};

function getItemEl(id) {
    return document.querySelector(`[data-item][data-id="${id}"]`);
}

function syncQtyUI(id) {
    const qty = cart.get(String(id)) || 0;
    const el = document.getElementById(`qty-${id}`);
    const btn = document.getElementById(`btn-add-${id}`);
    
    if (el) el.textContent = String(qty);
    if (btn) {
        if (qty > 0) {
            btn.textContent = 'Mettre à jour';
            btn.classList.replace('bg-brand-dark', 'bg-white');
            btn.classList.replace('text-white', 'text-brand-dark');
            btn.classList.add('border', 'border-brand-dark');
        } else {
            btn.textContent = 'Ajouter';
            btn.classList.replace('bg-white', 'bg-brand-dark');
            btn.classList.replace('text-brand-dark', 'text-white');
            btn.classList.remove('border', 'border-brand-dark');
        }
    }
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
    const floatingTotalEl = document.getElementById('floating-cart-total');
    const totalModalEl = document.getElementById('cart-total-modal');
    
    if (floatingTotalEl) floatingTotalEl.textContent = formatFCFA(total);
    if (totalModalEl) totalModalEl.textContent = formatFCFA(total);

    let count = 0;
    cart.forEach((qty) => count += Number(qty));
    
    // Top badge
    const badge = document.getElementById('cart-count-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = String(count);
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    // Floating bar
    const floatingCart = document.getElementById('floating-cart');
    const floatingCount = document.getElementById('floating-cart-count');
    if (floatingCart && floatingCount) {
        if (count > 0) {
            floatingCount.textContent = String(count);
            floatingCart.classList.remove('translate-y-24', 'opacity-0');
        } else {
            floatingCart.classList.add('translate-y-24', 'opacity-0');
        }
    }
}

function incItem(id) {
    const key = String(id);
    const next = Math.min(99, (cart.get(key) || 0) + 1);
    cart.set(key, next);
    syncQtyUI(id);
    syncTotalsUI();
}

function decItem(id) {
    const key = String(id);
    const next = Math.max(0, (cart.get(key) || 0) - 1);
    if (next <= 0) cart.delete(key);
    else cart.set(key, next);
    syncQtyUI(id);
    syncTotalsUI();
}

function openCart() {
    const modal = document.getElementById('cart-modal');
    if (!modal) return;
    renderCartLines();
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCart() {
    const modal = document.getElementById('cart-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function renderCartLines() {
    const container = document.getElementById('cart-lines');
    if (!container) return;

    if (cart.size === 0) {
        container.innerHTML = `
            <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center shadow-sm">
                <div class="mx-auto w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                    <i data-lucide="shopping-bag" class="w-6 h-6 text-gray-400"></i>
                </div>
                <p class="text-base font-serif text-brand-dark">Votre panier est vide</p>
                <p class="text-sm text-gray-500 mt-1">Ajoutez des délices depuis notre menu.</p>
            </div>
        `;
        syncTotalsUI();
        if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
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
            <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[15px] font-medium text-brand-dark truncate">${escapeHtml(name)}</p>
                    <p class="text-xs text-gray-500 mt-0.5">${formatFCFA(price)} x ${qty}</p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0">
                    <div class="flex items-center gap-3 bg-[#f5f1eb] rounded-full px-1 py-1">
                        <button type="button" onclick="decItem(${Number(id)}); renderCartLines();"
                            class="h-7 w-7 rounded-full flex items-center justify-center text-gray-600 hover:bg-white hover:shadow-sm transition-all">
                            <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                        </button>
                        <span class="w-4 text-center text-sm font-medium text-brand-dark">${qty}</span>
                        <button type="button" onclick="incItem(${Number(id)}); renderCartLines();"
                            class="h-7 w-7 rounded-full flex items-center justify-center text-gray-600 hover:bg-white hover:shadow-sm transition-all">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                    <span class="w-20 text-right text-sm font-medium text-brand-dark">${formatFCFA(lineTotal)}</span>
                </div>
            </div>
        `);
    });

    container.innerHTML = lines.join('');
    if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
    syncTotalsUI();
}

function escapeHtml(value) {
    return (value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function toggleCategories() {
    document.getElementById('category-bar')?.classList.toggle('hidden');
}

let selectedCategory = 'all';
function filterCategory(cat) {
    selectedCategory = String(cat);
    document.querySelectorAll('.cat-chip').forEach((chip) => {
        const active = chip.getAttribute('data-cat') === selectedCategory;
        if (active) {
            chip.classList.add('bg-brand-dark', 'text-white', 'border-brand-dark');
            chip.classList.remove('bg-transparent', 'text-gray-600', 'border-gray-300');
        } else {
            chip.classList.remove('bg-brand-dark', 'text-white', 'border-brand-dark');
            chip.classList.add('bg-transparent', 'text-gray-600', 'border-gray-300');
        }
    });
    applyFilters();
}

function applyFilters() {
    const qDesktop = String(document.getElementById('menu-search-desktop')?.value || '').trim().toLowerCase();
    const qMobile = String(document.getElementById('menu-search-mobile')?.value || '').trim().toLowerCase();
    const q = qDesktop || qMobile;
    
    document.querySelectorAll('[data-item]').forEach((el) => {
        const name = String(el.dataset.name || '').toLowerCase();
        const desc = String(el.textContent || '').toLowerCase();
        const cat = String(el.dataset.category || 'none');
        const okCat = selectedCategory === 'all' || selectedCategory === cat;
        const okQ = !q || name.includes(q) || desc.includes(q);
        el.classList.toggle('hidden', !(okCat && okQ));
    });
}

document.getElementById('menu-search-desktop')?.addEventListener('input', (e) => {
    const mobileSearch = document.getElementById('menu-search-mobile');
    if(mobileSearch) mobileSearch.value = e.target.value;
    applyFilters();
});

document.getElementById('menu-search-mobile')?.addEventListener('input', (e) => {
    const desktopSearch = document.getElementById('menu-search-desktop');
    if(desktopSearch) desktopSearch.value = e.target.value;
    applyFilters();
});

document.getElementById('order-form')?.addEventListener('submit', function (e) {
    if (cart.size === 0) {
        e.preventDefault();
        alert('Ajoutez au moins un article avant de valider.');
        return;
    }

    const tableValue = String(document.getElementById('table-number')?.value || '').trim();
    if (!tableValue) {
        e.preventDefault();
        alert('Indiquez le numéro de table avant de valider.');
        return;
    }

    const items = [];
    cart.forEach((qty, id) => items.push({ id: Number(id), qty: Number(qty) }));
    document.getElementById('items-json').value = JSON.stringify(items);

    document.getElementById('customer-name-hidden').value = document.getElementById('customer-name')?.value || '';
    document.getElementById('customer-phone-hidden').value = document.getElementById('customer-phone')?.value || '';
    document.getElementById('order-notes-hidden').value = document.getElementById('order-notes')?.value || '';

    const submitBtn = document.getElementById('submit-order');
    if (submitBtn) {
        submitBtn.setAttribute('disabled', 'disabled');
        submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Traitement...';
        if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
    }
});

syncTotalsUI();
if (typeof window.refreshLucideIcons === 'function') window.refreshLucideIcons();
</script>
</body>
</html>

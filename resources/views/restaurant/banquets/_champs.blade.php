{{-- Champs d'un banquet, au devis comme à la modification. $b : le banquet, ou null. --}}
@php
    $fcfa = fn ($c) => $c === null ? null : (int) round(((int) $c) / 100);
    $restaurantChoisi = (int) old('point_of_sale_id', $b?->point_of_sale_id ?? $restaurantParDefaut?->id);
    $salleChoisie = old('space_id', $b?->space_id);
    $salleChoisieId = $salleChoisie !== null && $salleChoisie !== '' ? (int) $salleChoisie : '';
    $sourcesList = $sources ?? \App\Models\RestaurantBanquet::SOURCES;
    $sourceInitiale = (string) old('source', $b?->source ?? $defaultSource ?? \App\Models\RestaurantBanquet::SOURCE_RESTAURANT);
    $menuSectionsList = $menuSections ?? \App\Models\RestaurantBanquet::MENU_SECTIONS;
    $menuCatalogList = ($menuCatalog ?? collect())->map(fn ($item) => [
        'id' => (int) $item->id,
        'point_of_sale_id' => (int) $item->point_of_sale_id,
        'category' => (string) ($item->category?->name ?? 'Carte'),
        'name' => (string) $item->name,
        'type' => (string) ($item->type ?? 'food'),
        'price_fcfa' => (int) round(((int) $item->price) / 100),
        'description' => (string) ($item->description ?? ''),
    ])->values();
    $oldMenuItems = old('menu_items');
    if (is_array($oldMenuItems)) {
        $initialMenuItems = collect($oldMenuItems)->map(fn ($row) => [
            'course' => (string) ($row['course'] ?? 'plat'),
            'menu_item_id' => ! empty($row['menu_item_id']) ? (int) $row['menu_item_id'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'unit_price' => max(0, (int) ($row['unit_price'] ?? 0)),
            'notes' => (string) ($row['notes'] ?? ''),
        ])->values()->all();
    } elseif ($b && is_array($b->menu_items)) {
        $initialMenuItems = collect($b->menu_items)->map(fn ($row) => [
            'course' => (string) ($row['course'] ?? 'plat'),
            'menu_item_id' => ! empty($row['menu_item_id']) ? (int) $row['menu_item_id'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'unit_price' => $fcfa($row['unit_price'] ?? 0) ?? 0,
            'notes' => (string) ($row['notes'] ?? ''),
        ])->values()->all();
    } else {
        $initialMenuItems = [];
    }
    $customersList = ($customers ?? collect())->map(fn ($c) => [
        'id' => $c->id,
        'name' => trim($c->first_name . ' ' . $c->last_name),
        'first_name' => $c->first_name,
        'last_name' => $c->last_name,
        'phone' => $c->phone ?? '',
        'email' => $c->email ?? '',
    ])->values();
    $bookingsList = ($activeBookings ?? collect())->map(fn ($bk) => [
        'id' => $bk->id,
        'label' => 'CH ' . ($bk->room?->number ?? '?') . ' · ' . $bk->booking_number . ($bk->customer ? ' — ' . trim($bk->customer->first_name . ' ' . $bk->customer->last_name) : ''),
        'customer_id' => $bk->customer_id,
        'client_name' => $bk->customer ? trim($bk->customer->first_name . ' ' . $bk->customer->last_name) : '',
        'client_phone' => $bk->customer?->phone ?? '',
        'client_email' => $bk->customer?->email ?? '',
    ])->values();
    $scopeKey = $b ? ('edit-' . $b->id) : 'create';
@endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2"
     x-data="{
        restaurant: {{ $restaurantChoisi ?: 0 }},
        spaceId: @js((string) $salleChoisieId),
        salles: @js($salles),
        customers: @js($customersList),
        bookings: @js($bookingsList),
        customerId: @js((string) old('customer_id', $b?->customer_id ?? '')),
        bookingId: @js((string) old('booking_id', $b?->booking_id ?? '')),
        createCustomer: @js((bool) old('create_customer', false)),
        clientName: @js((string) old('client_name', $b?->client_name ?? '')),
        clientPhone: @js((string) old('client_phone', $b?->client_phone ?? '')),
        clientEmail: @js((string) old('client_email', $b?->client_email ?? '')),
        quickFirstName: @js((string) old('quick_first_name', '')),
        quickLastName: @js((string) old('quick_last_name', '')),
        eventDate: @js((string) old('event_date', $b?->event_date?->toDateString() ?? '')),
        startTime: @js((string) old('start_time', $b?->start_time ?? '')),
        endTime: @js((string) old('end_time', $b?->end_time ?? '')),
        couverts: {{ (int) old('covers', $b?->covers ?? 0) }},
        prix: {{ (int) old('price_per_cover', $fcfa($b?->price_per_cover) ?? 0) }},
        supplements: {{ (int) old('extras_amount', $fcfa($b?->extras_amount) ?? 0) }},
        menuSections: @js($menuSectionsList),
        menuCatalog: @js($menuCatalogList),
        initialMenuItems: @js($initialMenuItems),
        menuItems: [],
        nextMenuUid: 1,
        menuSearch: '',
        menuSearchOpen: false,
        menuSearchIndex: 0,
        searchCourse: 'auto',
        customCourse: 'plat',
        customName: '',
        customPrice: '',
        priceAppliedFlash: false,
        init() {
            this.menuItems = (Array.isArray(this.initialMenuItems) ? this.initialMenuItems : []).map((row) => ({
                _uid: this.nextMenuUid++,
                course: row.course && this.menuSections[row.course] ? row.course : 'plat',
                menu_item_id: row.menu_item_id ? Number(row.menu_item_id) : null,
                name: String(row.name ?? ''),
                unit_price: Math.max(0, Number(row.unit_price || 0)),
                notes: String(row.notes ?? ''),
            }));

            this.$watch('restaurant', (newRestaurantId) => {
                this.menuSearch = '';
                this.menuSearchOpen = false;
                this.menuSearchIndex = 0;
                this.menuItems = this.menuItems.map((row) => {
                    if (!row.menu_item_id) return row;
                    const linked = this.menuCatalog.find((c) => Number(c.id) === Number(row.menu_item_id));
                    if (!linked || Number(linked.point_of_sale_id) !== Number(newRestaurantId)) {
                        return Object.assign({}, row, { menu_item_id: null });
                    }
                    return row;
                });
            });
        },
        normalizeMenuText(val) {
            return String(val ?? '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        },
        catalogForRestaurant() {
            return this.menuCatalog.filter((item) => Number(item.point_of_sale_id) === Number(this.restaurant));
        },
        filteredMenuCatalog() {
            const list = this.catalogForRestaurant();
            const terms = this.normalizeMenuText(this.menuSearch).split(/\s+/).filter(Boolean);
            if (terms.length === 0) {
                return list.slice(0, 40);
            }
            const first = terms[0];
            return list
                .filter((item) => {
                    const text = this.normalizeMenuText(`${item.name} ${item.category} ${item.description}`);
                    return terms.every((t) => text.includes(t));
                })
                .sort((a, b) => {
                    const na = this.normalizeMenuText(a.name);
                    const nb = this.normalizeMenuText(b.name);
                    const ra = na.startsWith(first) ? 0 : 1;
                    const rb = nb.startsWith(first) ? 0 : 1;
                    return ra - rb || na.localeCompare(nb);
                })
                .slice(0, 40);
        },
        isCatalogItemSelected(id) {
            return this.menuItems.some((row) => Number(row.menu_item_id) === Number(id));
        },
        inferCourse(item) {
            const text = this.normalizeMenuText(`${item.category} ${item.name}`);
            if (text.includes('cocktail') || text.includes('aperitif') || text.includes('mise en bouche') || text.includes('amuse')) {
                return 'aperitif';
            }
            if (item.type === 'drink' || text.includes('boisson') || text.includes('jus') || text.includes('vin') || text.includes('biere') || text.includes('eau') || text.includes('soda')) {
                return 'boisson';
            }
            if (text.includes('entree') || text.includes('salade') || text.includes('soupe') || text.includes('potage')) {
                return 'entree';
            }
            if (text.includes('dessert') || text.includes('patisserie') || text.includes('gateau') || text.includes('glace') || text.includes('fruit')) {
                return 'dessert';
            }
            if (text.includes('accompagnement') || text.includes('garniture') || text.includes('frite') || text.includes('riz')) {
                return 'accompagnement';
            }
            return 'plat';
        },
        addCatalogItem(catalogItem) {
            if (!catalogItem) return;
            const course = this.searchCourse === 'auto' ? this.inferCourse(catalogItem) : this.searchCourse;
            this.menuItems.push({
                _uid: this.nextMenuUid++,
                course: this.menuSections[course] ? course : 'plat',
                menu_item_id: Number(catalogItem.id),
                name: String(catalogItem.name),
                unit_price: Math.max(0, Number(catalogItem.price_fcfa || 0)),
                notes: '',
            });
            this.menuSearch = '';
            this.menuSearchOpen = false;
            this.menuSearchIndex = 0;
        },
        addCustomItem() {
            const trimmed = String(this.customName ?? '').trim();
            if (!trimmed) {
                this.$refs.customMenuNameInput?.focus();
                return;
            }
            this.menuItems.push({
                _uid: this.nextMenuUid++,
                course: this.customCourse && this.menuSections[this.customCourse] ? this.customCourse : 'plat',
                menu_item_id: null,
                name: trimmed,
                unit_price: Math.max(0, Number(this.customPrice || 0)),
                notes: '',
            });
            this.customName = '';
            this.customPrice = '';
            this.$nextTick(() => this.$refs.customMenuNameInput?.focus());
        },
        removeMenuItem(uid) {
            this.menuItems = this.menuItems.filter((row) => row._uid !== uid);
        },
        menuTotalFcfa() {
            return this.menuItems.reduce((sum, row) => sum + Math.max(0, Number(row.unit_price || 0)), 0);
        },
        applyMenuPriceToCover() {
            this.prix = this.menuTotalFcfa();
            this.priceAppliedFlash = true;
            setTimeout(() => { this.priceAppliedFlash = false; }, 1600);
        },
        onMenuSearchKeydown(event) {
            const list = this.filteredMenuCatalog();
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.menuSearchOpen = true;
                if (list.length > 0) {
                    this.menuSearchIndex = (this.menuSearchIndex + 1) % list.length;
                }
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.length > 0) {
                    this.menuSearchIndex = (this.menuSearchIndex - 1 + list.length) % list.length;
                }
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const picked = list[this.menuSearchIndex] || list[0];
                if (this.menuSearchOpen && picked) {
                    this.addCatalogItem(picked);
                }
            } else if (event.key === 'Escape') {
                if (this.menuSearchOpen) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.menuSearchOpen = false;
                }
            }
        },
        sallesDisponibles() {
            return this.salles.filter(s => s.point_of_sale_id === null || Number(s.point_of_sale_id) === Number(this.restaurant));
        },
        salleSelectionnee() {
            if (!this.spaceId) return null;
            return this.salles.find(s => Number(s.id) === Number(this.spaceId)) || null;
        },
        depassementCapacite() {
            const s = this.salleSelectionnee();
            return s && s.capacity && this.couverts > Number(s.capacity);
        },
        onSelectCustomer() {
            if (!this.customerId) return;
            this.createCustomer = false;
            const c = this.customers.find(item => String(item.id) === String(this.customerId));
            if (c) {
                this.clientName = c.name;
                this.clientPhone = c.phone || '';
                this.clientEmail = c.email || '';
            }
        },
        onSelectBooking() {
            if (!this.bookingId) return;
            const bk = this.bookings.find(item => String(item.id) === String(this.bookingId));
            if (!bk) return;
            if (bk.customer_id) {
                this.customerId = String(bk.customer_id);
                this.createCustomer = false;
            }
            if (bk.client_name) this.clientName = bk.client_name;
            if (bk.client_phone) this.clientPhone = bk.client_phone;
            if (bk.client_email) this.clientEmail = bk.client_email;
        },
        appliquerPrefill(detail) {
            if (!detail || detail.target !== '{{ $scopeKey }}') return;
            if (detail.point_of_sale_id) this.restaurant = Number(detail.point_of_sale_id);
            this.$nextTick(() => {
                if (detail.space_id !== undefined && detail.space_id !== null) {
                    this.spaceId = String(detail.space_id);
                }
            });
            if (detail.event_date) this.eventDate = detail.event_date;
            if (detail.start_time !== undefined) this.startTime = detail.start_time || '';
            if (detail.end_time !== undefined) this.endTime = detail.end_time || '';
            if (detail.covers) this.couverts = Number(detail.covers);
        }
     }"
     @banquet-prefill.window="appliquerPrefill($event.detail)">

    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Événement</span>
        <input type="text" name="title" required maxlength="255" value="{{ old('title', $b?->title) }}" placeholder="Ex. Mariage Ngo — dîner"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>

    <label class="block">
        <span class="text-xs text-primary/60">Restaurant</span>
        <select name="point_of_sale_id" x-model.number="restaurant" required
            class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
            @foreach($restaurants as $r)
                <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Salle</span>
        <select name="space_id" x-model="spaceId" class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
            <option value="">À préciser</option>
            <template x-for="s in sallesDisponibles()" :key="s.id">
                <option :value="String(s.id)" x-text="s.name + (s.capacity ? ' (' + s.capacity + ' places)' : '')" :selected="String(s.id) === String(spaceId)"></option>
            </template>
        </select>
    </label>

    <label class="block">
        <span class="text-xs text-primary/60">Canal / Source de la demande</span>
        <select name="source" class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
            @foreach($sourcesList as $code => $libelle)
                <option value="{{ $code }}" @selected($sourceInitiale === $code)>{{ $libelle }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-xs text-primary/60">Séjour résident lié (optionnel)</span>
        <select name="booking_id" x-model="bookingId" @change="onSelectBooking()"
            class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
            <option value="">Client externe / non rattaché à une chambre</option>
            @foreach($activeBookings ?? collect() as $bk)
                <option value="{{ $bk->id }}" @selected((string) old('booking_id', $b?->booking_id) === (string) $bk->id)>
                    CH {{ $bk->room?->number ?? '?' }} · {{ $bk->booking_number }}{{ $bk->customer ? ' — ' . trim($bk->customer->first_name . ' ' . $bk->customer->last_name) : '' }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Fiche client CRM (optionnel)</span>
        <select name="customer_id" x-model="customerId" @change="onSelectCustomer()"
            class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
            <option value="">— Saisie libre ou création rapide —</option>
            @foreach($customers ?? collect() as $cust)
                <option value="{{ $cust->id }}" @selected((string) old('customer_id', $b?->customer_id) === (string) $cust->id)>
                    {{ trim($cust->first_name . ' ' . $cust->last_name) }}@if($cust->phone) · {{ $cust->phone }}@endif
                </option>
            @endforeach
        </select>
    </label>

    <template x-if="!customerId">
        <div class="sm:col-span-2 rounded-lg border border-secondary/20 bg-secondary/5 px-3 py-2.5 space-y-2">
            <label class="flex items-center gap-2 text-xs font-medium text-primary cursor-pointer select-none">
                <input type="checkbox" name="create_customer" value="1" x-model="createCustomer"
                    class="rounded border-secondary/40 text-primary focus:ring-primary">
                <span>Créer automatiquement une fiche client dans le CRM</span>
            </label>
            <div x-show="createCustomer" x-cloak class="grid grid-cols-1 gap-2 sm:grid-cols-2 pt-1">
                <label class="block">
                    <span class="text-[11px] text-primary/60">Prénom (CRM)</span>
                    <input type="text" name="quick_first_name" x-model="quickFirstName" maxlength="255" placeholder="Ex. Aline"
                        class="mt-0.5 w-full rounded-lg border border-secondary/30 bg-white px-2.5 py-1.5 text-xs outline-none focus:border-secondary">
                </label>
                <label class="block">
                    <span class="text-[11px] text-primary/60">Nom (CRM)</span>
                    <input type="text" name="quick_last_name" x-model="quickLastName" maxlength="255" placeholder="Ex. Kamga"
                        class="mt-0.5 w-full rounded-lg border border-secondary/30 bg-white px-2.5 py-1.5 text-xs outline-none focus:border-secondary">
                </label>
            </div>
        </div>
    </template>

    <label class="block">
        <span class="text-xs text-primary/60">Nom / Raison sociale du client</span>
        <input type="text" name="client_name" x-model="clientName" :required="!customerId && !bookingId && !(createCustomer && (quickFirstName || quickLastName))" maxlength="255"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Téléphone</span>
        <input type="text" name="client_phone" x-model="clientPhone" maxlength="40"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Email</span>
        <input type="email" name="client_email" x-model="clientEmail" maxlength="255"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>

    <label class="block">
        <span class="text-xs text-primary/60">Date</span>
        <input type="date" name="event_date" x-model="eventDate" required @if(!$b) min="{{ today()->toDateString() }}" @endif
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <div class="grid grid-cols-2 gap-2">
        <label class="block">
            <span class="text-xs text-primary/60">Début</span>
            <input type="time" name="start_time" x-model="startTime"
                class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
        </label>
        <label class="block">
            <span class="text-xs text-primary/60">Fin</span>
            <input type="time" name="end_time" x-model="endTime"
                class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
        </label>
    </div>

    <div class="block">
        <label class="block">
            <span class="text-xs text-primary/60">Couverts</span>
            <input type="number" name="covers" required min="1" x-model.number="couverts"
                class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
        </label>
        <p x-show="depassementCapacite()" x-cloak class="mt-1 text-[11px] font-medium text-red-600"
           x-text="'Attention : capacité maximale de la salle (' + (salleSelectionnee()?.capacity || 0) + ' couverts) dépassée.'"></p>
    </div>
    <label class="block">
        <span class="text-xs text-primary/60">Prix par couvert (FCFA)</span>
        <input type="number" name="price_per_cover" required min="0" x-model.number="prix"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Suppléments (FCFA)</span>
        <input type="number" name="extras_amount" min="0" x-model.number="supplements"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <label class="block">
        <span class="text-xs text-primary/60">Acompte demandé (FCFA)</span>
        <input type="number" name="deposit_required" min="0" value="{{ old('deposit_required', $fcfa($b?->deposit_required) ?? 0) }}"
            class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
    </label>
    <p class="sm:col-span-2 text-right text-xs text-primary/60">
        Total estimé : <strong class="text-primary" x-text="((couverts || 0) * (prix || 0) + (supplements || 0)).toLocaleString('fr-FR') + ' FCFA'"></strong>
    </p>

    <div id="banquet-menu-builder" class="sm:col-span-2 rounded-xl border border-secondary/25 bg-accent/5 p-3.5 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-primary/75">Menu du banquet</span>
                <span class="rounded-full bg-secondary/20 px-2 py-0.5 text-[11px] font-semibold text-primary"
                      x-text="menuItems.length > 0 ? (menuItems.length + ' élément(s)') : 'Non défini'"></span>
            </div>
            <div x-show="menuItems.length > 0 && menuTotalFcfa() > 0" style="display: none;" class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] text-primary/65">
                    Total carte : <strong class="text-primary" x-text="menuTotalFcfa().toLocaleString('fr-FR') + ' FCFA/couvert'"></strong>
                </span>
                <button type="button"
                        x-on:click="applyMenuPriceToCover()"
                        class="rounded-lg border border-secondary/35 bg-white px-2.5 py-1 text-[11px] font-semibold text-primary hover:bg-accent/20 transition-colors cursor-pointer">
                    <span x-text="priceAppliedFlash ? 'Prix appliqué !' : 'Appliquer au prix/couvert'"></span>
                </button>
            </div>
        </div>

        {{-- Recherche à la saisie dans la carte du restaurant choisi --}}
        <div x-show="catalogForRestaurant().length > 0" style="display: none;" class="space-y-1.5" x-on:click.outside="menuSearchOpen = false">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_170px]">
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i data-lucide="search" class="h-3.5 w-3.5 text-primary/40"></i>
                    </div>
                    <input id="banquet-menu-search-input"
                           type="text"
                           x-model="menuSearch"
                           x-on:focus="menuSearchOpen = true"
                           x-on:input="menuSearchOpen = true; menuSearchIndex = 0"
                           x-on:keydown="onMenuSearchKeydown($event)"
                           autocomplete="off"
                           placeholder="Rechercher un plat ou une boisson de la carte..."
                           class="w-full rounded-lg border border-secondary/30 bg-white py-2 pl-9 pr-3 text-xs text-primary outline-none focus:border-secondary">
                </div>
                <select x-model="searchCourse"
                        aria-label="Service par défaut lors de l'ajout"
                        class="rounded-lg border border-secondary/30 bg-white px-2.5 py-2 text-xs text-primary outline-none focus:border-secondary">
                    <option value="auto">Service : Auto</option>
                    @foreach($menuSectionsList as $sectionCode => $sectionLabel)
                        <option value="{{ $sectionCode }}">{{ $sectionLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="menuSearchOpen"
                 style="display: none;"
                 class="w-full rounded-lg border border-secondary/25 bg-white shadow-md overflow-hidden">
                <ul class="max-h-44 overflow-y-auto divide-y divide-secondary/10 py-1">
                    <template x-for="(catalogItem, index) in filteredMenuCatalog()" :key="catalogItem.id">
                        <li>
                            <button type="button"
                                    x-on:mousedown.prevent="addCatalogItem(catalogItem)"
                                    :class="index === menuSearchIndex ? 'bg-accent/25' : 'hover:bg-accent/15'"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between gap-2 transition-colors">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate text-xs font-semibold text-primary" x-text="catalogItem.name"></span>
                                        <span x-show="isCatalogItemSelected(catalogItem.id)"
                                              class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-800 flex-shrink-0">Déjà au menu</span>
                                    </div>
                                    <p class="truncate text-[10px] text-primary/50" x-text="catalogItem.category"></p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-xs font-semibold text-primary tabular-nums"
                                          x-text="Number(catalogItem.price_fcfa).toLocaleString('fr-FR') + ' FCFA'"></span>
                                    <span class="text-primary/50 text-sm font-bold leading-none">+</span>
                                </div>
                            </button>
                        </li>
                    </template>
                    <li x-show="filteredMenuCatalog().length === 0" class="px-3 py-2 text-xs text-primary/50">
                        Aucun plat correspondant dans la carte de ce restaurant.
                    </li>
                </ul>
            </div>
        </div>

        {{-- Ajout d'un plat ou menu personnalisé hors carte --}}
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[150px_1fr_115px_auto] items-center">
            <select x-model="customCourse"
                    aria-label="Service du plat personnalisé"
                    class="rounded-lg border border-secondary/30 bg-white px-2.5 py-2 text-xs text-primary outline-none focus:border-secondary">
                @foreach($menuSectionsList as $sectionCode => $sectionLabel)
                    <option value="{{ $sectionCode }}">{{ $sectionLabel }}</option>
                @endforeach
            </select>
            <input id="banquet-custom-menu-name"
                   type="text"
                   x-model="customName"
                   x-ref="customMenuNameInput"
                   x-on:keydown.enter.prevent="addCustomItem()"
                   maxlength="160"
                   placeholder="Ou ajouter un plat / menu personnalisé..."
                   class="rounded-lg border border-secondary/30 bg-white px-3 py-2 text-xs text-primary outline-none focus:border-secondary">
            <input type="number"
                   x-model="customPrice"
                   x-on:keydown.enter.prevent="addCustomItem()"
                   min="0"
                   placeholder="Prix FCFA"
                   class="rounded-lg border border-secondary/30 bg-white px-2.5 py-2 text-xs text-primary outline-none focus:border-secondary">
            <button type="button"
                    id="banquet-add-custom-menu-btn"
                    x-on:click="addCustomItem()"
                    class="rounded-lg border border-secondary/30 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-accent/20 transition-colors cursor-pointer">
                + Ajouter
            </button>
        </div>

        {{-- Liste des éléments configurés du menu --}}
        <div x-show="menuItems.length > 0" style="display: none;" class="space-y-2 pt-1">
            <template x-for="(item, idx) in menuItems" :key="item._uid">
                <div class="rounded-lg border border-secondary/20 bg-white p-2.5 space-y-2">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[145px_1fr_110px_auto] items-center">
                        <select :name="'menu_items[' + idx + '][course]'"
                                x-model="item.course"
                                class="rounded-md border border-secondary/25 bg-accent/10 px-2 py-1.5 text-xs font-medium text-primary outline-none focus:border-secondary">
                            @foreach($menuSectionsList as $sectionCode => $sectionLabel)
                                <option value="{{ $sectionCode }}">{{ $sectionLabel }}</option>
                            @endforeach
                        </select>
                        <div class="flex items-center gap-1.5 min-w-0">
                            <input type="text"
                                   :name="'menu_items[' + idx + '][name]'"
                                   x-model="item.name"
                                   required
                                   maxlength="160"
                                   class="w-full min-w-0 rounded-md border border-secondary/25 px-2.5 py-1.5 text-xs font-medium text-primary outline-none focus:border-secondary">
                            <span x-show="item.menu_item_id"
                                  class="rounded bg-secondary/15 px-1.5 py-0.5 text-[10px] font-medium text-primary/70 flex-shrink-0">Carte</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <input type="number"
                                   :name="'menu_items[' + idx + '][unit_price]'"
                                   x-model.number="item.unit_price"
                                   min="0"
                                   class="w-full rounded-md border border-secondary/25 px-2 py-1.5 text-xs text-right tabular-nums text-primary outline-none focus:border-secondary">
                            <span class="text-[10px] text-primary/50 flex-shrink-0">F</span>
                        </div>
                        <button type="button"
                                x-on:click="removeMenuItem(item._uid)"
                                title="Retirer du menu"
                                aria-label="Retirer du menu"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-primary/45 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    <input type="text"
                           :name="'menu_items[' + idx + '][notes]'"
                           x-model="item.notes"
                           maxlength="255"
                           placeholder="Précision / consignes cuisine (optionnel : allergie, service à l'assiette...)"
                           class="w-full rounded-md border border-secondary/15 bg-secondary/5 px-2.5 py-1 text-[11px] text-primary/80 outline-none focus:border-secondary">
                    <input type="hidden" :name="'menu_items[' + idx + '][menu_item_id]'" :value="item.menu_item_id || ''">
                </div>
            </template>
        </div>

        <label class="block">
            <span class="text-[11px] text-primary/60">Détails complémentaires du menu &amp; prestations (optionnel)</span>
            <textarea name="menu" rows="2" maxlength="5000" placeholder="Ex. Boissons d'accueil, dressage en U, découpe du gâteau..."
                class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-xs outline-none focus:border-secondary">{{ old('menu', $b?->menu) }}</textarea>
        </label>
    </div>
    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Notes</span>
        <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">{{ old('notes', $b?->notes) }}</textarea>
    </label>
</div>

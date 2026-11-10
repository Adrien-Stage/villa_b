{{-- Champs d'un banquet, au devis comme à la modification. $b : le banquet, ou null. --}}
@php
    $fcfa = fn ($c) => $c === null ? null : (int) round(((int) $c) / 100);
    $restaurantChoisi = (int) old('point_of_sale_id', $b?->point_of_sale_id ?? $restaurantParDefaut?->id);
    $salleChoisie = old('space_id', $b?->space_id);
    $salleChoisieId = $salleChoisie !== null && $salleChoisie !== '' ? (int) $salleChoisie : '';
    $sourcesList = $sources ?? \App\Models\RestaurantBanquet::SOURCES;
    $sourceInitiale = (string) old('source', $b?->source ?? $defaultSource ?? \App\Models\RestaurantBanquet::SOURCE_RESTAURANT);
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

    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Menu & Prestations</span>
        <textarea name="menu" rows="4" maxlength="5000" class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">{{ old('menu', $b?->menu) }}</textarea>
    </label>
    <label class="block sm:col-span-2">
        <span class="text-xs text-primary/60">Notes</span>
        <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">{{ old('notes', $b?->notes) }}</textarea>
    </label>
</div>

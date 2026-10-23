@extends('layouts.hotel')

@section('title', 'Grille tarifaire')

@section('content')
@php
    $fcfa = fn ($centimes) => number_format(((int) $centimes) / 100, 0, ',', ' ');
    $champ = 'w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm text-primary outline-none focus:border-primary';
    $tarifsJs = $types->flatMap(fn ($type) => $type->rates->map(fn ($r) => [
        'id' => $r->id,
        'room_type_id' => $r->room_type_id,
        'name' => $r->name,
        'rate_type' => $r->rate_type,
        'start_date' => $r->start_date?->toDateString(),
        'end_date' => $r->end_date?->toDateString(),
        'days_of_week' => array_map('intval', $r->days_of_week ?? []),
        'price_adjustment_type' => $r->price_adjustment_type,
        // Montants affichés en FCFA, pourcentage tel quel.
        'price_adjustment' => $r->price_adjustment_type === \App\Models\RoomRate::AJUSTEMENT_POURCENTAGE ? $r->price_adjustment : intdiv($r->price_adjustment, 100),
        'min_nights' => $r->min_nights,
        'max_nights' => $r->max_nights,
        'min_advance_days' => $r->min_advance_days,
        'max_advance_days' => $r->max_advance_days,
        'event_name' => $r->event_name,
        'priority' => $r->priority,
        'is_active' => $r->is_active,
    ]))->keyBy('id');
@endphp

<div class="max-w-6xl mx-auto space-y-6"
     x-data="grilleTarifaire({ tarifs: {{ Js::from($tarifsJs) }}, store: @js(route('rooms.rates.store')), base: @js(url('hebergement/tarifs')) })">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Grille tarifaire</h1>
            <p class="text-sm text-primary/60 mt-0.5 max-w-3xl">
                Le prix de base de chaque type de chambre, et ses tarifs par critère. Chaque nuit d'un séjour prend le tarif
                qui s'y applique — la plus haute priorité, puis événement, saison, jours de la semaine, long séjour, dernière
                minute — sinon le prix de base. Les tarifs ne se cumulent pas ; la surcharge de capacité s'ajoute ensuite.
            </p>
        </div>
    </div>

    @include('housekeeping.partials.flash')

    {{-- Simulation : le prix que la réservation reprendra. --}}
    <form method="GET" action="{{ route('rooms.rates.index') }}" class="bg-white border border-secondary/20 rounded-xl p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-primary">Simuler un séjour</h2>
        <p class="text-xs text-primary/50 mb-3">Le prix de chaque nuit, tel que la réservation le proposera à la réception.</p>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
            <label class="block col-span-2">
                <span class="block text-[11px] font-medium text-primary/60 mb-1">Type de chambre</span>
                <select name="type" required class="{{ $champ }}">
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" @selected((int) request('type') === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-[11px] font-medium text-primary/60 mb-1">Arrivée</span>
                <input type="date" name="arrivee" required value="{{ request('arrivee', today()->toDateString()) }}" class="{{ $champ }}">
            </label>
            <label class="block">
                <span class="block text-[11px] font-medium text-primary/60 mb-1">Départ</span>
                <input type="date" name="depart" required value="{{ request('depart', today()->addDays(3)->toDateString()) }}" class="{{ $champ }}">
            </label>
            <label class="block">
                <span class="block text-[11px] font-medium text-primary/60 mb-1">Adultes / enfants</span>
                <span class="flex gap-2">
                    <input type="number" name="adultes" min="1" max="20" value="{{ request('adultes', 2) }}" aria-label="Adultes" class="{{ $champ }}">
                    <input type="number" name="enfants" min="0" max="20" value="{{ request('enfants', 0) }}" aria-label="Enfants" class="{{ $champ }}">
                </span>
            </label>
            <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark">Calculer</button>
        </div>

        @if($simulation)
            <div class="mt-4 rounded-lg border border-secondary/20 bg-gray-50 p-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm text-primary">
                        <strong>{{ $simulation['type']->name }}</strong> — {{ count($simulation['nuits']) }} nuit(s) :
                        <strong>{{ $fcfa($simulation['total']) }} FCFA</strong>
                        <span class="text-primary/50">(moyenne {{ $fcfa($simulation['par_nuit']) }} FCFA la nuit)</span>
                    </p>
                    <p class="text-xs text-primary/60">{{ $simulation['resume'] }}</p>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach($simulation['nuits'] as $nuit)
                        <span class="inline-flex flex-col rounded-md border px-2 py-1 text-[11px] {{ $nuit['tarif'] ? 'border-secondary/40 bg-accent/20' : 'border-secondary/20 bg-white' }}">
                            <span class="font-semibold text-primary">{{ \Carbon\Carbon::parse($nuit['date'])->locale('fr')->isoFormat('ddd D/MM') }}</span>
                            <span class="text-primary/70">{{ $fcfa($nuit['prix']) }} F</span>
                            <span class="text-primary/45 truncate max-w-28">{{ $nuit['tarif'] ?? 'prix de base' }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </form>

    @forelse($types as $type)
        <section class="bg-white border border-secondary/20 rounded-xl shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-gray-50/80 border-b border-secondary/15">
                <div>
                    <h2 class="text-sm font-semibold text-primary">{{ $type->name }}</h2>
                    <p class="text-xs text-primary/50">Capacité de base : {{ $type->base_capacity }} personne(s) — au-delà, surcharge de capacité.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if($peutRegleBase)
                        <form method="POST" action="{{ route('rooms.rates.types.update', $type) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <label for="base-{{ $type->id }}" class="text-xs text-primary/60">Prix de base</label>
                            <input id="base-{{ $type->id }}" type="number" name="base_price" min="0" step="500" required value="{{ intdiv((int) $type->base_price, 100) }}"
                                   class="w-32 rounded-lg border border-secondary/30 bg-white px-3 py-1.5 text-sm text-right font-mono outline-none focus:border-primary">
                            <span class="text-xs text-primary/50">FCFA</span>
                            <button type="submit" class="px-3 py-1.5 border border-secondary/30 text-xs font-semibold rounded-lg text-primary hover:bg-accent/20">Enregistrer</button>
                        </form>
                    @else
                        <p class="text-sm text-primary">Prix de base : <strong>{{ $fcfa($type->base_price) }} FCFA</strong></p>
                    @endif
                    @if($peutCreer)
                        <button type="button" @click="ouvrir(null, {{ $type->id }})"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter un tarif
                        </button>
                    @endif
                </div>
            </div>

            <x-table :rows="$type->rates" empty="Aucun tarif par critère : toutes les nuits sont au prix de base." empty-icon="tags" :caption="'Tarifs de ' . $type->name">
                <x-slot:head>
                    <x-table.col>Tarif</x-table.col>
                    <x-table.col hide="md">Critère</x-table.col>
                    <x-table.col>Conditions</x-table.col>
                    <x-table.col align="right">Prix de la nuit</x-table.col>
                    <x-table.col align="right" hide="lg">Priorité</x-table.col>
                    @if($peutModifier || $peutSupprimer)<x-table.col actions />@endif
                </x-slot:head>
                @foreach($type->rates as $tarif)
                    <x-table.row :muted="! $tarif->is_active">
                        <x-table.cell>
                            <p class="font-medium text-primary">{{ $tarif->name }}</p>
                            @unless($tarif->is_active)<p class="text-[11px] text-primary/45">Inactif</p>@endunless
                        </x-table.cell>
                        <x-table.cell hide="md" class="text-primary/70">{{ $tarif->typeLabel() }}</x-table.cell>
                        <x-table.cell class="text-xs text-primary/65">{{ $tarif->conditionsLabel() }}</x-table.cell>
                        <x-table.cell align="right" nowrap>
                            <span class="font-semibold text-primary">{{ $tarif->ajustementLabel() }}</span>
                            <span class="block text-[11px] text-primary/45">soit {{ $fcfa($tarif->calculatePrice((int) $type->base_price)) }} F</span>
                        </x-table.cell>
                        <x-table.cell align="right" hide="lg" class="font-mono text-primary/70">{{ $tarif->priority }}</x-table.cell>
                        @if($peutModifier || $peutSupprimer)
                            <x-table.actions :label="'Actions pour le tarif '.$tarif->name">
                                @if($peutModifier)
                                    <x-table.action icon="pencil" x-on:click="ouvrir({{ $tarif->id }})">Modifier</x-table.action>
                                @endif
                                @if($peutSupprimer)
                                    <x-table.action :action="route('rooms.rates.destroy', $tarif)" method="DELETE" icon="trash-2" tone="danger"
                                        confirm="Retirer ce tarif de la grille ?">Supprimer</x-table.action>
                                @endif
                            </x-table.actions>
                        @endif
                    </x-table.row>
                @endforeach
            </x-table>
        </section>
    @empty
        <p class="rounded-xl border border-dashed border-secondary/30 px-4 py-10 text-center text-sm text-primary/50">
            Aucun type de chambre : créez-les d'abord dans Hôtel › Chambres.
        </p>
    @endforelse

    @if($peutCreer || $peutModifier)
        {{-- Fenêtre d'édition d'un tarif : ajout ou modification. --}}
        <div x-show="ouvert" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);"
             @keydown.escape.window="ouvert = false">
            <div class="absolute inset-0" @click="ouvert = false"></div>
            <form method="POST" :action="action()" class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="room_type_id" :value="form.room_type_id">
                <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                    <h3 class="font-heading font-semibold text-primary" x-text="form.id ? 'Modifier le tarif' : 'Ajouter un tarif'"></h3>
                    <button type="button" @click="ouvert = false" class="text-primary/30 hover:text-primary" aria-label="Fermer"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>

                <div class="px-6 py-4 space-y-4 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Nom du tarif *</span>
                            <input type="text" name="name" x-model="form.name" required maxlength="120" placeholder="Ex : Haute saison 2027" class="{{ $champ }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Critère *</span>
                            <select name="rate_type" x-model="form.rate_type" required class="{{ $champ }}">
                                @foreach(\App\Models\RoomRate::TYPES as $cle => $libelle)
                                    <option value="{{ $cle }}">{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Du <span x-show="avecPeriode()">*</span></span>
                            <input type="date" name="start_date" x-model="form.start_date" :required="avecPeriode()" class="{{ $champ }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Au <span x-show="avecPeriode()">*</span></span>
                            <input type="date" name="end_date" x-model="form.end_date" :required="avecPeriode()" class="{{ $champ }}">
                        </label>
                        <p class="sm:col-span-2 -mt-2 text-[11px] text-primary/45" x-show="!avecPeriode()">Sans dates, le tarif vaut toute l'année.</p>
                    </div>

                    <fieldset>
                        <legend class="block text-xs font-medium text-primary/70 mb-1">Jours concernés <span x-show="form.rate_type === 'weekly'">*</span></legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach(\App\Models\RoomRate::JOURS as $jour => $libelle)
                                <label class="inline-flex items-center justify-center w-12 h-9 rounded-lg border border-secondary/25 text-xs cursor-pointer has-[:checked]:bg-primary has-[:checked]:text-white has-[:checked]:border-primary has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-secondary">
                                    <input type="checkbox" name="days_of_week[]" value="{{ $jour }}" class="sr-only" :checked="form.days_of_week.includes({{ $jour }})"
                                           @change="basculerJour({{ $jour }})">
                                    {{ $libelle }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[11px] text-primary/45">Aucun jour coché : tous les jours de la période.</p>
                    </fieldset>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border border-secondary/20 bg-gray-50 p-3">
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Prix de la nuit *</span>
                            <select name="price_adjustment_type" x-model="form.price_adjustment_type" required class="{{ $champ }}">
                                @foreach(\App\Models\RoomRate::AJUSTEMENTS as $cle => $libelle)
                                    <option value="{{ $cle }}">{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1" x-text="form.price_adjustment_type === 'percentage' ? 'Pourcentage (+ majore, − remise)' : 'Montant (FCFA)'"></span>
                            <input type="number" name="price_adjustment" x-model.number="form.price_adjustment" required class="{{ $champ }} font-mono text-right"
                                   :min="form.price_adjustment_type === 'percentage' ? -100 : 0" :max="form.price_adjustment_type === 'percentage' ? 500 : null">
                        </label>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Nuits min. <span x-show="form.rate_type === 'long_stay'">*</span></span>
                            <input type="number" name="min_nights" x-model="form.min_nights" min="1" max="365" :required="form.rate_type === 'long_stay'" class="{{ $champ }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Nuits max.</span>
                            <input type="number" name="max_nights" x-model="form.max_nights" min="1" max="365" class="{{ $champ }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Réservé au moins … j. avant</span>
                            <input type="number" name="min_advance_days" x-model="form.min_advance_days" min="0" max="365" class="{{ $champ }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Réservé au plus … j. avant <span x-show="form.rate_type === 'last_minute'">*</span></span>
                            <input type="number" name="max_advance_days" x-model="form.max_advance_days" min="0" max="365" :required="form.rate_type === 'last_minute'" class="{{ $champ }}">
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="block sm:col-span-2" x-show="form.rate_type === 'event'">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Événement *</span>
                            <input type="text" name="event_name" x-model="form.event_name" maxlength="120" :required="form.rate_type === 'event'" :disabled="form.rate_type !== 'event'"
                                   placeholder="Ex : FESPAM 2027" class="{{ $champ }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-primary/70 mb-1">Priorité</span>
                            <input type="number" name="priority" x-model="form.priority" min="1" max="99" class="{{ $champ }}">
                            <span class="block mt-0.5 text-[11px] text-primary/45">Deux tarifs le même jour : la plus haute l'emporte.</span>
                        </label>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-primary/80">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active"> Tarif actif
                    </label>
                </div>

                <div class="flex justify-end gap-2 px-6 py-4 border-t border-secondary/20 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="ouvert = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark">Enregistrer</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function grilleTarifaire(cfg) {
        const vierge = (typeId) => ({
            id: null, room_type_id: typeId, name: '', rate_type: 'seasonal', start_date: '', end_date: '', days_of_week: [],
            price_adjustment_type: 'percentage', price_adjustment: 0, min_nights: '', max_nights: '', min_advance_days: '',
            max_advance_days: '', event_name: '', priority: 1, is_active: true,
        });

        return {
            ouvert: false,
            form: vierge(null),
            ouvrir(id, typeId = null) {
                const tarif = id ? cfg.tarifs[id] : null;
                this.form = tarif
                    ? { ...vierge(tarif.room_type_id), ...tarif, days_of_week: [...(tarif.days_of_week || [])],
                        start_date: tarif.start_date || '', end_date: tarif.end_date || '',
                        min_nights: tarif.min_nights ?? '', max_nights: tarif.max_nights ?? '',
                        min_advance_days: tarif.min_advance_days ?? '', max_advance_days: tarif.max_advance_days ?? '', event_name: tarif.event_name || '' }
                    : vierge(typeId);
                this.ouvert = true;
                this.$nextTick(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
            },
            action() { return this.form.id ? `${cfg.base}/${this.form.id}` : cfg.store; },
            avecPeriode() { return ['seasonal', 'event'].includes(this.form.rate_type); },
            basculerJour(jour) {
                const i = this.form.days_of_week.indexOf(jour);
                i === -1 ? this.form.days_of_week.push(jour) : this.form.days_of_week.splice(i, 1);
            },
        };
    }
</script>
@endpush

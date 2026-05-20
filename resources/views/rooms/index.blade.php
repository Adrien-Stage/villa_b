@extends('layouts.hotel')

@section('title', 'Chambres')

@section('content')

{{-- En-tête --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Chambres</h1>
        <p class="text-sm text-primary/50 mt-0.5">
            {{ $counts['all'] }} chambre{{ $counts['all'] > 1 ? 's' : '' }} ·
            {{ $roomTypes->count() }} type{{ $roomTypes->count() > 1 ? 's' : '' }}
        </p>
    </div>
    @if($tab === 'rooms')
    @role('manager')
    <button onclick="document.getElementById('modal-create-room').classList.remove('hidden')"
        class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nouvelle chambre
    </button>
    @endrole
    @else
    @role('manager')
    <button onclick="document.getElementById('modal-create-type').classList.remove('hidden')"
        class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nouveau type
    </button>
    @endrole
    @endif
</div>

{{-- Messages flash --}}
@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
    {{ session('success') }}
</div>
@endif
@if($errors->has('delete'))
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
    {{ $errors->first('delete') }}
</div>
@endif

{{-- Onglets --}}
<div class="flex items-center gap-6 border-b border-secondary/20 mb-5">
    <a href="{{ route('rooms.index', array_merge(request()->query(), ['tab' => 'rooms'])) }}"
        class="flex items-center gap-2 pb-3 text-sm font-medium border-b-2 transition-colors
              {{ $tab === 'rooms' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
        <i data-lucide="hotel" class="w-4 h-4"></i>
        Chambres
    </a>
    <a href="{{ route('rooms.index', array_merge(request()->query(), ['tab' => 'types'])) }}"
        class="flex items-center gap-2 pb-3 text-sm font-medium border-b-2 transition-colors
              {{ $tab === 'types' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
        <i data-lucide="layers" class="w-4 h-4"></i>
        Types de chambre
    </a>
</div>

{{-- ===== ONGLET CHAMBRES ===== --}}
@if($tab === 'rooms')

{{-- Barre outils --}}
<div class="flex items-center justify-between gap-4 mb-4">
    <div class="flex items-center gap-2 flex-wrap">
        @php
        $user = auth()->user();
        $isHousekeepingOnly = $user->hasAnyRole(['housekeeping', 'housekeeping_chief', 'housekeeping_staff', 'housekeeping_leader']) && !$user->hasAnyRole(['manager', 'reception']);

        if ($isHousekeepingOnly) {
            $filters = [
                'all' => 'Toutes',
                'dirty' => 'Sale',
                'cleaning' => 'En nettoyage',
                'clean' => 'Propre',
                'inspected' => 'Contrôlée',
                'maintenance' => 'Maintenance',
            ];
        } else {
            $filters = [
                'all' => 'Toutes',
                'available' => 'Disponibles',
                'occupied' => 'Occupées',
                'dirty' => 'Sale',
                'cleaning' => 'Nettoyage',
                'clean' => 'Propre',
                'maintenance' => 'Maintenance',
                'out_of_order' => 'Hors service',
            ];
        }
        @endphp
        @foreach($filters as $value => $label)
        <a href="{{ route('rooms.index', array_merge(request()->query(), ['tab' => 'rooms', 'status' => $value])) }}"
            class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                          {{ $status === $value
                              ? 'bg-primary text-white'
                              : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
            {{ $label }}
            <span class="ml-1 opacity-70">({{ $counts[$value] }})</span>
        </a>
        @endforeach
    </div>

    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('rooms.index') }}" class="relative">
            <input type="hidden" name="tab" value="rooms">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="text"
                name="search"
                id="search-input"
                value="{{ $search }}"
                placeholder="Rechercher une chambre..."
                class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-52 transition-all"
                autocomplete="off">
            <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
        </form>

        <div class="flex items-center bg-white border border-secondary/30 rounded-lg overflow-hidden">
            <a href="{{ route('rooms.index', array_merge(request()->query(), ['view' => 'list'])) }}"
                class="px-3 py-2 transition-colors {{ $view === 'list' ? 'bg-primary text-white' : 'text-primary/40 hover:text-primary' }}">
                <i data-lucide="list" class="w-4 h-4"></i>
            </a>
            <a href="{{ route('rooms.index', array_merge(request()->query(), ['view' => 'card'])) }}"
                class="px-3 py-2 transition-colors {{ $view === 'card' ? 'bg-primary text-white' : 'text-primary/40 hover:text-primary' }}">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</div>

{{-- Vue Liste --}}
@if($view === 'list')
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    @if($rooms->isEmpty())
    <div class="flex flex-col items-center justify-center py-16 text-primary/30">
        <i data-lucide="door-open" class="w-10 h-10 mb-3 opacity-40"></i>
        <p class="text-sm">Aucune chambre trouvée</p>
    </div>
    @else
    <div class="grid grid-cols-12 gap-4 px-5 py-3 border-b border-secondary/10 bg-accent/20">
        <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Chambre</div>
        <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Type</div>
        <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40">Étage / Vue</div>
        <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Statut</div>
        <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Housekeeping</div>
        <div class="col-span-1"></div>
    </div>

    @foreach($rooms as $room)
    @php
    $statusColors = [
    'available' => 'bg-green-50 text-green-700 border-green-200',
    'occupied' => 'bg-blue-50 text-blue-700 border-blue-200',
    'cleaning' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'maintenance' => 'bg-orange-50 text-orange-700 border-orange-200',
    'out_of_order' => 'bg-red-50 text-red-700 border-red-200',
    ];
    $colorClass = $statusColors[$room->status->value] ?? 'bg-secondary/10 text-primary/60 border-secondary/30';
    @endphp
    <div class="grid grid-cols-12 gap-4 px-5 py-3.5 border-b border-secondary/10 hover:bg-accent/10 transition-colors items-center">
        <div class="col-span-2 flex items-center gap-2">
            <i data-lucide="door-open" class="w-4 h-4 text-primary/30 flex-shrink-0"></i>
            <span class="text-sm font-semibold text-primary">{{ $room->number }}</span>
        </div>
        <div class="col-span-2 text-sm text-primary/70">{{ $room->roomType->name }}</div>
        <div class="col-span-3 flex items-center gap-2 text-sm text-primary/70">
            <span>{{ $room->floor ?? '—' }}</span>
            @if($room->view_type)
            <span class="text-primary/30">·</span>
            <span class="capitalize">{{ $room->view_type }}</span>
            @endif
        </div>
        <div class="col-span-2">
            <span class="px-2.5 py-1 text-xs font-medium rounded-full border {{ $colorClass }}">
                {{ $room->status->label() }}
            </span>
        </div>
        <div class="col-span-2">
            @if($room->activeHousekeepingAssignment)
            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-yellow-50 text-yellow-700 border border-yellow-200">
                {{ $room->activeHousekeepingAssignment->team->name }}
            </span>
            @elseif($room->status->value === 'dirty')
            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-red-50 text-red-700 border border-red-200">
                à affecter
            </span>
            @elseif(in_array($room->status->value, ['clean', 'inspected', 'available']))
            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                prêt
            </span>
            @else
            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-secondary/10 text-primary/60 border border-secondary/20">
                suivi manuel
            </span>
            @endif
        </div>
        <div class="col-span-1 flex items-center justify-end gap-1">
            <a href="{{ route('rooms.show', $room) }}"
                class="p-1.5 text-primary/30 hover:text-primary transition-colors rounded"
                title="Voir détail">
                <i data-lucide="settings" class="w-4 h-4"></i>
            </a>
            @role('manager')
            <button
                data-id="{{ $room->id }}"
                data-number="{{ $room->number }}"
                data-type="{{ $room->room_type_id }}"
                data-floor="{{ $room->floor }}"
                data-view="{{ $room->view_type }}"
                onclick="openEditRoom(this)"
                class="p-1.5 text-primary/30 hover:text-primary transition-colors rounded"
                title="Modifier">
                <i data-lucide="pencil" class="w-4 h-4"></i>
            </button>
            <form method="POST" action="{{ route('rooms.destroy', $room) }}"
                onsubmit="return confirm('Supprimer la chambre {{ $room->number }} ?')"
                class="expect-popup">
                @csrf @method('DELETE')
                <button type="submit" class="p-1.5 text-primary/30 hover:text-red-500 transition-colors rounded" title="Supprimer">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
            @endrole
        </div>
    </div>
    @endforeach
    @endif
</div>

@if($rooms->hasPages())
<div class="mt-4">{{ $rooms->links() }}</div>
@endif

{{-- Vue Carte --}}
@else
@if($rooms->isEmpty())
<div class="flex flex-col items-center justify-center py-16 text-primary/30">
    <p class="text-sm">Aucune chambre trouvée</p>
</div>
@else
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
    @foreach($rooms as $room)
    @php
    $cardColors = [
    'available' => ['border' => 'border-green-200', 'dot' => 'bg-green-400', 'badge' => 'bg-green-50 text-green-700'],
    'occupied' => ['border' => 'border-blue-200', 'dot' => 'bg-blue-400', 'badge' => 'bg-blue-50 text-blue-700'],
    'cleaning' => ['border' => 'border-yellow-200', 'dot' => 'bg-yellow-400', 'badge' => 'bg-yellow-50 text-yellow-700'],
    'maintenance' => ['border' => 'border-orange-200', 'dot' => 'bg-orange-400', 'badge' => 'bg-orange-50 text-orange-700'],
    'out_of_order' => ['border' => 'border-red-200', 'dot' => 'bg-red-400', 'badge' => 'bg-red-50 text-red-700'],
    ];
    $c = $cardColors[$room->status->value] ?? ['border' => 'border-secondary/20', 'dot' => 'bg-secondary', 'badge' => 'bg-secondary/10 text-primary/60'];
    $roomImages = $room->images;
    @endphp
    <div class="bg-white rounded-xl border {{ $c['border'] }} shadow-sm hover:shadow-md transition-shadow overflow-hidden">
        {{-- Image Slideshow --}}
        @if($roomImages->count() > 0)
        <div class="relative aspect-[4/3] bg-gray-100 group/slide" x-data="{
                current: 0,
                total: {{ $roomImages->count() }},
                timer: null,
                init() {
                    if (this.total > 1) this.startAuto();
                },
                destroy() { clearInterval(this.timer); },
                startAuto() { this.timer = setInterval(() => { this.current = (this.current + 1) % this.total; }, 3000); },
                pauseAuto() { clearInterval(this.timer); },
                resumeAuto() { if (this.total > 1) this.startAuto(); }
            }"
            @mouseenter="pauseAuto()"
            @mouseleave="resumeAuto()">
            @foreach($roomImages as $idx => $img)
            <img src="{{ asset('storage/' . $img->path) }}" alt="Chambre {{ $room->number }}" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300" :class="current === {{ $idx }} ? 'opacity-100' : 'opacity-0 pointer-events-none'" @click="$dispatch('open-lightbox', { images: {{ $roomImages->pluck('path')->map(fn($p) => asset('storage/'.$p))->toJson() }}, index: current })">
            @endforeach
            @if($roomImages->count() > 1)
            <button @click.stop="current = (current - 1 + total) % total" class="absolute left-1.5 top-1/2 -translate-y-1/2 w-7 h-7 bg-black/40 hover:bg-black/60 backdrop-blur-sm text-white rounded-full flex items-center justify-center opacity-0 group-hover/slide:opacity-100 transition-opacity">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
            <button @click.stop="current = (current + 1) % total" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-7 h-7 bg-black/40 hover:bg-black/60 backdrop-blur-sm text-white rounded-full flex items-center justify-center opacity-0 group-hover/slide:opacity-100 transition-opacity">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
            <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                <template x-for="i in total" :key="i">
                    <button @click.stop="current = i - 1" class="w-1.5 h-1.5 rounded-full transition-all" :class="current === i - 1 ? 'bg-white w-3' : 'bg-white/50'"></button>
                </template>
            </div>
            @endif
            <div class="absolute top-2 left-2 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full {{ $c['dot'] }}"></span>
            </div>
            <div class="absolute top-2 right-2">
                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full backdrop-blur-sm bg-white/80 {{ $c['badge'] }}">{{ $room->status->label() }}</span>
            </div>
            <button @click.stop="$dispatch('open-lightbox', { images: {{ $roomImages->pluck('path')->map(fn($p) => asset('storage/'.$p))->toJson() }}, index: current })" class="absolute bottom-2 right-2 w-7 h-7 bg-black/40 hover:bg-black/60 backdrop-blur-sm text-white rounded-full flex items-center justify-center opacity-0 group-hover/slide:opacity-100 transition-opacity" title="Agrandir">
                <i data-lucide="maximize-2" class="w-3.5 h-3.5"></i>
            </button>
        </div>
        @else
        <div class="relative aspect-[4/3] bg-gray-50 flex flex-col items-center justify-center text-primary/15">
            <i data-lucide="image" class="w-10 h-10 mb-1"></i>
            <span class="text-[9px] uppercase tracking-wider font-medium">Pas de photo</span>
            <div class="absolute top-2 left-2 flex items-center gap-1"><span class="w-2 h-2 rounded-full {{ $c['dot'] }}"></span></div>
        </div>
        @endif

        <div class="p-4">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <span class="font-heading font-semibold text-primary text-lg">{{ $room->number }}</span>
                    <p class="text-xs text-primary/50 mt-0.5">{{ $room->roomType->name }}</p>
                </div>
                @if($roomImages->isEmpty())
                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $c['badge'] }}">{{ $room->status->label() }}</span>
                @endif
            </div>
            <div class="space-y-1 mb-3">
                @if($room->floor)
                <div class="flex items-center gap-1.5 text-xs text-primary/50"><i data-lucide="building-2" class="w-3 h-3"></i> Étage {{ $room->floor }}</div>
                @endif
                @if($room->view_type)
                <div class="flex items-center gap-1.5 text-xs text-primary/50 capitalize"><i data-lucide="eye" class="w-3 h-3"></i> Vue {{ $room->view_type }}</div>
                @endif
                <div class="flex items-center gap-1.5 text-xs text-primary/50"><i data-lucide="users" class="w-3 h-3"></i> {{ $room->roomType->base_capacity }} pers.</div>
            </div>
            <div class="flex items-center justify-end gap-1 pt-3 border-t border-secondary/10">
                <a href="{{ route('rooms.show', $room) }}" class="p-1.5 text-primary/30 hover:text-primary transition-colors rounded"><i data-lucide="settings" class="w-3.5 h-3.5"></i></a>
                <button data-id="{{ $room->id }}" data-number="{{ $room->number }}" data-type="{{ $room->room_type_id }}" data-floor="{{ $room->floor }}" data-view="{{ $room->view_type }}" onclick="openEditRoom(this)" class="p-1.5 text-primary/30 hover:text-primary transition-colors rounded"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
                <form method="POST" action="{{ route('rooms.destroy', $room) }}" onsubmit="return confirm('Supprimer la chambre {{ $room->number }} ?')" class="expect-popup">@csrf @method('DELETE')<button type="submit" class="p-1.5 text-primary/30 hover:text-red-500 transition-colors rounded"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form>
            </div>
        </div>
    </div>
    @endforeach
</div>

@if($rooms->hasPages())
<div class="mt-4">{{ $rooms->links() }}</div>
@endif
@endif
@endif

{{-- ===== ONGLET TYPES ===== --}}
@else
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    @if($roomTypes->isEmpty())
    <div class="flex flex-col items-center justify-center py-16 text-primary/30">
        <p class="text-sm">Aucun type de chambre</p>
    </div>
    @else
    <div class="grid grid-cols-12 gap-4 px-5 py-3 border-b border-secondary/10 bg-accent/20">
        <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40">Nom</div>
        <div class="col-span-4 text-xs font-semibold uppercase tracking-widest text-primary/40">Description</div>
        <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Capacité</div>
        <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Nb chambres</div>
        <div class="col-span-1"></div>
    </div>

    @foreach($roomTypes as $type)
    <div class="grid grid-cols-12 gap-4 px-5 py-3.5 border-b border-secondary/10 hover:bg-accent/10 transition-colors items-center">
        <div class="col-span-3 font-medium text-sm text-primary">{{ $type->name }}</div>
        <div class="col-span-4 text-sm text-primary/50">{{ $type->description ?? '—' }}</div>
        <div class="col-span-2 text-sm text-primary/70">{{ $type->base_capacity }} pers.</div>
        <div class="col-span-2 text-sm text-primary/70">{{ $type->rooms_count }}</div>
        <div class="col-span-1 flex items-center justify-end gap-1">
            {{-- ✅ data-* attributes au lieu de paramètres JS inline --}}
            <button
                data-id="{{ $type->id }}"
                data-name="{{ $type->name }}"
                data-code="{{ $type->code }}"
                data-desc="{{ $type->description }}"
                data-base-cap="{{ $type->base_capacity }}"
                data-max-cap="{{ $type->max_capacity }}"
                data-price="{{ $type->base_price / 100 }}"
                data-sqm="{{ $type->size_sqm ?? 0 }}"
                onclick="openEditType(this)"
                class="p-1.5 text-primary/30 hover:text-primary transition-colors rounded">
                <i data-lucide="pencil" class="w-4 h-4"></i>
            </button>
            <form method="POST" action="{{ route('rooms.types.destroy', $type) }}"
                onsubmit="return confirm('Supprimer le type {{ $type->name }} ?')"
                class="expect-popup">
                @csrf @method('DELETE')
                <button type="submit" class="p-1.5 text-primary/30 hover:text-red-500 transition-colors rounded">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>
    @endforeach
    @endif
</div>
@endif


{{-- ===================================================== --}}
{{-- MODALS                                                --}}
{{-- ===================================================== --}}

<style>
    .modal-backdrop {
        background: rgba(15, 2, 1, 0.5);
        backdrop-filter: blur(4px);
    }

    .modal-input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        border: 1px solid rgba(204, 171, 135, 0.3);
        border-radius: 0.5rem;
        color: #391F0E;
        outline: none;
        transition: border-color 0.15s;
        background: white;
    }

    .modal-input:focus {
        border-color: #CCAB87;
    }

    .modal-label {
        display: block;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(57, 31, 14, 0.5);
        margin-bottom: 0.35rem;
    }
</style>

{{-- Modal : Créer chambre --}}
<div id="modal-create-room" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-backdrop">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <h3 class="font-heading font-semibold text-primary">Nouvelle chambre</h3>
            <button onclick="document.getElementById('modal-create-room').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('rooms.store') }}" enctype="multipart/form-data" class="flex flex-col flex-1 min-h-0 overflow-hidden expect-popup">
            @csrf
            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
            <div>
                <label class="modal-label">Type de chambre *</label>
                <select name="room_type_id" required class="modal-input">
                    <option value="">Sélectionner...</option>
                    @foreach($roomTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="modal-label">Numéro *</label>
                    <input type="text" name="number" placeholder="101" required class="modal-input">
                </div>
                <div>
                    <label class="modal-label">Étage</label>
                    <input type="text" name="floor" placeholder="1" class="modal-input">
                </div>
            </div>
            <div>
                <label class="modal-label">Vue</label>
                <select name="view_type" class="modal-input">
                    <option value="">Aucune</option>
                    <option value="garden">Garden</option>
                    <option value="pool">Pool</option>
                    <option value="heritage">Heritage</option>
                    <option value="courtyard">Courtyard</option>
                    <option value="city">City</option>
                </select>
            </div>
            <div>
                <label class="modal-label">Notes internes</label>
                <textarea name="notes" rows="2" placeholder="Notes optionnelles..." class="modal-input resize-none"></textarea>
            </div>
            <div x-data="multiImagePreview()">
                <label class="modal-label">Photos (max 4)</label>
                <div @click="$refs.fileInput.click()" @dragover.prevent="isDragging=true" @dragleave.prevent="isDragging=false" @drop.prevent="handleDrop($event)" :class="isDragging ? 'border-primary bg-primary/5' : 'border-gray-300 hover:border-primary/40'" class="border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all">
                    <i data-lucide="image-plus" class="w-6 h-6 mx-auto text-primary/30 mb-1"></i>
                    <p class="text-xs text-primary/50">Cliquez ou glissez · JPG, PNG, WebP · Max 3 Mo/image</p>
                </div>
                <input type="file" name="images[]" x-ref="fileInput" @change="handleFiles($event)" accept="image/*" multiple class="hidden">
                <div x-show="previews.length > 0" class="grid grid-cols-4 gap-2 mt-3">
                    <template x-for="(p, i) in previews" :key="i">
                        <div class="relative rounded-lg overflow-hidden aspect-square bg-gray-100">
                            <img :src="p" class="w-full h-full object-cover">
                            <button type="button" @click="removeImage(i)" class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">×</button>
                        </div>
                    </template>
                </div>
            </div>
            </div>
            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="document.getElementById('modal-create-room').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Créer la chambre
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Éditer chambre --}}
<div id="modal-edit-room" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-backdrop">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <h3 class="font-heading font-semibold text-primary">Modifier la chambre</h3>
            <button onclick="document.getElementById('modal-edit-room').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form id="form-edit-room" method="POST" action="" enctype="multipart/form-data" class="flex flex-col flex-1 min-h-0 overflow-hidden expect-popup">
            @csrf
            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
            @method('PUT')
            <div>
                <label class="modal-label">Type de chambre *</label>
                <select id="edit-room-type" name="room_type_id" required class="modal-input">
                    @foreach($roomTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="modal-label">Numéro *</label>
                    <input type="text" id="edit-room-number" name="number" required class="modal-input">
                </div>
                <div>
                    <label class="modal-label">Étage</label>
                    <input type="text" id="edit-room-floor" name="floor" class="modal-input">
                </div>
            </div>
            <div>
                <label class="modal-label">Vue</label>
                <select id="edit-room-view" name="view_type" class="modal-input">
                    <option value="">Aucune</option>
                    <option value="garden">Garden</option>
                    <option value="pool">Pool</option>
                    <option value="heritage">Heritage</option>
                    <option value="courtyard">Courtyard</option>
                    <option value="city">City</option>
                </select>
            </div>
            <div x-data="multiImagePreview()">
                <label class="modal-label">Ajouter des photos (max 4 au total)</label>
                <div @click="$refs.fileInput.click()" @dragover.prevent="isDragging=true" @dragleave.prevent="isDragging=false" @drop.prevent="handleDrop($event)" :class="isDragging ? 'border-primary bg-primary/5' : 'border-gray-300 hover:border-primary/40'" class="border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all">
                    <i data-lucide="image-plus" class="w-6 h-6 mx-auto text-primary/30 mb-1"></i>
                    <p class="text-xs text-primary/50">Cliquez ou glissez · JPG, PNG, WebP · Max 3 Mo/image</p>
                </div>
                <input type="file" name="images[]" x-ref="fileInput" @change="handleFiles($event)" accept="image/*" multiple class="hidden">
                <div x-show="previews.length > 0" class="grid grid-cols-4 gap-2 mt-3">
                    <template x-for="(p, i) in previews" :key="i">
                        <div class="relative rounded-lg overflow-hidden aspect-square bg-gray-100">
                            <img :src="p" class="w-full h-full object-cover">
                            <button type="button" @click="removeImage(i)" class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">×</button>
                        </div>
                    </template>
                </div>
            </div>
            </div>
            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="document.getElementById('modal-edit-room').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Créer type --}}
<div id="modal-create-type" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-backdrop">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <h3 class="font-heading font-semibold text-primary">Nouveau type de chambre</h3>
            <button onclick="document.getElementById('modal-create-type').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('rooms.types.store') }}" class="flex flex-col flex-1 min-h-0 overflow-hidden expect-popup">
            @csrf
            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="modal-label">Nom *</label>
                    <input type="text" name="name" placeholder="Standard" required class="modal-input">
                </div>
                <div>
                    <label class="modal-label">Code *</label>
                    <input type="text" name="code" placeholder="STD" required class="modal-input">
                </div>
            </div>
            <div>
                <label class="modal-label">Description</label>
                <textarea name="description" rows="2" class="modal-input resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="modal-label">Capacité base *</label>
                    <input type="number" name="base_capacity" value="2" min="1" required class="modal-input">
                </div>
                <div>
                    <label class="modal-label">Capacité max *</label>
                    <input type="number" name="max_capacity" value="3" min="1" required class="modal-input">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="modal-label">Prix / nuit (FCFA) *</label>
                    <input type="number" name="base_price" placeholder="45000" min="0" required class="modal-input">
                </div>
                <div>
                    <label class="modal-label">Surface (m²)</label>
                    <input type="number" name="size_sqm" placeholder="25" min="0" class="modal-input">
                </div>
            </div>
            </div>
            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="document.getElementById('modal-create-type').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Créer le type
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Éditer type --}}
<div id="modal-edit-type" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-backdrop">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <h3 class="font-heading font-semibold text-primary">Modifier le type</h3>
            <button onclick="document.getElementById('modal-edit-type').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form id="form-edit-type" method="POST" action="" class="flex flex-col flex-1 min-h-0 overflow-hidden expect-popup">
            @csrf
            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="modal-label">Nom *</label>
                    <input type="text" id="edit-type-name" name="name" required class="modal-input">
                </div>
                <div>
                    <label class="modal-label">Code *</label>
                    <input type="text" id="edit-type-code" name="code" required class="modal-input">
                </div>
            </div>
            <div>
                <label class="modal-label">Description</label>
                <textarea id="edit-type-desc" name="description" rows="2" class="modal-input resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="modal-label">Capacité base *</label>
                    <input type="number" id="edit-type-base-cap" name="base_capacity" min="1" required class="modal-input">
                </div>
                <div>
                    <label class="modal-label">Capacité max *</label>
                    <input type="number" id="edit-type-max-cap" name="max_capacity" min="1" required class="modal-input">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="modal-label">Prix / nuit (FCFA) *</label>
                    <input type="number" id="edit-type-price" name="base_price" min="0" required class="modal-input">
                </div>
                <div>
                    <label class="modal-label">Surface (m²)</label>
                    <input type="number" id="edit-type-sqm" name="size_sqm" min="0" class="modal-input">
                </div>
            </div>
            </div>
            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="document.getElementById('modal-edit-type').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Scripts --}}
<script>
    // ✅ Utilise this (le bouton) au lieu de paramètres inline
    // → Évite les bugs avec les apostrophes dans les chaînes
    function openEditRoom(btn) {
        document.getElementById('form-edit-room').action = `/rooms/${btn.dataset.id}`;
        document.getElementById('edit-room-number').value = btn.dataset.number;
        document.getElementById('edit-room-floor').value = btn.dataset.floor || '';
        document.getElementById('edit-room-type').value = btn.dataset.type;
        document.getElementById('edit-room-view').value = btn.dataset.view || '';
        document.getElementById('modal-edit-room').classList.remove('hidden');
    }

    function openEditType(btn) {
        document.getElementById('form-edit-type').action = `/rooms/types/${btn.dataset.id}`;
        document.getElementById('edit-type-name').value = btn.dataset.name;
        document.getElementById('edit-type-code').value = btn.dataset.code;
        document.getElementById('edit-type-desc').value = btn.dataset.desc || '';
        document.getElementById('edit-type-base-cap').value = btn.dataset.baseCap;
        document.getElementById('edit-type-max-cap').value = btn.dataset.maxCap;
        document.getElementById('edit-type-price').value = btn.dataset.price;
        document.getElementById('edit-type-sqm').value = btn.dataset.sqm || '';
        document.getElementById('modal-edit-type').classList.remove('hidden');
    }

    // Recherche à la saisie avec debounce 400ms
    let searchTimer;
    document.getElementById('search-input').addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            this.closest('form').submit();
        }, 400);
    });

    // Fermer modal en cliquant sur le backdrop
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });
</script>

{{-- Lightbox --}}
<div x-data="lightbox()" x-show="open" x-cloak
     @open-lightbox.window="openLightbox($event.detail)"
     @keydown.escape.window="open = false"
     @keydown.arrow-left.window="prev()"
     @keydown.arrow-right.window="next()"
     class="fixed inset-0 z-[100] flex items-center justify-center"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="display:none;">
    <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative z-10 max-w-5xl max-h-[90vh] w-full mx-4">
        <img :src="images[current]" class="w-full max-h-[85vh] object-contain rounded-lg shadow-2xl" alt="Photo chambre">

        {{-- Compteur --}}
        <div class="absolute top-4 left-4 text-white/80 text-sm font-medium bg-black/40 backdrop-blur-sm px-3 py-1 rounded-full">
            <span x-text="(current + 1) + ' / ' + images.length"></span>
        </div>

        {{-- Fermer --}}
        <button @click="open = false" class="absolute top-4 right-4 w-10 h-10 bg-black/40 hover:bg-black/60 backdrop-blur-sm text-white rounded-full flex items-center justify-center transition-colors">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        {{-- Navigation --}}
        <template x-if="images.length > 1">
            <div>
                <button @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-black/40 hover:bg-black/60 backdrop-blur-sm text-white rounded-full flex items-center justify-center transition-colors">
                    <i data-lucide="chevron-left" class="w-6 h-6"></i>
                </button>
                <button @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-black/40 hover:bg-black/60 backdrop-blur-sm text-white rounded-full flex items-center justify-center transition-colors">
                    <i data-lucide="chevron-right" class="w-6 h-6"></i>
                </button>
            </div>
        </template>

        {{-- Vignettes --}}
        <div x-show="images.length > 1" class="flex justify-center gap-2 mt-4">
            <template x-for="(img, i) in images" :key="i">
                <button @click="current = i" class="w-16 h-12 rounded-lg overflow-hidden border-2 transition-all" :class="i === current ? 'border-white shadow-lg' : 'border-transparent opacity-60 hover:opacity-100'">
                    <img :src="img" class="w-full h-full object-cover">
                </button>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('multiImagePreview', () => ({
        previews: [],
        files: [],
        isDragging: false,

        handleFiles(event) {
            const newFiles = Array.from(event.target.files);
            this.addFiles(newFiles);
        },

        handleDrop(event) {
            this.isDragging = false;
            const newFiles = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'));
            this.addFiles(newFiles);
        },

        addFiles(newFiles) {
            const maxTotal = 4;
            const remaining = maxTotal - this.previews.length;
            const toAdd = newFiles.slice(0, remaining);

            toAdd.forEach(file => {
                if (file.size > 3 * 1024 * 1024) {
                    alert('Image trop volumineuse (max 3 Mo) : ' + file.name);
                    return;
                }
                this.files.push(file);
                this.previews.push(URL.createObjectURL(file));
            });

            // Rebuild file input
            this.$nextTick(() => {
                const dt = new DataTransfer();
                this.files.forEach(f => dt.items.add(f));
                this.$refs.fileInput.files = dt.files;
            });
        },

        removeImage(index) {
            this.previews.splice(index, 1);
            this.files.splice(index, 1);
            const dt = new DataTransfer();
            this.files.forEach(f => dt.items.add(f));
            this.$refs.fileInput.files = dt.files;
        }
    }));

    Alpine.data('lightbox', () => ({
        open: false,
        images: [],
        current: 0,

        openLightbox(detail) {
            this.images = detail.images;
            this.current = detail.index || 0;
            this.open = true;
            this.$nextTick(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
        },

        next() {
            if (this.open && this.images.length > 1) {
                this.current = (this.current + 1) % this.images.length;
            }
        },

        prev() {
            if (this.open && this.images.length > 1) {
                this.current = (this.current - 1 + this.images.length) % this.images.length;
            }
        }
    }));
});
</script>
@endpush

@endsection

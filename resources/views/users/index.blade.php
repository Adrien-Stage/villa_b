@extends('layouts.hotel')

@section('title', 'Utilisateurs')

@section('content')

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Gestion du staff</h1>
        <p class="text-sm text-primary/50 mt-0.5">
            {{ $stats['total'] }} membre{{ $stats['total'] > 1 ? 's' : '' }} du personnel
        </p>
    </div>

    @droit('users.creer')
        <button type="button"
            onclick="openCreateModal()"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
            <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
            Ajouter un membre
        </button>
    @enddroit
</div>

@php
    $viewMode = request('view', 'list');
@endphp

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@include('users.partials.mot-de-passe-provisoire')

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

<div class="grid grid-cols-3 gap-2 sm:gap-4 mb-5">
    <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 text-center">
        <p class="text-xl sm:text-2xl font-heading font-semibold text-primary">{{ $stats['total'] }}</p>
        <p class="text-xs text-primary/50 mt-1">Staff total</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 text-center">
        <p class="text-xl sm:text-2xl font-heading font-semibold text-green-600">{{ $stats['active'] }}</p>
        <p class="text-xs text-primary/50 mt-1">Comptes actifs</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 text-center">
        <p class="text-xl sm:text-2xl font-heading font-semibold text-red-500">{{ $stats['inactive'] }}</p>
        <p class="text-xs text-primary/50 mt-1">Comptes inactifs</p>
    </div>
</div>

<div class="flex flex-wrap items-center justify-between gap-4 mb-5">
    <div id="users-status-tabs" class="flex items-center gap-2">
        @php
            $statuses = [
                '' => 'Tous',
                'active' => 'Actifs',
                'inactive' => 'Inactifs',
            ];
        @endphp

        @foreach($statuses as $value => $label)
            <a href="{{ route('users.index', array_merge(request()->except('status', 'page'), $value ? ['status' => $value] : [])) }}"
                data-status="{{ $value }}"
                class="user-status-tab px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ request('status', '') === $value ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ $label }}
            </a>
        @endforeach

    </div>

    {{-- Les filtres passent à la ligne plutôt que de faire défiler la page. --}}
    <form id="users-filter-form" method="GET" action="{{ route('users.index') }}" class="flex min-w-0 flex-wrap items-center gap-2">
        <input type="hidden" name="status" id="filter-status-input" value="{{ request('status') }}">
        <input type="hidden" name="view" id="filter-view-input" value="{{ $viewMode }}">

        <select name="department_id" id="filter-department-select" aria-label="Filtrer par département"
            class="max-w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
            <option value="">Tous les départements</option>
            @foreach($departments as $d)
                <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>

        <select name="role" id="filter-role-select" aria-label="Filtrer par rôle"
            class="max-w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
            <option value="">Tous les rôles</option>
            @foreach($roles as $role)
                <option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>
            @endforeach
        </select>

        <div class="relative min-w-0 flex-1 sm:flex-none">
            <input type="text"
                id="search-input" aria-label="Rechercher un membre du personnel"
                name="search"
                value="{{ request('search') }}"
                placeholder="Nom, email, téléphone..."
                autocomplete="off"
                class="pl-9 pr-8 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-full sm:w-64 transition-all">
            <i data-lucide="search" id="search-icon" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30 pointer-events-none"></i>
            <span id="search-spinner" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                <svg class="animate-spin h-3.5 w-3.5 text-primary/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </span>
            <button type="button" id="search-clear-btn" aria-label="Effacer la recherche"
                class="{{ request('search') ? '' : 'hidden' }} absolute right-2.5 top-1/2 -translate-y-1/2 text-primary/30 hover:text-primary transition-colors p-0.5 rounded-full">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </button>
        </div>

        <div id="users-view-toggle" class="inline-flex rounded-lg border border-secondary/30 bg-white p-0.5">
            <a href="{{ route('users.index', array_merge(request()->except('view', 'page'), ['view' => 'list'])) }}"
                data-view="list"
                title="Vue liste"
                class="user-view-btn inline-flex items-center justify-center h-8 w-8 rounded-md {{ $viewMode === 'list' ? 'bg-primary text-white' : 'text-primary/60 hover:text-primary' }}">
                <i data-lucide="list" class="w-4 h-4"></i>
            </a>
            <a href="{{ route('users.index', array_merge(request()->except('view', 'page'), ['view' => 'cards'])) }}"
                data-view="cards"
                title="Vue cartes"
                class="user-view-btn inline-flex items-center justify-center h-8 w-8 rounded-md {{ $viewMode === 'cards' ? 'bg-primary text-white' : 'text-primary/60 hover:text-primary' }}">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
            </a>
        </div>
    </form>
</div>

<div id="users-table-container" class="transition-opacity duration-150">
@if($viewMode === 'list')
<x-table :rows="$staffUsers" inline="never" empty="Aucun membre du personnel trouvé." empty-icon="user-x" caption="Membres du personnel">
    <x-slot:head>
        <x-table.col>Collaborateur</x-table.col>
        <x-table.col hide="2xl">Contact</x-table.col>
        <x-table.col hide="3xl">Département</x-table.col>
        <x-table.col>Rôles</x-table.col>
        <x-table.col>État</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($staffUsers as $staff)
        <x-table.row :muted="! $staff->is_active">
            <x-table.cell>
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary" aria-hidden="true">
                        <span class="text-xs font-semibold text-white">{{ mb_strtoupper(mb_substr($staff->name, 0, 2)) }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-primary">{{ $staff->name }}</p>
                        <p class="text-xs text-primary/45">Créé le {{ $staff->created_at?->locale('fr')->isoFormat('D MMM YYYY') }}</p>
                    </div>
                </div>
            </x-table.cell>
            <x-table.cell hide="2xl">
                <p class="max-w-56 truncate text-xs text-primary/70">{{ $staff->email }}</p>
                <p class="text-xs text-primary/45">{{ $staff->phone ?: '—' }}</p>
            </x-table.cell>
            <x-table.cell hide="3xl">
                @if($staff->department)
                    <span class="inline-flex max-w-52 items-center gap-1.5 rounded-lg border border-secondary/20 bg-primary/5 px-2 py-0.5 text-xs font-semibold text-primary">
                        <i data-lucide="{{ $staff->department->icon ?? 'briefcase' }}" class="h-3.5 w-3.5 shrink-0 text-secondary" aria-hidden="true"></i>
                        <span class="truncate">{{ $staff->department->name }}</span>
                    </span>
                @else
                    <span class="text-xs italic text-primary/35">Non affecté</span>
                @endif
            </x-table.cell>
            <x-table.cell>
                <div class="flex max-w-64 flex-wrap gap-1">
                    @forelse($staff->roles as $r)
                        <span class="inline-flex items-center gap-1 rounded-full border border-secondary/20 bg-secondary/10 px-2 py-0.5 text-[11px] font-medium text-primary">
                            {{ $r->name }}
                            @if(($r->pivot->level ?: 'write') === 'read')
                                <i data-lucide="eye" class="h-2.5 w-2.5 text-primary/40" aria-label="Lecture seule"></i>
                            @endif
                        </span>
                    @empty
                        <span class="text-xs italic text-primary/35">Aucun rôle</span>
                    @endforelse
                </div>
            </x-table.cell>
            <x-table.cell nowrap>
                @if($staff->is_active)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500" aria-hidden="true"></span> Actif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-red-500" aria-hidden="true"></span> Inactif
                    </span>
                @endif
            </x-table.cell>
            <x-table.actions :label="'Actions pour '.$staff->name">
                <x-table.action :href="route('users.show', $staff)" icon="id-card">Fiche</x-table.action>
                @droit('users.modifier')
                    <x-table.action icon="pencil" onclick="openEditModal('{{ $staff->id }}')">Modifier</x-table.action>
                @enddroit
                @droit('users.resetPassword')
                    <x-table.action :action="route('users.resetPassword', $staff)" :fields="['view' => $viewMode]" icon="key-round"
                        :confirm="'Réinitialiser le mot de passe de '.$staff->name.' ? Un mot de passe provisoire vous sera donné ; ses sessions ouvertes seront fermées.'">Réinitialiser le mot de passe</x-table.action>
                @enddroit
                @include('users.partials.action-statut', ['staff' => $staff, 'champs' => ['view' => $viewMode]])
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
@else
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($staffUsers as $staff)
        <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/10">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-semibold">{{ mb_strtoupper(mb_substr($staff->name, 0, 2)) }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary truncate">{{ $staff->name }}</p>
                        <p class="text-xs text-primary/50 truncate">{{ $staff->email }}</p>
                        @if($staff->department)
                            <div class="mt-1">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-primary/5 text-primary border border-secondary/20">
                                    <i data-lucide="{{ $staff->department->icon ?? 'briefcase' }}" class="w-3 h-3 text-secondary"></i>
                                    <span>{{ $staff->department->name }}</span>
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
                @if($staff->is_active)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium border bg-green-50 text-green-700 border-green-200">Actif</span>
                @else
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium border bg-red-50 text-red-700 border-red-200">Inactif</span>
                @endif
            </div>

            <div class="space-y-2 mb-4">
                <div class="flex flex-wrap gap-1">
                    @forelse($staff->roles as $r)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium border bg-secondary/10 text-primary border-secondary/20">
                            {{ $r->name }}
                            @if(($r->pivot->level ?: 'write') === 'read')
                                <i data-lucide="eye" class="w-2.5 h-2.5 text-primary/40" title="Lecture seule"></i>
                            @endif
                        </span>
                    @empty
                        <span class="text-xs text-primary/70">{{ ucfirst(str_replace('_', ' ', $staff->role)) }}</span>
                    @endforelse
                </div>
                <p class="text-xs text-primary/50">Téléphone : {{ $staff->phone ?: '-' }}</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('users.show', $staff) }}"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border border-secondary/20 text-primary hover:bg-accent/20 transition-colors">
                    <i data-lucide="id-card" class="w-3.5 h-3.5"></i> Fiche
                </a>
                <x-menu-actions :label="'Actions pour '.$staff->name">
                    @droit('users.modifier')
                        <x-table.action icon="pencil" onclick="openEditModal('{{ $staff->id }}')">Modifier</x-table.action>
                    @enddroit
                    @droit('users.resetPassword')
                        <x-table.action :action="route('users.resetPassword', $staff)" :fields="['view' => $viewMode]" icon="key-round"
                            :confirm="'Réinitialiser le mot de passe de '.$staff->name.' ? Un mot de passe provisoire vous sera donné ; ses sessions ouvertes seront fermées.'">Réinitialiser le mot de passe</x-table.action>
                    @enddroit
                    @include('users.partials.action-statut', ['staff' => $staff, 'champs' => ['view' => $viewMode]])
                </x-menu-actions>
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-xl shadow-sm p-12 text-center text-primary/40">
            <i data-lucide="user-x" class="w-9 h-9 mx-auto mb-2"></i>
            Aucun membre du personnel trouvé.
        </div>
    @endforelse
</div>
@if($staffUsers->hasPages())
    <div class="mt-4">{{ $staffUsers->onEachSide(1)->links('components.table.pagination') }}</div>
@endif
@endif

{{-- Fenêtres de modification pour les membres affichés --}}
<div id="users-modals-container">
    @foreach($staffUsers as $staff)
        @include('users.partials.modal-edition', ['staff' => $staff, 'viewMode' => $viewMode])
    @endforeach
</div>
</div>

{{-- Modal create --}}
<x-modal id="create-user-modal" title="Nouveau membre du staff" max-width="max-w-2xl" formAction="{{ route('users.store') }}" closeAction="closeCreateModal()">
    <input type="hidden" name="form_type" value="create">
    <input type="hidden" name="view" value="{{ $viewMode }}">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="text-xs font-medium text-primary/70">Nom complet <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   placeholder="Ex: Clyde Fotso"
                   class="mt-1 w-full px-3 py-2 text-sm border @error('name') border-red-500 bg-red-50/20 @else border-secondary/30 @enderror rounded-lg focus:border-secondary outline-none transition-colors">
            @error('name')
                <p class="text-[11px] text-red-600 font-medium mt-1 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    {{ $message }}
                </p>
            @enderror
        </div>
        <div>
            <label class="text-xs font-medium text-primary/70">Email professionnel <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   placeholder="Ex: collaborateur@entreprise.com"
                   class="mt-1 w-full px-3 py-2 text-sm border @error('email') border-red-500 bg-red-50/20 @else border-secondary/30 @enderror rounded-lg focus:border-secondary outline-none transition-colors">
            @error('email')
                <p class="text-[11px] text-red-600 font-medium mt-1 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    {{ $message }}
                </p>
            @enderror
        </div>
    </div>

    <div>
        <label class="text-xs font-medium text-primary/70">Téléphone</label>
        <input type="text" name="phone" value="{{ old('phone') }}"
               placeholder="Ex: +237 690 00 00 00"
               class="mt-1 w-full px-3 py-2 text-sm border @error('phone') border-red-500 bg-red-50/20 @else border-secondary/30 @enderror rounded-lg focus:border-secondary outline-none transition-colors">
        @error('phone')
            <p class="text-[11px] text-red-600 font-medium mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="create-user-dept-select" class="text-xs font-semibold text-primary">Département d'affectation</label>
        <select name="department_id" id="create-user-dept-select"
                onchange="onUserDepartmentSelect(this.value, 'create')"
                class="mt-1 w-full px-3 py-2 text-sm border @error('department_id') border-red-500 bg-red-50/20 @else border-secondary/30 @enderror rounded-lg focus:border-secondary outline-none bg-white">
            <option value="">-- Aucun / Sélection manuelle --</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id)>{{ $dept->name }} ({{ $dept->code ?: 'N/A' }})</option>
            @endforeach
        </select>
        @error('department_id')
            <p class="text-[11px] text-red-600 font-medium mt-1">{{ $message }}</p>
        @enderror
        <p class="text-[11px] text-primary/50 mt-1">
            ⭐ Sélectionner un département pré-coche automatiquement ses rôles &amp; modules ci-dessous.
        </p>
    </div>

    <div>
        <label for="create-user-regime-select" class="text-xs font-semibold text-primary">Régime horaire <span class="font-normal text-primary/55">(optionnel — exception individuelle)</span></label>
        <select name="work_regime_id" id="create-user-regime-select"
                class="mt-1 w-full px-3 py-2 text-sm border @error('work_regime_id') border-red-500 bg-red-50/20 @else border-secondary/30 @enderror rounded-lg focus:border-secondary outline-none bg-white">
            <option value="">— Suivre le régime du département —</option>
            @foreach(($workRegimes ?? collect()) as $regime)
                <option value="{{ $regime->id }}" @selected((string) old('work_regime_id') === (string) $regime->id)>
                    {{ $regime->name }} ({{ $regime->estFixe() ? 'Fixe · ' . $regime->workingDaysLabel() : 'Quarts' }})
                </option>
            @endforeach
        </select>
        @error('work_regime_id')
            <p class="text-[11px] text-red-600 font-medium mt-1">{{ $message }}</p>
        @enderror
        <p data-regime-hint-context="create" class="text-[11px] text-primary/50 mt-1">
            Par défaut : régime du département choisi.
        </p>
    </div>

    @include('users.partials.restaurants', ['contexte' => 'create', 'departements' => $departments, 'personne' => null])

    <div>
        <label class="text-xs font-medium text-primary/70">Rôles & niveau d'accès <span class="text-red-500">*</span></label>
        <p class="text-[11px] text-primary/40 mb-2">Cochez un ou plusieurs rôles. Chaque rôle donne accès à son module ; choisissez le niveau (lecture ou lecture / écriture).</p>
        @error('roles')
            <div class="mb-2 p-2.5 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ $message }}</span>
            </div>
        @enderror
        <x-role-picker :rolesByModule="$rolesByModule" :moduleLabels="$moduleLabels"
                       :selected="old('roles', [])" :levels="old('levels', [])" context="create" />
    </div>

    @include('users.partials.derogation')


    <div class="rounded-xl border border-secondary/30 bg-accent/10 p-3.5 text-xs text-primary/80 flex items-start gap-3">
        <svg class="w-5 h-5 text-secondary shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
            <p class="font-semibold text-primary">Génération automatique du mot de passe</p>
            <p class="text-[11px] text-primary/70 mt-0.5">Un mot de passe par défaut sécurisé sera automatiquement attribué et transmis par e-mail avec ses identifiants. L'utilisateur aura pour consigne de le modifier obligatoirement dès sa première connexion.</p>
        </div>
    </div>

    <label class="inline-flex items-center gap-2 text-xs text-primary/70">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
        Compte actif
    </label>

    <x-slot:footer>
        <button type="button" onclick="closeCreateModal()" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Créer</button>
    </x-slot:footer>
</x-modal>

@include('users.partials.script-formulaire')

<script>
let searchTimer = null;
let searchAbortController = null;

const searchInput = document.getElementById('search-input');
const searchSpinner = document.getElementById('search-spinner');
const searchClearBtn = document.getElementById('search-clear-btn');
const filterForm = document.getElementById('users-filter-form');
const listContainer = document.getElementById('users-table-container');
const deptSelect = document.getElementById('filter-department-select');
const roleSelect = document.getElementById('filter-role-select');
const statusInput = document.getElementById('filter-status-input');
const viewInput = document.getElementById('filter-view-input');
const statusTabs = document.getElementById('users-status-tabs');
const viewToggle = document.getElementById('users-view-toggle');

function construireUrlRecherche(overrides = {}) {
    const params = new URLSearchParams();
    if (filterForm) {
        const formData = new FormData(filterForm);
        for (const [key, value] of formData.entries()) {
            if (value !== '' && value !== null && value !== undefined) {
                params.set(key, value);
            }
        }
    }
    for (const [key, value] of Object.entries(overrides)) {
        if (value === null || value === '' || value === undefined) {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    }
    const base = filterForm ? filterForm.action : window.location.pathname;
    const qs = params.toString();
    return qs ? `${base}?${qs}` : base;
}

function updateClearBtn() {
    if (searchClearBtn && searchInput) {
        if (searchInput.value.trim().length > 0) {
            searchClearBtn.classList.remove('hidden');
        } else {
            searchClearBtn.classList.add('hidden');
        }
    }
}

async function executerRecherche(url, updateHistory = true) {
    if (searchAbortController) {
        searchAbortController.abort();
    }
    searchAbortController = new AbortController();

    if (searchSpinner) searchSpinner.classList.remove('hidden');
    if (searchClearBtn) searchClearBtn.classList.add('hidden');
    if (listContainer) listContainer.classList.add('opacity-50', 'pointer-events-none');

    try {
        const response = await fetch(url, {
            signal: searchAbortController.signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        });

        if (!response.ok) {
            window.location.href = url;
            return;
        }

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');

        const newContainer = doc.getElementById('users-table-container');
        if (newContainer && listContainer) {
            listContainer.innerHTML = newContainer.innerHTML;
        }

        // Mettre à jour les onglets de statut
        const newStatusTabs = doc.getElementById('users-status-tabs');
        if (newStatusTabs && statusTabs) {
            statusTabs.innerHTML = newStatusTabs.innerHTML;
        }

        // Mettre à jour les boutons d'affichage (liste / cartes)
        const newViewToggle = doc.getElementById('users-view-toggle');
        if (newViewToggle && viewToggle) {
            viewToggle.innerHTML = newViewToggle.innerHTML;
        }

        // Mettre à jour l'URL sans rechargement de page
        if (updateHistory) {
            window.history.replaceState(null, '', url);
        }

        // Rafraîchir les icônes Lucide
        if (typeof window.refreshLucideIcons === 'function') {
            window.refreshLucideIcons();
        } else if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        // Réinitialiser Alpine sur les nouveaux éléments injectés (ex: menus d'actions)
        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
            window.Alpine.initTree(listContainer);
        }

    } catch (e) {
        if (e.name !== 'AbortError') {
            console.error('Erreur lors du filtrage asynchrone des utilisateurs:', e);
            window.location.href = url;
        }
    } finally {
        if (searchSpinner) searchSpinner.classList.add('hidden');
        updateClearBtn();
        if (listContainer) listContainer.classList.remove('opacity-50', 'pointer-events-none');
    }
}

if (searchInput) {
    searchInput.addEventListener('input', function() {
        updateClearBtn();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            const url = construireUrlRecherche({ page: null });
            executerRecherche(url);
        }, 250);
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimer);
            const url = construireUrlRecherche({ page: null });
            executerRecherche(url);
        } else if (e.key === 'Escape') {
            if (this.value) {
                this.value = '';
                updateClearBtn();
                clearTimeout(searchTimer);
                const url = construireUrlRecherche({ page: null });
                executerRecherche(url);
            }
        }
    });
}

if (searchClearBtn) {
    searchClearBtn.addEventListener('click', function() {
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
            updateClearBtn();
            clearTimeout(searchTimer);
            const url = construireUrlRecherche({ page: null });
            executerRecherche(url);
        }
    });
}

if (filterForm) {
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        clearTimeout(searchTimer);
        const url = construireUrlRecherche({ page: null });
        executerRecherche(url);
    });
}

[deptSelect, roleSelect].forEach(select => {
    if (!select) return;
    select.addEventListener('change', function() {
        clearTimeout(searchTimer);
        const url = construireUrlRecherche({ page: null });
        executerRecherche(url);
    });
});

// Interception des clics sur les onglets de statut (Tous / Actifs / Inactifs)
if (statusTabs) {
    statusTabs.addEventListener('click', function(e) {
        const link = e.target.closest('a.user-status-tab');
        if (!link) return;
        e.preventDefault();
        const statusVal = link.dataset.status ?? '';
        if (statusInput) statusInput.value = statusVal;
        const url = construireUrlRecherche({ status: statusVal, page: null });
        executerRecherche(url);
    });
}

// Interception des clics sur les boutons de mode de vue (liste / cartes)
if (viewToggle) {
    viewToggle.addEventListener('click', function(e) {
        const link = e.target.closest('a.user-view-btn');
        if (!link) return;
        e.preventDefault();
        const viewVal = link.dataset.view ?? 'list';
        if (viewInput) viewInput.value = viewVal;
        const url = construireUrlRecherche({ view: viewVal });
        executerRecherche(url);
    });
}

// Interception des clics sur la pagination du tableau/cartes
if (listContainer) {
    listContainer.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href) return;
        try {
            const urlObj = new URL(href, window.location.origin);
            if (urlObj.searchParams.has('page')) {
                e.preventDefault();
                executerRecherche(href);
                listContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch (_) {}
    });
}

window.addEventListener('popstate', function() {
    executerRecherche(window.location.href, false);
});

window.openCreateModal = function() {
    const modal = document.getElementById('create-user-modal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        const deptSelectModal = document.getElementById('create-user-dept-select');
        if (deptSelectModal && deptSelectModal.value) {
            window.onUserDepartmentSelect(deptSelectModal.value, 'create');
        }
    }
};

window.closeCreateModal = function() {
    const modal = document.getElementById('create-user-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

@if($errors->any() && (old('form_type') === 'create' || !old('staff_id')))
    document.addEventListener('DOMContentLoaded', () => {
        openCreateModal();
    });
@endif
</script>

@endsection

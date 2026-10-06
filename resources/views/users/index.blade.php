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

    <button type="button"
        onclick="openCreateModal()"
        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
        <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
        Ajouter un membre
    </button>
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
    <div class="flex items-center gap-2">
        @php
            $statuses = [
                '' => 'Tous',
                'active' => 'Actifs',
                'inactive' => 'Inactifs',
            ];
        @endphp

        @foreach($statuses as $value => $label)
            <a href="{{ route('users.index', array_merge(request()->except('status', 'page'), $value ? ['status' => $value] : [])) }}"
                class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ request('status', '') === $value ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ $label }}
            </a>
        @endforeach

    </div>

    {{-- Les filtres passent à la ligne plutôt que de faire défiler la page. --}}
    <form method="GET" action="{{ route('users.index') }}" class="flex min-w-0 flex-wrap items-center gap-2">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="hidden" name="view" value="{{ $viewMode }}">

        <select name="department_id" aria-label="Filtrer par département"
            onchange="this.form.submit()"
            class="max-w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
            <option value="">Tous les départements</option>
            @foreach($departments as $d)
                <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>

        <select name="role" aria-label="Filtrer par rôle"
            onchange="this.form.submit()"
            class="max-w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
            <option value="">Tous les roles</option>
            @foreach($roles as $role)
                <option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>
            @endforeach
        </select>

        <div class="relative min-w-0 flex-1 sm:flex-none">
            <input type="text"
                id="search-input" aria-label="Rechercher un membre du personnel"
                name="search"
                value="{{ request('search') }}"
                placeholder="Nom, email, telephone..."
                autocomplete="off"
                class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-full sm:w-64 transition-all">
            <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
        </div>

        <div class="inline-flex rounded-lg border border-secondary/30 bg-white p-0.5">
            <a href="{{ route('users.index', array_merge(request()->except('view', 'page'), ['view' => 'list'])) }}"
                title="Vue liste"
                class="inline-flex items-center justify-center h-8 w-8 rounded-md {{ $viewMode === 'list' ? 'bg-primary text-white' : 'text-primary/60 hover:text-primary' }}">
                <i data-lucide="list" class="w-4 h-4"></i>
            </a>
            <a href="{{ route('users.index', array_merge(request()->except('view', 'page'), ['view' => 'cards'])) }}"
                title="Vue cartes"
                class="inline-flex items-center justify-center h-8 w-8 rounded-md {{ $viewMode === 'cards' ? 'bg-primary text-white' : 'text-primary/60 hover:text-primary' }}">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
            </a>
        </div>
    </form>
</div>

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
                        <span class="text-xs font-semibold text-white">{{ strtoupper(substr($staff->name, 0, 2)) }}</span>
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
                        <span class="text-white text-xs font-semibold">{{ strtoupper(substr($staff->name, 0, 2)) }}</span>
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
            Aucun membre du staff trouve
        </div>
    @endforelse
</div>
@if($staffUsers->hasPages())
    <div class="mt-4">{{ $staffUsers->onEachSide(1)->links('components.table.pagination') }}</div>
@endif
@endif

{{-- Modal create --}}
<x-modal id="create-user-modal" title="Nouveau membre du staff" max-width="max-w-2xl" formAction="{{ route('users.store') }}" closeAction="closeCreateModal()">
    <input type="hidden" name="form_type" value="create">
    <input type="hidden" name="view" value="{{ $viewMode }}">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="text-xs text-primary/60">Nom complet</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
        <div>
            <label class="text-xs text-primary/60">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
    </div>

    <div>
        <label class="text-xs text-primary/60">Téléphone</label>
        <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
    </div>

    <div>
        <label class="text-xs font-semibold text-primary">Département d'affectation</label>
        <select name="department_id" id="create-user-dept-select"
                onchange="onUserDepartmentSelect(this.value, 'create')"
                class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none bg-white">
            <option value="">-- Aucun / Sélection manuelle --</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id)>{{ $dept->name }} ({{ $dept->code ?: 'N/A' }})</option>
            @endforeach
        </select>
        <p class="text-[11px] text-primary/50 mt-1">
            ⭐ Sélectionner un département pré-coche automatiquement ses rôles & modules ci-dessous.
        </p>
    </div>

    @include('users.partials.restaurants', ['contexte' => 'create', 'departements' => $departments, 'personne' => null])

    <div>
        <label class="text-xs text-primary/60">Rôles & niveau d'accès <span class="text-red-500">*</span></label>
        <p class="text-[11px] text-primary/40 mb-2">Cochez un ou plusieurs rôles. Chaque rôle donne accès à son module ; choisissez le niveau (lecture ou lecture / écriture).</p>
        <x-role-picker :rolesByModule="$rolesByModule" :moduleLabels="$moduleLabels"
                       :selected="old('roles', [])" :levels="old('levels', [])" context="create" />
    </div>

    @include('users.partials.derogation')


    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="text-xs text-primary/60">Mot de passe</label>
            <input type="password" name="password" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
        <div>
            <label class="text-xs text-primary/60">Confirmation mot de passe</label>
            <input type="password" name="password_confirmation" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
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

{{-- Fenêtres de modification --}}
@foreach($staffUsers as $staff)
    @include('users.partials.modal-edition', ['staff' => $staff, 'viewMode' => $viewMode])
@endforeach

@include('users.partials.script-formulaire')

<script>
let searchTimer;
const searchInput = document.getElementById('search-input');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => this.closest('form').submit(), 400);
    });
}

window.openCreateModal = function() {
    document.getElementById('create-user-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    const deptSelect = document.getElementById('create-user-dept-select');
    if (deptSelect && deptSelect.value) {
        window.onUserDepartmentSelect(deptSelect.value, 'create');
    }
};

window.closeCreateModal = function() {
    document.getElementById('create-user-modal').classList.add('hidden');
    document.body.style.overflow = '';
};

@if($errors->any() && old('form_type') === 'create')
    openCreateModal();
@endif
</script>

@endsection

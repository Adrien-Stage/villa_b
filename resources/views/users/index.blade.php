@extends('layouts.hotel')

@section('title', 'Utilisateurs')

@section('content')

<div class="flex items-start justify-between mb-6">
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

<div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-2xl font-heading font-semibold text-primary">{{ $stats['total'] }}</p>
        <p class="text-xs text-primary/50 mt-1">Staff total</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-2xl font-heading font-semibold text-green-600">{{ $stats['active'] }}</p>
        <p class="text-xs text-primary/50 mt-1">Comptes actifs</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
        <p class="text-2xl font-heading font-semibold text-red-500">{{ $stats['inactive'] }}</p>
        <p class="text-xs text-primary/50 mt-1">Comptes inactifs</p>
    </div>
</div>

<div class="flex items-center justify-between gap-4 mb-5">
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

    <form method="GET" action="{{ route('users.index') }}" class="flex items-center gap-2">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="hidden" name="view" value="{{ $viewMode }}">

        <select name="role"
            onchange="this.form.submit()"
            class="px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
            <option value="">Tous les roles</option>
            @foreach($roles as $role)
                <option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>
            @endforeach
        </select>

        <div class="relative">
            <input type="text"
                id="search-input"
                name="search"
                value="{{ request('search') }}"
                placeholder="Nom, email, telephone..."
                autocomplete="off"
                class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-64 transition-all">
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
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    @if($staffUsers->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-primary/30">
            <i data-lucide="user-x" class="w-10 h-10 mb-3 opacity-40"></i>
            <p class="text-sm">Aucun membre du staff trouve</p>
        </div>
    @else
        <div class="grid grid-cols-12 gap-4 px-5 py-3 border-b border-secondary/10 bg-accent/20">
            <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40">Collaborateur</div>
            <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40">Contact</div>
            <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Role</div>
            <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Etat</div>
            <div class="col-span-2"></div>
        </div>

        @foreach($staffUsers as $staff)
            <div class="grid grid-cols-12 gap-4 px-5 py-3.5 border-b border-secondary/10 items-center">
                <div class="col-span-3 flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-semibold">
                            {{ strtoupper(substr($staff->name, 0, 2)) }}
                        </span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-primary truncate">{{ $staff->name }}</p>
                        <p class="text-xs text-primary/40">
                            Cree le {{ $staff->created_at?->locale('fr')->isoFormat('D MMM YYYY') }}
                        </p>
                    </div>
                </div>

                <div class="col-span-3">
                    <p class="text-xs text-primary/70 truncate">{{ $staff->email }}</p>
                    <p class="text-xs text-primary/40">{{ $staff->phone ?: '-' }}</p>
                </div>

                <div class="col-span-2">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium border bg-secondary/10 text-primary border-secondary/20">
                        {{ $roles->firstWhere('slug', $staff->role)?->name ?? ucfirst(str_replace('_', ' ', $staff->role)) }}
                    </span>
                </div>

                <div class="col-span-2">
                    @if($staff->is_active)
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium border bg-green-50 text-green-700 border-green-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Actif
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium border bg-red-50 text-red-700 border-red-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inactif
                        </span>
                    @endif
                </div>

                <div class="col-span-2 flex items-center justify-end gap-2">
                    <button type="button"
                        onclick="openEditModal('{{ $staff->id }}')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium border border-secondary/20 text-primary hover:bg-accent/20 transition-colors">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Editer
                    </button>

                    <form method="POST" action="{{ route('users.toggleStatus', $staff) }}">
                        @csrf
                        <input type="hidden" name="view" value="{{ $viewMode }}">
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium border {{ $staff->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' }} transition-colors">
                            @if($staff->is_active)
                                <i data-lucide="user-x" class="w-3.5 h-3.5"></i> Desactiver
                            @else
                                <i data-lucide="user-check" class="w-3.5 h-3.5"></i> Reactiver
                            @endif
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($staffUsers as $staff)
        <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/10">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-semibold">{{ strtoupper(substr($staff->name, 0, 2)) }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary truncate">{{ $staff->name }}</p>
                        <p class="text-xs text-primary/50 truncate">{{ $staff->email }}</p>
                    </div>
                </div>
                @if($staff->is_active)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium border bg-green-50 text-green-700 border-green-200">Actif</span>
                @else
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium border bg-red-50 text-red-700 border-red-200">Inactif</span>
                @endif
            </div>

            <div class="space-y-1 mb-4">
                <p class="text-xs text-primary/70">Role: <span class="font-medium">{{ $roles->firstWhere('slug', $staff->role)?->name ?? ucfirst(str_replace('_', ' ', $staff->role)) }}</span></p>
                <p class="text-xs text-primary/50">Telephone: {{ $staff->phone ?: '-' }}</p>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                    onclick="openEditModal('{{ $staff->id }}')"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border border-secondary/20 text-primary hover:bg-accent/20 transition-colors">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Editer
                </button>
                <form method="POST" action="{{ route('users.toggleStatus', $staff) }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="view" value="{{ $viewMode }}">
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border {{ $staff->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' }} transition-colors">
                        @if($staff->is_active)
                            <i data-lucide="user-x" class="w-3.5 h-3.5"></i> Desactiver
                        @else
                            <i data-lucide="user-check" class="w-3.5 h-3.5"></i> Reactiver
                        @endif
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-xl shadow-sm p-12 text-center text-primary/40">
            <i data-lucide="user-x" class="w-9 h-9 mx-auto mb-2"></i>
            Aucun membre du staff trouve
        </div>
    @endforelse
</div>
@endif

@if($staffUsers->hasPages())
    <div class="mt-4">{{ $staffUsers->links() }}</div>
@endif

{{-- Modal create --}}
<x-modal id="create-user-modal" title="Nouveau membre du staff" max-width="max-w-2xl" formAction="{{ route('users.store') }}" closeAction="closeCreateModal()">
    <input type="hidden" name="form_type" value="create">
    <input type="hidden" name="view" value="{{ $viewMode }}">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="text-xs text-primary/60">Nom complet</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
        <div>
            <label class="text-xs text-primary/60">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="text-xs text-primary/60">Telephone</label>
            <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
        <div>
            <label class="text-xs text-primary/60">Role</label>
            <select name="role" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}" @selected(old('role') === $role->slug)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
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
        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Creer</button>
    </x-slot:footer>
</x-modal>

{{-- Edit modals --}}
@foreach($staffUsers as $staff)
    <x-modal id="edit-user-modal-{{ $staff->id }}" title="Modifier {{ $staff->name }}" max-width="max-w-2xl" formAction="{{ route('users.update', $staff) }}" closeAction="closeEditModal('{{ $staff->id }}')">
        @method('PUT')
        <input type="hidden" name="form_type" value="edit_{{ $staff->id }}">
        <input type="hidden" name="view" value="{{ $viewMode }}">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-primary/60">Nom complet</label>
                <input type="text" name="name" value="{{ old('name', $staff->name) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
            <div>
                <label class="text-xs text-primary/60">Email</label>
                <input type="email" name="email" value="{{ old('email', $staff->email) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-primary/60">Telephone</label>
                <input type="text" name="phone" value="{{ old('phone', $staff->phone) }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
            <div>
                <label class="text-xs text-primary/60">Role</label>
                <select name="role" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white focus:border-secondary outline-none">
                    @foreach($roles as $role)
                        <option value="{{ $role->slug }}" @selected(old('role', $staff->role) === $role->slug)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-primary/60">Nouveau mot de passe (optionnel)</label>
                <input type="password" name="password" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
            <div>
                <label class="text-xs text-primary/60">Confirmation nouveau mot de passe</label>
                <input type="password" name="password_confirmation" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
            </div>
        </div>

        <label class="inline-flex items-center gap-2 text-xs text-primary/70">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active))>
            Compte actif
        </label>

        <x-slot:footer>
            <button type="button" onclick="closeEditModal('{{ $staff->id }}')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
        </x-slot:footer>
    </x-modal>
@endforeach

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
};

window.closeCreateModal = function() {
    document.getElementById('create-user-modal').classList.add('hidden');
    document.body.style.overflow = '';
};

window.openEditModal = function(userId) {
    const modal = document.getElementById(`edit-user-modal-${userId}`);
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeEditModal = function(userId) {
    const modal = document.getElementById(`edit-user-modal-${userId}`);
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

@if($errors->any())
    @if(old('form_type') === 'create')
        openCreateModal();
    @elseif(old('form_type') && str_starts_with(old('form_type'), 'edit_'))
        openEditModal('{{ str_replace('edit_', '', old('form_type')) }}');
    @endif
@endif
</script>

@endsection

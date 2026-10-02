@extends('layouts.hotel')

@section('title', $membre->name)

@section('content')

@php
    $libelleRole = fn (string $slug) => \App\Support\RoleCatalog::find($slug)['name'] ?? $slug;
    $peutExcepter = ! $membre->isAdmin() && ! $membre->isSupport()
        && app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'droits.exceptions.creer');
@endphp

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('users.index') }}" class="text-xs text-primary/50 hover:text-primary">&larr; Personnel</a>
        <h1 class="font-heading text-2xl font-semibold text-primary mt-1">{{ $membre->name }}</h1>
        <p class="text-sm text-primary/50">{{ $membre->email }}{{ $membre->department ? ' · ' . $membre->department->name : '' }}</p>
    </div>
    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $membre->is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
        {{ $membre->is_active ? 'Compte actif' : 'Compte désactivé' }}
    </span>
</div>

@foreach(['success' => 'border-green-200 bg-green-50 text-green-700', 'error' => 'border-red-200 bg-red-50 text-red-700'] as $cle => $classes)
    @if(session($cle))<div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($cle) }}</div>@endif
@endforeach
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside">@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="font-heading text-lg font-semibold text-primary">Rôles</h2>
            <ul class="mt-3 space-y-2">
                @forelse($membre->roles as $role)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-primary">{{ $role->name }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ ($role->pivot->level ?? null) === 'read' ? 'bg-accent/40 text-primary/70' : 'bg-green-50 text-green-700' }}">
                            {{ ($role->pivot->level ?? null) === 'read' ? 'Lecture seule' : 'Plein exercice' }}
                        </span>
                    </li>
                @empty
                    <li class="text-sm text-primary/60">{{ $membre->role ? 'Rôle hérité : ' . $libelleRole($membre->role) : 'Aucun rôle : ce compte ne détient aucun droit.' }}</li>
                @endforelse
            </ul>
            @if($cumuls !== [])
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                    <p class="font-semibold">Cumul de fonctions incompatibles</p>
                    @foreach($cumuls as $c)<p class="mt-1">{{ implode(' × ', array_map($libelleRole, $c['roles'])) }} — {{ $c['motif'] }}</p>@endforeach
                </div>
            @endif
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="font-heading text-lg font-semibold text-primary">Ce que le moteur lui accorde</h2>
            <p class="mt-1 text-xs text-primary/50">Modèle de ses rôles, couches de la console et de l'hôtel, exceptions nominatives : le résultat, droit par droit.</p>
            <div class="mt-3 space-y-2">
                @forelse($droitsParModule as $module => $droits)
                    <details class="rounded-lg border border-secondary/20">
                        <summary class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase tracking-wider text-primary">
                            {{ $module }} <span class="font-normal normal-case text-primary/40">{{ count($droits) }} droit(s), dont {{ count(array_filter($droits, fn ($d) => isset($ecritures[$d]))) }} en écriture</span>
                        </summary>
                        <div class="flex flex-wrap gap-1 px-3 pb-3">
                            @foreach($droits as $droit)
                                <span class="rounded px-1.5 py-0.5 font-mono text-[10px] {{ isset($ecritures[$droit]) ? 'bg-amber-50 text-amber-800' : 'bg-accent/30 text-primary/70' }}">{{ $droit }}</span>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <p class="text-sm text-primary/60">Aucun droit.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="space-y-5">
        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="font-heading text-lg font-semibold text-primary">Périmètre</h2>
            <dl class="mt-3 space-y-1 text-xs">
                @foreach($portees as $droit => $libelle)
                    <div class="flex justify-between gap-2"><dt class="font-mono text-primary/60">{{ $droit }}</dt><dd class="text-primary">{{ $libelle }}</dd></div>
                @endforeach
                <div class="flex justify-between gap-2"><dt class="text-primary/60">Dernière connexion</dt><dd class="text-primary">{{ $membre->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="font-heading text-lg font-semibold text-primary">Exceptions</h2>
            <ul class="mt-3 space-y-2 text-xs">
                @forelse($exceptions as $e)
                    <li class="rounded-lg border border-secondary/20 px-3 py-2">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-mono text-primary">{{ $e->permission }}</span>
                            <span class="font-semibold {{ $e->effect === 'deny' ? 'text-red-700' : 'text-green-700' }}">{{ $e->effect === 'deny' ? 'Refus' : 'Autorisation' }}</span>
                        </div>
                        <p class="mt-1 text-primary/60">{{ $e->reason }}{{ $e->expires_at ? ' — jusqu\'au ' . $e->expires_at->format('d/m/Y') : '' }}</p>
                        @if($e->origin === 'etablissement')
                            @droit('droits.exceptions.supprimer')
                                <form method="POST" action="{{ route('droits.exceptions.destroy', $e) }}" class="mt-1" onsubmit="return confirm('Retirer cette exception ?');">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="retour" value="fiche">
                                    <button type="submit" class="text-[11px] font-semibold text-red-700 hover:underline">Retirer</button>
                                </form>
                            @enddroit
                        @endif
                    </li>
                @empty
                    <li class="text-primary/60">Aucune exception.</li>
                @endforelse
                @foreach($restrictions as $r)
                    <li class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">
                        Service <span class="font-mono">{{ $r->module_key }}</span> : {{ $r->access_level === 'none' ? 'exclu' : 'lecture seule' }} (restriction héritée de la console)
                    </li>
                @endforeach
            </ul>

            @if($peutExcepter)
                <form method="POST" action="{{ route('droits.exceptions.store') }}" class="mt-4 space-y-2 border-t border-secondary/15 pt-4">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $membre->id }}">
                    <input type="hidden" name="retour" value="fiche">
                    <label for="f-droit" class="block text-xs font-semibold text-primary/70">Nouvelle exception</label>
                    <input id="f-droit" name="permission" list="f-droits" required placeholder="Droit" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 font-mono text-xs">
                    <datalist id="f-droits">@foreach($catalogue as $d)<option value="{{ $d }}">@endforeach</datalist>
                    <select name="effect" aria-label="Effet" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
                        <option value="deny">Refus</option>
                        <option value="allow">Autorisation</option>
                    </select>
                    <input name="reason" required maxlength="255" placeholder="Motif" aria-label="Motif" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
                    <input type="date" name="expires_at" aria-label="Jusqu'au" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
                    <label class="flex items-center gap-1.5 text-[11px] text-primary/70"><input type="checkbox" name="derogation" value="1" class="rounded border-secondary/40"> Dérogation à la séparation des tâches</label>
                    <button type="submit" class="w-full rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white">Enregistrer l'exception</button>
                </form>
            @endif
        </section>
    </div>
</div>

@endsection

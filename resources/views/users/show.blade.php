@extends('layouts.hotel')

@section('title', $membre->name)

@section('content')

@php
    $libelleRole = fn (string $slug) => \App\Support\RoleCatalog::find($slug)['name'] ?? $slug;
    $peutExcepter = ! $membre->isAdmin() && ! $membre->isSupport()
        && app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'droits.exceptions.creer');
    $peutLever = app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'droits.exceptions.supprimer');
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
                    <li class="text-sm text-primary/60">Aucun rôle : ce compte ne détient aucun droit.</li>
                @endforelse
            </ul>
            @if($cumuls !== [])
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                    <p class="font-semibold">Cumul de fonctions incompatibles</p>
                    @foreach($cumuls as $c)<p class="mt-1">{{ implode(' × ', array_map($libelleRole, $c['roles'])) }} — {{ $c['motif'] }}</p>@endforeach
                </div>
            @endif
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm" x-data="{ choix: @js(old('permissions', [])) }">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h2 class="font-heading text-lg font-semibold text-primary">Ses accès</h2>
                <p class="flex flex-wrap items-center gap-2 text-[11px] text-primary/60" aria-hidden="true">
                    <span class="rounded border border-secondary/20 bg-accent/20 px-1.5">consulte</span>
                    <span class="rounded border border-amber-200 bg-amber-50 px-1.5 text-amber-900">modifie</span>
                    <span class="rounded border border-red-200 bg-red-50 px-1.5 text-red-700 line-through">retiré</span>
                </p>
            </div>
            <p class="mt-1 text-xs text-primary/60">
                @if($peutExcepter)
                    Ses rôles lui ouvrent ces accès. Pour en retirer à cette personne seulement, cochez-les puis donnez un motif :
                    ses rôles ne changent pas, ses collègues non plus. Un accès retiré se rétablit d'un clic.
                @else
                    Ce que ses rôles et ses exceptions lui ouvrent, écran par écran.
                @endif
            </p>

            <form method="POST" action="{{ route('droits.exceptions.store') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $membre->id }}">
                <input type="hidden" name="effect" value="deny">
                <input type="hidden" name="retour" value="fiche">

                <div class="mt-3 space-y-2">
                    @forelse($accesRanges as $module => $ecrans)
                        @php
                            $droitsModule = array_merge(...array_values($ecrans));
                            $accordes = array_values(array_filter($droitsModule, fn ($d) => $acces[$d]['etat'] === 'accorde'));
                            $quiModifient = array_values(array_filter($accordes, fn ($d) => isset($ecritures[$d])));
                            $retires = count($droitsModule) - count($accordes);
                        @endphp
                        <details class="rounded-lg border border-secondary/20" @if($retires) open @endif>
                            <summary class="flex cursor-pointer flex-wrap items-baseline gap-x-2 px-3 py-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-primary">{{ $module }}</span>
                                <span class="text-[11px] text-primary/45">
                                    {{ count($accordes) }} accès, dont {{ count($quiModifient) }} qui modifient
                                    @if($retires)· <span class="font-semibold text-red-700">{{ $retires }} retiré(s)</span>@endif
                                </span>
                            </summary>
                            <div class="border-t border-secondary/10 px-3 pb-3 pt-2">
                                @if($peutExcepter && $accordes !== [])
                                    <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                                        <span class="text-primary/50">Sélectionner pour retrait :</span>
                                        @if($quiModifient !== [])
                                            <button type="button" class="font-semibold text-primary underline-offset-2 hover:underline"
                                                    @click="choix = [...new Set([...choix, ...@js($quiModifient)])]">ce qui modifie (lecture seule)</button>
                                        @endif
                                        <button type="button" class="font-semibold text-primary underline-offset-2 hover:underline"
                                                @click="choix = [...new Set([...choix, ...@js($accordes)])]">tout le service</button>
                                        <button type="button" class="text-primary/60 underline-offset-2 hover:underline"
                                                @click="choix = choix.filter(d => ! @js($accordes).includes(d))">rien</button>
                                    </div>
                                @endif
                                <dl class="divide-y divide-secondary/10">
                                    @foreach($ecrans as $ecran => $droits)
                                        <div class="grid gap-1 py-1.5 sm:grid-cols-[11rem_1fr]">
                                            <dt class="text-xs font-medium text-primary/70">{{ $ecran }}</dt>
                                            <dd class="flex flex-wrap gap-1">
                                                @foreach($droits as $droit)
                                                    @php
                                                        $etat = $acces[$droit]['etat'];
                                                        $teinte = isset($ecritures[$droit]) ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-secondary/20 bg-accent/20 text-primary/80';
                                                        $action = \App\Support\PermissionLabels::action($droit);
                                                    @endphp
                                                    @if($etat === 'accorde' && $peutExcepter)
                                                        <label title="{{ $droit }}" class="inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] transition-colors focus-within:ring-2 focus-within:ring-secondary/40"
                                                               :class="choix.includes(@js($droit)) ? 'border-red-300 bg-red-50 text-red-700 line-through' : @js($teinte)">
                                                            <input type="checkbox" name="permissions[]" value="{{ $droit }}" x-model="choix" class="h-3 w-3 rounded border-secondary/40">
                                                            {{ $action }}
                                                        </label>
                                                    @elseif($etat === 'accorde')
                                                        <span title="{{ $droit }}" class="rounded-md border px-2 py-0.5 text-[11px] {{ $teinte }}">{{ $action }}</span>
                                                    @else
                                                        @php $exception = $acces[$droit]['exception']; @endphp
                                                        <span title="{{ $droit }} — {{ $exception->reason }}{{ $exception->expires_at ? ' — jusqu\'au ' . $exception->expires_at->format('d/m/Y') : '' }}"
                                                              class="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] text-red-700">
                                                            <s>{{ $action }}</s>
                                                            @if($etat === 'retire_console')
                                                                <span class="text-red-700/70">(console)</span>
                                                            @elseif($peutLever)
                                                                <button type="submit" form="retablir-{{ $exception->id }}" class="font-semibold underline-offset-2 hover:underline">Rétablir</button>
                                                            @endif
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        </details>
                    @empty
                        <p class="text-sm text-primary/60">Aucun accès.</p>
                    @endforelse
                </div>

                @if($peutExcepter)
                    <div x-show="choix.length > 0" x-cloak class="sticky bottom-3 mt-3 rounded-xl border border-red-200 bg-red-50 p-3 shadow-lg">
                        <p class="text-xs font-semibold text-red-800">
                            <span x-text="choix.length"></span> accès à retirer à {{ $membre->name }}
                        </p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-[1fr_10rem_auto]">
                            <input name="reason" value="{{ old('effect') === 'deny' ? old('reason') : '' }}" maxlength="255" :required="choix.length > 0"
                                   placeholder="Motif (obligatoire)" aria-label="Motif du retrait"
                                   class="rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs">
                            <input type="date" name="expires_at" aria-label="Retiré jusqu'au (facultatif)" title="Retiré jusqu'au (facultatif)"
                                   class="rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs">
                            <button type="submit" class="rounded-lg bg-red-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-800">Retirer</button>
                        </div>
                        <p class="mt-1 text-[11px] text-red-700/80">Sans date, le retrait vaut jusqu'à ce qu'on le lève.</p>
                    </div>
                @endif
            </form>

            {{-- Rétablir : un formulaire par retrait, hors du formulaire de retrait (on n'imbrique pas les formulaires). --}}
            @if($peutLever)
                @foreach($acces as $droit => $a)
                    @if($a['etat'] === 'retire')
                        <form id="retablir-{{ $a['exception']->id }}" method="POST" action="{{ route('droits.exceptions.destroy', $a['exception']) }}" class="hidden">
                            @csrf @method('DELETE')
                            <input type="hidden" name="retour" value="fiche">
                        </form>
                    @endif
                @endforeach
            @endif
        </section>
    </div>

    <div class="space-y-5">
        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="font-heading text-lg font-semibold text-primary">Périmètre</h2>
            <dl class="mt-3 space-y-1 text-xs">
                @foreach($portees as $droit => $libelle)
                    <div class="flex justify-between gap-2"><dt class="text-primary/60" title="{{ $droit }}">{{ \App\Support\PermissionLabels::ecran($droit) }}</dt><dd class="text-right text-primary">{{ $libelle }}</dd></div>
                @endforeach
                @if(app(\App\Services\RestaurantContext::class)->plusieurs())
                    <div class="flex justify-between gap-2"><dt class="text-primary/60">Restaurants</dt>
                        <dd class="text-right text-primary">{{ app(\App\Services\RestaurantContext::class)->vueGlobale($membre) ? 'Tous (direction)' : ($membre->restaurants->pluck('name')->implode(', ') ?: 'Aucun') }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-2"><dt class="text-primary/60">Dernière connexion</dt><dd class="text-primary">{{ $membre->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="font-heading text-lg font-semibold text-primary">Exceptions</h2>
            <p class="mt-1 text-xs text-primary/55">
                Une exception ne vaut que pour cette personne et l'emporte sur ses rôles. Elle se motive, peut expirer, et se lève à tout moment.
            </p>
            {{-- Une longue liste se replie : les retraits se lisent aussi dans « Ses accès ». --}}
            <details class="mt-3" @if($exceptions->count() <= 5) open @endif>
            <summary class="cursor-pointer text-xs font-semibold text-primary/70">{{ $exceptions->count() }} exception(s)</summary>
            <ul class="mt-2 space-y-2 text-xs">
                @forelse($exceptions as $e)
                    <li class="rounded-lg border border-secondary/20 px-3 py-2">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-primary" title="{{ $e->permission }}">{{ \App\Support\PermissionLabels::complet($e->permission) }}</span>
                            <span class="shrink-0 font-semibold {{ $e->effect === 'deny' ? 'text-red-700' : 'text-green-700' }}">{{ $e->effect === 'deny' ? 'Retiré' : 'Accordé' }}</span>
                        </div>
                        <p class="mt-1 text-primary/60">
                            {{ $e->reason }}{{ $e->expires_at ? ' — jusqu\'au ' . $e->expires_at->format('d/m/Y') : '' }}
                            @if($e->origin !== 'etablissement') — posée par la console @endif
                        </p>
                        @if($e->origin === 'etablissement' && $peutLever)
                            <form method="POST" action="{{ route('droits.exceptions.destroy', $e) }}" class="mt-1" onsubmit="return confirm('Lever cette exception ?');">
                                @csrf @method('DELETE')
                                <input type="hidden" name="retour" value="fiche">
                                <button type="submit" class="text-[11px] font-semibold text-red-700 hover:underline">Lever</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="text-primary/60">Aucune exception : ses accès sont exactement ceux de ses rôles.</li>
                @endforelse
            </ul>
            </details>

            @if($peutExcepter)
                <form method="POST" action="{{ route('droits.exceptions.store') }}" class="mt-4 space-y-2 border-t border-secondary/15 pt-4">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $membre->id }}">
                    <input type="hidden" name="effect" value="allow">
                    <input type="hidden" name="retour" value="fiche">
                    <label for="f-droit" class="block text-xs font-semibold text-primary/70">Accorder un accès en plus</label>
                    <p class="text-[11px] text-primary/50">Un accès que ses rôles ne lui donnent pas. Pour en retirer, cochez-les dans « Ses accès ».</p>
                    <select id="f-droit" name="permission" required class="w-full rounded-lg border border-secondary/30 bg-white px-2.5 py-1.5 text-xs">
                        <option value="">— Choisir l'accès —</option>
                        @foreach($aAccorder as $module => $ecrans)
                            <optgroup label="{{ $module }}">
                                @foreach($ecrans as $ecran => $droits)
                                    @foreach($droits as $droit)
                                        <option value="{{ $droit }}" @selected(old('effect') === 'allow' && old('permission') === $droit)>{{ $ecran }} — {{ \App\Support\PermissionLabels::action($droit) }}</option>
                                    @endforeach
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <input name="reason" required maxlength="255" value="{{ old('effect') === 'allow' ? old('reason') : '' }}" placeholder="Motif" aria-label="Motif" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
                    <input type="date" name="expires_at" aria-label="Accordé jusqu'au (facultatif)" title="Accordé jusqu'au (facultatif)" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
                    <label class="flex items-start gap-1.5 text-[11px] text-primary/70">
                        <input type="checkbox" name="derogation" value="1" class="mt-0.5 rounded border-secondary/40">
                        <span>Dérogation à la séparation des tâches — à cocher seulement si l'accès fait cumuler des fonctions incompatibles (encaisser et contrôler, par exemple) et que c'est voulu.</span>
                    </label>
                    <button type="submit" class="w-full rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white">Accorder</button>
                </form>
            @endif
        </section>
    </div>
</div>

@endsection

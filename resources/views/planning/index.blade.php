@extends('layouts.hotel')

@section('title', 'Planning des quarts')

@php
    $dimanche = $lundi->addDays(6);
    $periode = 'du ' . $lundi->locale('fr')->isoFormat('D MMMM') . ' au ' . $dimanche->locale('fr')->isoFormat('D MMMM YYYY');
    $lien = fn (\Carbon\CarbonImmutable $semaine) => route('planning.index', array_filter([
        'semaine' => $semaine->toDateString(),
        'departement' => $departement?->id,
    ]));
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Planning des quarts</h1>
        <p class="mt-0.5 text-sm text-primary/50">Semaine {{ $periode }}</p>
    </div>
    <nav class="flex flex-wrap items-center gap-2" aria-label="Changer de semaine">
        <a href="{{ $lien($lundi->subWeek()) }}" class="inline-flex items-center gap-1 rounded-lg border border-secondary/25 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-accent/20">
            <i data-lucide="chevron-left" class="h-3.5 w-3.5" aria-hidden="true"></i> Semaine précédente
        </a>
        @unless($lundi->equalTo(\App\Services\PlanningService::lundi()))
            <a href="{{ $lien(\App\Services\PlanningService::lundi()) }}" class="rounded-lg border border-secondary/25 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-accent/20">Cette semaine</a>
        @endunless
        <a href="{{ $lien($lundi->addWeek()) }}" class="inline-flex items-center gap-1 rounded-lg border border-secondary/25 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-accent/20">
            Semaine suivante <i data-lucide="chevron-right" class="h-3.5 w-3.5" aria-hidden="true"></i>
        </a>
    </nav>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
    </div>
@endif

@if($departement)
    {{-- Le dimanche, la semaine qui vient attend d'être programmée. --}}
    @if($rappel)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <p class="flex items-start gap-2">
                <i data-lucide="bell-ring" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"></i>
                <span>Programmez la semaine du {{ $rappel->locale('fr')->isoFormat('D') }} au {{ $rappel->addDays(6)->locale('fr')->isoFormat('D MMMM') }} pour {{ $departement->name }}, puis envoyez-la à votre personnel.</span>
            </p>
            @unless($lundi->equalTo($rappel))
                <a href="{{ $lien($rappel) }}" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">Programmer la semaine suivante</a>
            @endunless
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        @if($departements->count() > 1)
            <form method="GET" action="{{ route('planning.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="semaine" value="{{ $lundi->toDateString() }}">
                <label for="planning-departement" class="text-xs font-semibold text-primary/70">Service</label>
                <select id="planning-departement" name="departement" onchange="this.form.submit()"
                        class="rounded-lg border border-secondary/30 bg-white px-3 py-2 text-xs text-primary outline-none focus:border-secondary">
                    @foreach($departements as $d)
                        <option value="{{ $d->id }}" @selected($d->id === $departement->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </form>
        @else
            <p class="text-sm font-semibold text-primary">{{ $departement->name }}</p>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            @if($semaine?->published_at)
                <span class="text-xs {{ $modifiee ? 'font-semibold text-amber-700' : 'text-green-700' }}">
                    Envoyé le {{ $semaine->published_at->format('d/m/Y à H:i') }}@if($modifiee) — modifié depuis, à renvoyer @endif
                </span>
            @else
                <span class="text-xs text-primary/50">Pas encore envoyé au personnel</span>
            @endif

            @if($peutPlanifier)
                @droit('planning.recopier')
                    <form method="POST" action="{{ route('planning.recopier') }}">
                        @csrf
                        <input type="hidden" name="department_id" value="{{ $departement->id }}">
                        <input type="hidden" name="semaine" value="{{ $lundi->toDateString() }}">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/25 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-accent/20">
                            <i data-lucide="copy" class="h-3.5 w-3.5" aria-hidden="true"></i> Recopier la semaine précédente
                        </button>
                    </form>
                @enddroit
                @droit('planning.publier')
                    <form method="POST" action="{{ route('planning.publier') }}"
                          onsubmit="return confirm('Envoyer ce planning ? Chaque personne dont les quarts ont changé en sera prévenue.')">
                        @csrf
                        <input type="hidden" name="department_id" value="{{ $departement->id }}">
                        <input type="hidden" name="semaine" value="{{ $lundi->toDateString() }}">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-95">
                            <i data-lucide="send" class="h-3.5 w-3.5" aria-hidden="true"></i> Envoyer le planning
                        </button>
                    </form>
                @enddroit
            @endif
        </div>
    </div>

    {{-- Qui est en service à cet instant. --}}
    <section class="mb-4 rounded-xl border border-secondary/20 bg-white px-4 py-3" aria-label="En service maintenant">
        <p class="text-xs font-bold uppercase tracking-wider text-primary/60">En service maintenant</p>
        @if($enService->isEmpty())
            <p class="mt-1 text-sm text-primary/50">Personne de {{ $departement->name }} n'est planifié en ce moment.</p>
        @else
            <ul class="mt-2 flex flex-wrap gap-1.5">
                @foreach($enService as $a)
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500" aria-hidden="true"></span>
                        {{ $a->user->name }} <span class="text-green-700/70">· {{ $a->shift->name }} jusqu'à {{ $a->fin()->format('H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if($quarts->isEmpty())
        <p class="rounded-xl border border-dashed border-secondary/30 bg-white px-4 py-10 text-center text-sm text-primary/50">
            Aucun quart n'est défini. La direction les crée dans Paramètres › Quarts.
        </p>
    @else
        {{-- La semaine, comme un agenda : les jours en colonnes, les quarts en lignes. --}}
        <div class="overflow-x-auto rounded-xl border border-secondary/20 bg-white shadow-sm">
            <table class="w-full min-w-[56rem] table-fixed border-collapse text-sm">
                <caption class="sr-only">Planning de {{ $departement->name }}, semaine {{ $periode }}</caption>
                <thead>
                    <tr class="bg-[color-mix(in_oklab,var(--color-accent)_20%,white)]">
                        <th scope="col" class="w-28 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/60">Quart</th>
                        @foreach($jours as $jour)
                            <th scope="col" class="px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wider {{ $jour->equalTo($aujourdhui) ? 'bg-primary text-white' : 'text-primary/60' }}">
                                {{ $jour->locale('fr')->isoFormat('ddd D') }}
                                @if($jour->equalTo($aujourdhui))<span class="sr-only">(aujourd'hui)</span>@endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($quarts as $quart)
                        <tr class="border-t border-secondary/15 align-top">
                            <th scope="row" class="px-3 py-3 text-left">
                                <span class="block text-sm font-semibold text-primary">{{ $quart->name }}</span>
                                <span class="block text-[11px] font-normal text-primary/50">{{ $quart->horaire() }}</span>
                            </th>
                            @foreach($jours as $jour)
                                @php
                                    $cellule = $grille[$jour->toDateString()][$quart->id] ?? collect();
                                    $dejaLa = $cellule->pluck('user_id')->all();
                                @endphp
                                <td class="border-l border-secondary/10 px-2 py-2 {{ $jour->equalTo($aujourdhui) ? 'bg-accent/10' : '' }}">
                                    <ul class="space-y-1">
                                        @foreach($cellule as $a)
                                            <li class="flex items-start justify-between gap-1 rounded-md bg-primary/5 px-2 py-1 text-xs leading-tight text-primary">
                                                {{-- Le nom passe à la ligne plutôt que d'être coupé : on doit lire qui travaille. --}}
                                                <span class="min-w-0">{{ $a->user->name }}</span>
                                                @if($peutPlanifier)
                                                    @droit('planning.affectations.supprimer')
                                                        <form method="POST" action="{{ route('planning.affectations.destroy', $a) }}">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="rounded p-0.5 text-primary/40 hover:bg-red-50 hover:text-red-700"
                                                                    aria-label="Retirer {{ $a->user->name }} du quart {{ $quart->name }} du {{ $jour->locale('fr')->isoFormat('dddd D') }}">
                                                                <i data-lucide="x" class="h-3 w-3" aria-hidden="true"></i>
                                                            </button>
                                                        </form>
                                                    @enddroit
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if($peutPlanifier)
                                        @droit('planning.affectations.creer')
                                            @php $disponibles = $personnel->whereNotIn('id', $dejaLa); @endphp
                                            @if($disponibles->isNotEmpty())
                                                <div x-data="{ ouvert: false }" class="mt-1">
                                                    <button type="button" @click="ouvert = ! ouvert; $nextTick(() => ouvert && $refs.choix.focus())" :aria-expanded="ouvert.toString()"
                                                            class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-semibold text-primary/50 hover:bg-accent/20 hover:text-primary">
                                                        <i data-lucide="plus" class="h-3 w-3" aria-hidden="true"></i> Ajouter
                                                    </button>
                                                    <form x-show="ouvert" x-cloak method="POST" action="{{ route('planning.affectations.store') }}" class="mt-1 space-y-1">
                                                        @csrf
                                                        <input type="hidden" name="department_id" value="{{ $departement->id }}">
                                                        <input type="hidden" name="work_shift_id" value="{{ $quart->id }}">
                                                        <input type="hidden" name="date" value="{{ $jour->toDateString() }}">
                                                        <select x-ref="choix" name="user_id" required aria-label="Personne à placer — {{ $quart->name }}, {{ $jour->locale('fr')->isoFormat('dddd D') }}"
                                                                class="w-full rounded-md border border-secondary/30 bg-white px-1.5 py-1 text-xs">
                                                            <option value="">Choisir…</option>
                                                            @foreach($disponibles as $personne)
                                                                <option value="{{ $personne->id }}">{{ $personne->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="w-full rounded-md bg-primary px-2 py-1 text-[11px] font-semibold text-white">Placer</button>
                                                    </form>
                                                </div>
                                            @endif
                                        @enddroit
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Le compte de chacun : personne n'est oublié, personne n'est surchargé. --}}
        <div class="mt-6">
            <x-table :rows="$personnel" inline="never" empty="Aucun personnel actif dans ce service." empty-icon="users" caption="Quarts et heures de la semaine">
                <x-slot:toolbar>
                    <h2 class="text-sm font-semibold text-primary">Quarts et heures de la semaine</h2>
                </x-slot:toolbar>
                <x-slot:head>
                    <x-table.col>Personne</x-table.col>
                    <x-table.col align="right">Quarts</x-table.col>
                    <x-table.col align="right">Heures</x-table.col>
                </x-slot:head>
                @foreach($personnel as $personne)
                    @php $compte = $heures[$personne->id] ?? null; @endphp
                    <x-table.row>
                        <x-table.cell>
                            <span class="font-medium text-primary">{{ $personne->name }}</span>
                            @unless($compte)<span class="ml-2 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Non planifié cette semaine</span>@endunless
                        </x-table.cell>
                        <x-table.cell align="right">{{ $compte['quarts'] ?? 0 }}</x-table.cell>
                        <x-table.cell align="right" nowrap>{{ $compte ? rtrim(rtrim(number_format($compte['heures'], 2, ',', ' '), '0'), ',') . ' h' : '—' }}</x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>
    @endif
@endif

{{-- Ses propres quarts. --}}
<section class="mt-6 rounded-xl border border-secondary/20 bg-white p-5 shadow-sm" aria-labelledby="mes-quarts">
    <h2 id="mes-quarts" class="font-heading text-lg font-semibold text-primary">Mes quarts</h2>
    @if($mesQuarts->isEmpty())
        <p class="mt-2 text-sm text-primary/55">Aucun quart prévu pour vous cette semaine.</p>
    @else
        <ul class="mt-3 divide-y divide-secondary/10">
            @foreach($mesQuarts as $a)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <span class="font-medium text-primary {{ $a->jour()->equalTo($aujourdhui) ? 'underline decoration-secondary decoration-2 underline-offset-4' : '' }}">
                        {{ ucfirst($a->jour()->locale('fr')->isoFormat('dddd D MMMM')) }}
                    </span>
                    <span class="text-primary/70">{{ $a->shift->name }} · {{ $a->shift->horaire() }}@if($a->department) · {{ $a->department->name }}@endif</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection

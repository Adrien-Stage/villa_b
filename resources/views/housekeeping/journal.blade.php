@extends('layouts.hotel')

@section('title', 'Suivi des chambres — Housekeeping')

@section('content')
@php
    $champ = 'mt-1 rounded-lg border border-secondary/30 bg-white px-2.5 py-1.5 text-sm text-primary outline-none focus:border-primary';
    $heure = fn ($t) => $t ? $t->format($du->isSameDay($au) ? 'H:i' : 'd/m H:i') : null;
    $styles = [
        \App\Models\HousekeepingLog::CLEANED           => 'bg-purple-50 text-purple-700 border-purple-200',
        \App\Models\HousekeepingLog::STAYOVER_DONE     => 'bg-blue-50 text-blue-700 border-blue-200',
        \App\Models\HousekeepingLog::INSPECTED         => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        \App\Models\HousekeepingLog::RELEASED          => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        \App\Models\HousekeepingLog::REJECTED          => 'bg-red-50 text-red-700 border-red-200',
        \App\Models\HousekeepingLog::ISSUE             => 'bg-red-50 text-red-700 border-red-200',
        \App\Models\HousekeepingLog::MINIBAR_CHECKED   => 'bg-amber-50 text-amber-800 border-amber-200',
        \App\Models\HousekeepingLog::MINIBAR_RESTOCKED => 'bg-amber-50 text-amber-800 border-amber-200',
    ];
    $neutre = 'bg-secondary/10 text-primary/70 border-secondary/20';
@endphp

<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="list-checks" class="w-7 h-7 text-primary"></i>
                <span>Suivi des chambres</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                Qui a nettoyé, contrôlé, remis en vente ou rechargé chaque chambre, et à quelle heure.
            </p>
        </div>
        <a href="{{ route('housekeeping.index') }}"
           class="self-start sm:self-auto inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shrink-0">
            <i data-lucide="sparkles" class="w-4 h-4"></i> Housekeeping
        </a>
    </div>

    <form method="GET" action="{{ route('housekeeping.journal.index') }}" class="rounded-xl border border-secondary/20 bg-white px-4 py-3">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="du" class="block text-[11px] font-medium text-primary/50">Du</label>
                <input type="date" name="du" id="du" value="{{ $du->toDateString() }}" class="{{ $champ }}">
            </div>
            <div>
                <label for="au" class="block text-[11px] font-medium text-primary/50">Au</label>
                <input type="date" name="au" id="au" value="{{ $au->toDateString() }}" class="{{ $champ }}">
            </div>
            <div>
                <label for="room_id" class="block text-[11px] font-medium text-primary/50">Chambre</label>
                <select name="room_id" id="room_id" class="{{ $champ }}">
                    <option value="">Toutes</option>
                    @foreach($rooms as $r)
                        <option value="{{ $r->id }}" @selected((int) ($filtres['room_id'] ?? 0) === $r->id)>{{ $r->number }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="user_id" class="block text-[11px] font-medium text-primary/50">Personne</label>
                <select name="user_id" id="user_id" class="{{ $champ }}">
                    <option value="">Tout le monde</option>
                    @foreach($personnes as $p)
                        <option value="{{ $p->id }}" @selected((int) ($filtres['user_id'] ?? 0) === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="team_id" class="block text-[11px] font-medium text-primary/50">Équipe</label>
                <select name="team_id" id="team_id" class="{{ $champ }}">
                    <option value="">Toutes</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}" @selected((int) ($filtres['team_id'] ?? 0) === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">Afficher</button>
        </div>
    </form>

    {{-- ═══ Le travail de chacun ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20">
            <h2 class="font-heading font-semibold text-primary text-sm">Par personne</h2>
            <p class="text-xs text-primary/45 mt-0.5">
                {{ $du->isSameDay($au) ? 'Le ' . $du->format('d/m/Y') : 'Du ' . $du->format('d/m/Y') . ' au ' . $au->format('d/m/Y') }}
            </p>
        </div>
        @if($parPersonne->isEmpty())
            <div class="px-5 py-8 text-sm text-primary/40 text-center">Aucun geste enregistré sur la période.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/10 text-[11px] uppercase tracking-wider text-primary/50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">Personne</th>
                            <th class="px-3 py-2 text-right font-semibold">Nettoyées</th>
                            <th class="px-3 py-2 text-right font-semibold">Recouches</th>
                            <th class="px-3 py-2 text-right font-semibold">Contrôlées</th>
                            <th class="px-3 py-2 text-right font-semibold">Refusées</th>
                            <th class="px-3 py-2 text-right font-semibold">Relevés mini-bar</th>
                            <th class="px-3 py-2 text-right font-semibold">Mini-bars rechargés</th>
                            <th class="px-4 py-2 text-right font-semibold">Dernier geste</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($parPersonne as $ligne)
                            <tr>
                                <td class="px-4 py-2 font-medium text-primary">{{ $ligne['user']?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $ligne['nettoyees'] ?: '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $ligne['recouches'] ?: '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $ligne['controlees'] ?: '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $ligne['refusees'] ?: '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $ligne['releves'] ?: '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $ligne['recharges'] ?: '' }}</td>
                                <td class="px-4 py-2 text-right text-primary/60 tabular-nums">{{ $heure($ligne['dernier']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ═══ Chaque passage dans une chambre ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-heading font-semibold text-primary text-sm">Chambres traitées</h2>
                <p class="text-xs text-primary/45 mt-0.5">Une ligne par ménage de départ ou par recouche.</p>
            </div>
            <span class="text-xs text-primary/40">{{ $passages->count() }}</span>
        </div>
        @if($passages->isEmpty())
            <div class="px-5 py-8 text-sm text-primary/40 text-center">Aucune chambre traitée sur la période.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/10 text-[11px] uppercase tracking-wider text-primary/50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">Chambre</th>
                            <th class="px-3 py-2 text-left font-semibold">Équipe</th>
                            <th class="px-3 py-2 text-left font-semibold">Commencée</th>
                            <th class="px-3 py-2 text-left font-semibold">Terminée</th>
                            <th class="px-3 py-2 text-right font-semibold">Durée</th>
                            <th class="px-3 py-2 text-left font-semibold">Contrôlée</th>
                            <th class="px-4 py-2 text-left font-semibold">Remise en vente</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($passages as $a)
                            <tr class="align-top">
                                <td class="px-4 py-2">
                                    <span class="font-semibold text-primary">{{ $a->room?->number }}</span>
                                    <span class="ml-1 px-1.5 py-0.5 rounded border text-[10px] {{ $a->isRecouche() ? 'bg-blue-50 text-blue-700 border-blue-200' : $neutre }}">
                                        {{ $a->isRecouche() ? 'Recouche' : 'Départ' }}
                                    </span>
                                    @if($a->status === 'blocked')
                                        <span class="ml-1 px-1.5 py-0.5 rounded border text-[10px] bg-red-50 text-red-700 border-red-200">Problème</span>
                                    @elseif($a->status === 'cancelled')
                                        <span class="ml-1 px-1.5 py-0.5 rounded border text-[10px] {{ $neutre }}">Annulée</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-primary/70">{{ $a->team?->name ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @if($a->started_at)
                                        <span class="tabular-nums">{{ $heure($a->started_at) }}</span>
                                        <span class="block text-xs text-primary/50">{{ $a->startedBy?->name }}</span>
                                    @else
                                        <span class="text-primary/30">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if($a->completed_at)
                                        <span class="tabular-nums">{{ $heure($a->completed_at) }}</span>
                                        <span class="block text-xs text-primary/50">{{ $a->completedBy?->name }}</span>
                                    @else
                                        <span class="text-primary/30">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-primary/70">
                                    @php $minutes = $a->dureeEnMinutes(); @endphp
                                    {{ $minutes !== null ? $minutes . ' min' : '' }}
                                </td>
                                <td class="px-3 py-2">
                                    @if($a->inspected_at)
                                        <span class="tabular-nums">{{ $heure($a->inspected_at) }}</span>
                                        <span class="block text-xs text-primary/50">{{ $a->inspectedBy?->name }}</span>
                                    @else
                                        <span class="text-primary/30">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($a->released_at)
                                        <span class="tabular-nums">{{ $heure($a->released_at) }}</span>
                                        <span class="block text-xs text-primary/50">{{ $a->releasedBy?->name }}</span>
                                    @else
                                        <span class="text-primary/30">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ═══ Journal détaillé ═══ --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/20 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-heading font-semibold text-primary text-sm">Journal</h2>
                <p class="text-xs text-primary/45 mt-0.5">Chaque geste, du plus récent au plus ancien.</p>
            </div>
            <span class="text-xs text-primary/40">{{ $journal->count() }}</span>
        </div>
        @if($journal->isEmpty())
            <div class="px-5 py-8 text-sm text-primary/40 text-center">Rien à afficher.</div>
        @else
            <ul class="divide-y divide-secondary/10">
                @foreach($journal as $geste)
                    <li class="px-5 py-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span class="{{ $du->isSameDay($au) ? 'w-12' : 'w-24' }} shrink-0 tabular-nums text-primary/50">{{ $heure($geste->occurred_at) }}</span>
                        <span class="font-semibold text-primary w-16 shrink-0">Ch. {{ $geste->room?->number }}</span>
                        <span class="px-2 py-0.5 rounded-full border text-[11px] font-medium {{ $styles[$geste->action] ?? $neutre }}">{{ $geste->actionLabel() }}</span>
                        <span class="text-primary/80">{{ $geste->user?->name ?? 'Système' }}</span>
                        @if($geste->team)
                            <span class="text-xs text-primary/45">· {{ $geste->team->name }}</span>
                        @endif
                        @if($geste->details)
                            <span class="w-full sm:w-auto sm:flex-1 text-xs text-primary/55">{{ $geste->details }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection

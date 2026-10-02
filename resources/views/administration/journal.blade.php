@extends('layouts.hotel')

@section('title', "Journal d'audit")

@section('content')

<div class="mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Journal d'audit</h1>
    <p class="text-sm text-primary/50 mt-0.5">
        Connexions, refus d'accès, actions sensibles, interventions de l'administrateur et sessions du support.
    </p>
</div>

<form method="GET" action="{{ route('audit.index') }}" class="bg-white rounded-xl shadow-sm p-4 mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
    <div>
        <label for="j-user" class="block text-[11px] font-semibold text-primary/60 mb-1">Utilisateur</label>
        <select id="j-user" name="user_id" class="w-full rounded-lg border border-secondary/30 px-2.5 py-2 text-xs">
            <option value="">Tous</option>
            @foreach($utilisateurs as $u)
                <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="j-type" class="block text-[11px] font-semibold text-primary/60 mb-1">Événement</label>
        <select id="j-type" name="event_type" class="w-full rounded-lg border border-secondary/30 px-2.5 py-2 text-xs">
            <option value="">Tous</option>
            @foreach($types as $type)
                <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="j-module" class="block text-[11px] font-semibold text-primary/60 mb-1">Module</label>
        <select id="j-module" name="module" class="w-full rounded-lg border border-secondary/30 px-2.5 py-2 text-xs">
            <option value="">Tous</option>
            @foreach($modules as $module)
                <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="j-du" class="block text-[11px] font-semibold text-primary/60 mb-1">Du</label>
        <input type="date" id="j-du" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
    </div>
    <div>
        <label for="j-au" class="block text-[11px] font-semibold text-primary/60 mb-1">Au</label>
        <input type="date" id="j-au" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
    </div>
    <div>
        <label for="j-q" class="block text-[11px] font-semibold text-primary/60 mb-1">Recherche</label>
        <input type="search" id="j-q" name="q" value="{{ request('q') }}" placeholder="Texte de l'action" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
    </div>
    <div class="sm:col-span-2 lg:col-span-6 flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center gap-1.5 text-xs text-primary/70">
            <input type="checkbox" name="interventions" value="1" @checked(request()->boolean('interventions')) class="rounded border-secondary/40">
            Interventions seulement
        </label>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('audit.index') }}" class="px-3 py-1.5 rounded-lg border border-secondary/30 text-xs text-primary/70 hover:text-primary">Réinitialiser</a>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-semibold">Filtrer</button>
        </div>
    </div>
</form>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-left text-xs">
        <thead class="bg-accent/30 text-[11px] uppercase tracking-wider text-primary/50">
            <tr>
                <th class="px-4 py-2.5">Date</th>
                <th class="px-4 py-2.5">Utilisateur</th>
                <th class="px-4 py-2.5">Événement</th>
                <th class="px-4 py-2.5">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-secondary/15">
            @forelse($journal as $ligne)
                @php $enIntervention = isset($ligne->payload['intervention_id']) || str_starts_with($ligne->event_type, 'intervention'); @endphp
                <tr class="{{ $enIntervention ? 'bg-amber-50/60' : '' }}">
                    <td class="px-4 py-2.5 whitespace-nowrap text-primary/60">{{ $ligne->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td class="px-4 py-2.5 text-primary">{{ $ligne->user?->name ?? 'Système' }}</td>
                    <td class="px-4 py-2.5">
                        <span class="rounded-full bg-accent/40 px-2 py-0.5 font-mono text-[10px] text-primary/70">{{ $ligne->event_type }}</span>
                        @if($ligne->module)<span class="ml-1 text-[10px] text-primary/40">{{ $ligne->module }}</span>@endif
                    </td>
                    <td class="px-4 py-2.5 text-primary/80">
                        @if($enIntervention)
                            <span class="mr-1 rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900">Intervention</span>
                        @endif
                        {{ $ligne->action }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-primary/50">Aucune entrée.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $journal->links() }}</div>

@endsection

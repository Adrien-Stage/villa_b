@extends('layouts.hotel')

@section('title', 'Sessions du support')

@section('content')

<div class="mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Sessions du support</h1>
    <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
        Le support de l'éditeur entre par le mode assistance de la console, sous un compte technique qui consulte sans
        écrire. Chaque session est listée ici ; ses actions figurent au journal d'audit.
    </p>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-left text-xs">
        <thead class="bg-accent/30 text-[11px] uppercase tracking-wider text-primary/50">
            <tr>
                <th class="px-4 py-2.5">Technicien</th>
                <th class="px-4 py-2.5">Début</th>
                <th class="px-4 py-2.5">Fin</th>
                <th class="px-4 py-2.5">Référence</th>
                <th class="px-4 py-2.5">Adresse</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-secondary/15">
            @forelse($sessions as $s)
                <tr>
                    <td class="px-4 py-2.5 text-primary">{{ $s->technicien }}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap text-primary/70">{{ $s->debut->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap text-primary/70">
                        @if($s->fin)
                            {{ $s->fin->format('d/m/Y H:i') }}
                        @else
                            <span class="text-primary/40">sans déconnexion enregistrée</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 font-mono text-primary/50">{{ $s->reference ?: '—' }}</td>
                    <td class="px-4 py-2.5 font-mono text-primary/50">{{ $s->ip_address ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-primary/50">Aucune session du support.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $sessions->links() }}</div>

@endsection

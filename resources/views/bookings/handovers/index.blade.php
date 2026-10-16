@extends('layouts.hotel')

@section('title', 'Passations de service')

@section('content')
@php
    $fcfa = fn ($c) => $c === null ? '—' : number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
@endphp

<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="hand-coins" class="w-7 h-7 text-primary"></i>
                <span>Passations de service</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1">Chaque fin de quart à la réception : qui a remis le tiroir, qui l'a repris, ce qu'ils ont compté.</p>
        </div>
        @droit('bookings.handovers.creer')
            @if(app(\App\Services\PassationService::class)->sessionOuverte(auth()->user()))
                <a href="{{ route('bookings.handovers.create') }}"
                   class="self-start sm:self-auto inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-surface-dark transition-colors">
                    <i data-lucide="hand-coins" class="w-4 h-4"></i> Terminer mon service
                </a>
            @endif
        @enddroit
    </div>

    @include('bookings.handovers.partials.flash')

    @if($enAttente->isNotEmpty())
        <section class="rounded-xl border border-amber-200 bg-amber-50 overflow-hidden" aria-labelledby="en-attente">
            <h2 id="en-attente" class="px-5 py-3 font-heading font-semibold text-amber-900 text-sm border-b border-amber-200">En attente de reprise</h2>
            <ul class="divide-y divide-amber-200">
                @foreach($enAttente as $p)
                    <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span class="text-amber-900">
                            <strong>{{ $p->fromUser?->name }}</strong> a remis le tiroir à {{ $p->handed_at->format('H:i') }} ({{ $fcfa($p->handed_amount) }})
                            @if($p->plannedUser) · prévu : {{ $p->plannedUser->name }} @endif
                        </span>
                        <a href="{{ route('bookings.handovers.take', $p) }}" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700">Prendre le service</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="bg-white rounded-xl shadow-sm overflow-hidden">
        @if($passations->isEmpty())
            <p class="px-5 py-8 text-sm text-primary/40 text-center">Aucune passation reprise pour le moment.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/10 text-[11px] uppercase tracking-wider text-primary/50">
                        <tr>
                            <th class="px-5 py-2 text-left font-semibold">Remise</th>
                            <th class="px-3 py-2 text-left font-semibold">Remis par</th>
                            <th class="px-3 py-2 text-right font-semibold">Compté</th>
                            <th class="px-3 py-2 text-left font-semibold">Repris par</th>
                            <th class="px-3 py-2 text-right font-semibold">Recompté</th>
                            <th class="px-5 py-2 text-right font-semibold">Écart</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($passations as $p)
                            <tr>
                                <td class="px-5 py-2"><a href="{{ route('bookings.handovers.show', $p) }}" class="font-medium text-primary hover:underline">{{ $p->handed_at->format('d/m H:i') }}</a></td>
                                <td class="px-3 py-2">{{ $p->fromUser?->name }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fcfa($p->handed_amount) }}</td>
                                <td class="px-3 py-2">
                                    {{ $p->toUser?->name }}
                                    @if($p->reprisHorsPlanning())<span class="text-xs text-amber-700">(à la place de {{ $p->plannedUser?->name }})</span>@endif
                                    <span class="block text-xs text-primary/45">{{ $p->taken_at?->format('d/m H:i') }}</span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fcfa($p->received_amount) }}</td>
                                <td class="px-5 py-2 text-right tabular-nums {{ (int) $p->discrepancy_amount !== 0 ? 'text-red-700 font-semibold' : 'text-primary/40' }}">
                                    {{ (int) $p->discrepancy_amount !== 0 ? $fcfa($p->discrepancy_amount) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $passations->links() }}</div>
        @endif
    </section>
</div>
@endsection

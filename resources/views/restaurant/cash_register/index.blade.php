@extends('layouts.hotel')

@section('title', 'Caisse — Restaurant')

@php $fcfa = fn ($c) => $c === null ? '—' : number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA'; @endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Caisse du restaurant</h1>
        <p class="text-sm text-primary/50 mt-0.5">Une caisse par restaurant, une session par personne ; la comptabilité contresigne chaque comptage.</p>
    </div>
    @droit('restaurant.cash_register.open.creer')
        @if(! $enCours)
            <a href="{{ route('restaurant.cash_register.open') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg">
                <i data-lucide="lock-open" class="w-3.5 h-3.5"></i> Ouvrir ma caisse
            </a>
        @elseif($enCours->status === 'open')
            <a href="{{ route('restaurant.cash_register.close') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg">
                <i data-lucide="lock" class="w-3.5 h-3.5"></i> Compter ma caisse
            </a>
        @endif
    @enddroit
</div>

@foreach(['success' => 'border-green-200 bg-green-50 text-green-700', 'info' => 'border-sky-200 bg-sky-50 text-sky-800'] as $cle => $classes)
    @if(session($cle))<div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($cle) }}</div>@endif
@endforeach

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-left text-xs">
        <thead class="bg-accent/30 text-[11px] uppercase tracking-wider text-primary/50">
            <tr>
                <th class="px-4 py-2.5">Caisse</th>
                <th class="px-4 py-2.5">Titulaire</th>
                <th class="px-4 py-2.5">Ouverture</th>
                <th class="px-4 py-2.5">Fond</th>
                <th class="px-4 py-2.5">Théorique</th>
                <th class="px-4 py-2.5">Compté</th>
                <th class="px-4 py-2.5">Écart</th>
                <th class="px-4 py-2.5">État</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-secondary/15">
            @forelse($sessions as $s)
                <tr>
                    <td class="px-4 py-2.5 text-primary">{{ $s->pointOfSale?->name ?? 'Restaurant' }}</td>
                    <td class="px-4 py-2.5 text-primary">{{ $s->user?->name }}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap text-primary/60">{{ $s->opened_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2.5">{{ $fcfa($s->opening_amount) }}</td>
                    <td class="px-4 py-2.5">{{ $fcfa($s->theoretical_closing_amount) }}</td>
                    <td class="px-4 py-2.5">{{ $fcfa($s->actual_closing_amount) }}</td>
                    <td class="px-4 py-2.5 {{ (int) $s->discrepancy_amount < 0 ? 'text-red-700' : 'text-primary' }}">{{ $fcfa($s->discrepancy_amount) }}</td>
                    <td class="px-4 py-2.5">
                        @if($s->closed_at)
                            <span class="rounded-full bg-green-50 px-2 py-0.5 font-semibold text-green-700">Close{{ $s->witness ? ' — ' . $s->witness->name : '' }}</span>
                        @elseif($s->isPendingReview())
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-800">Attend la comptabilité</span>
                        @elseif($s->status === 'paused')
                            <span class="rounded-full bg-accent/40 px-2 py-0.5 font-semibold text-primary/70">En pause</span>
                        @else
                            <span class="rounded-full bg-sky-50 px-2 py-0.5 font-semibold text-sky-800">Ouverte</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-primary/50">Aucune session de caisse.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $sessions->links() }}</div>
@endsection

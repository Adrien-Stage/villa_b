@extends('layouts.hotel')

@section('title', 'Périodes comptables')

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Exercices et périodes</h1>
    <p class="text-sm text-primary/60 mt-1">Verrouillage des écritures — Article 22 de l'Acte Uniforme</p>
</div>

@include('accounting.ledger.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

<div class="px-4 py-3 mb-5 rounded-xl bg-accent/20 border border-secondary/20 text-[11px] text-primary/70 leading-relaxed">
    <strong>Le verrouillage est définitif.</strong> Une période verrouillée n'accepte plus aucune écriture :
    toute correction ultérieure passe obligatoirement par une contre-passation datée d'une période encore ouverte.
    L'Acte Uniforme impose de verrouiller au plus tard <strong>un mois</strong> après la fin de la période.
</div>

<form method="POST" action="{{ route('accounting.ledger.years.open') }}" class="flex flex-col sm:flex-row gap-2 mb-6">
    @csrf
    <input type="number" name="year" min="2000" max="2100" value="{{ now()->year }}" required
           class="w-full sm:w-40 rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
    <button type="submit" class="px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shrink-0">
        Ouvrir cet exercice
    </button>
</form>

@forelse($exercices as $exercice)
    <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-secondary/15 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-primary">{{ $exercice->label }}</h2>
                <p class="text-xs text-primary/50">
                    {{ $exercice->starts_on->format('d/m/Y') }} — {{ $exercice->ends_on->format('d/m/Y') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-1.5">
                @if($exercice->hasOpeningBalance())
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-green-50 text-green-700 text-[11px] font-semibold">
                        <i data-lucide="check" class="w-3 h-3"></i> À-nouveaux repris
                    </span>
                @else
                    <a href="{{ route('accounting.ledger.opening', ['exercice' => $exercice->id]) }}"
                       class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-amber-50 text-amber-800 text-[11px] font-semibold hover:bg-amber-100 transition-colors">
                        <i data-lucide="import" class="w-3 h-3"></i> Reprendre les à-nouveaux
                    </a>
                @endif
                @if($exercice->isClosed())
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-accent/40 text-primary/70 text-[11px] font-semibold">
                        <i data-lucide="lock" class="w-3 h-3"></i> Exercice clos
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-px bg-secondary/10">
            @foreach($exercice->periods->sortBy('starts_on') as $p)
                <div class="bg-white px-4 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-primary">{{ $p->label() }}</p>
                        <p class="text-[10px] text-primary/45">
                            @if($p->isLocked())
                                Verrouillée le {{ $p->locked_at?->format('d/m/Y') }}
                            @elseif($p->isOverdue())
                                <span class="text-amber-700 font-semibold">À verrouiller depuis le {{ $p->lockDeadline()->format('d/m/Y') }}</span>
                            @else
                                Ouverte — délai au {{ $p->lockDeadline()->format('d/m/Y') }}
                            @endif
                        </p>
                    </div>
                    @if($p->isLocked())
                        <i data-lucide="lock" class="w-4 h-4 text-primary/30 shrink-0"></i>
                    @else
                        <form method="POST" action="{{ route('accounting.ledger.periods.lock', $p) }}" class="shrink-0"
                              onsubmit="return confirm('Verrouiller {{ $p->label() }} ? Cette action est définitive : les corrections ultérieures ne seront possibles que par contre-passation.');">
                            @csrf
                            <button type="submit"
                                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition-colors
                                        {{ $p->isOverdue() ? 'bg-amber-500 text-white hover:bg-amber-600' : 'border border-secondary/25 text-primary/60 hover:text-primary hover:bg-accent/20' }}">
                                Verrouiller
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@empty
    <div class="bg-white rounded-xl border border-secondary/20 p-8 text-center text-primary/50 text-sm">
        Aucun exercice ouvert.
    </div>
@endforelse
@endsection

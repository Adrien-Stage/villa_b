@extends('layouts.hotel')

@section('title', 'Ouverture de caisse — Restaurant')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-8">
        <h1 class="text-2xl font-heading font-bold text-primary mb-6 flex items-center gap-3">
            <i data-lucide="lock-open" class="text-green-500 w-8 h-8"></i>
            Ouverture de caisse
        </h1>

        <p class="text-primary/70 mb-6 border-l-4 border-accent pl-4">
            Votre session : vous comptez ce que vous encaissez. Déclarez le fond de caisse présent dans le tiroir ;
            la comptabilité contresignera votre comptage en fin de service.
        </p>

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('restaurant.cash_register.open.store') }}" method="POST" class="space-y-6">
            @csrf

            @if($caisses->count() > 1)
                <fieldset>
                    <legend class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-2">Caisse *</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($caisses as $caisse)
                            @php $tenue = $tenues[$caisse->id] ?? null; @endphp
                            <label class="flex items-start gap-2 rounded-lg border px-3 py-2.5 text-sm {{ $tenue ? 'border-secondary/20 bg-accent/10 text-primary/40' : 'border-secondary/30 text-primary' }}">
                                <input type="radio" name="point_of_sale_id" value="{{ $caisse->id }}" @disabled($tenue) @checked(old('point_of_sale_id') == $caisse->id) class="mt-0.5">
                                <span>
                                    <span class="font-semibold">{{ $caisse->name }}</span>
                                    @if($tenue)
                                        <span class="block text-xs">Tenue par {{ $tenue->user?->name }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @elseif($caisses->count() === 1 && $tenues[$caisses->first()->id])
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    La caisse {{ $caisses->first()->name }} est tenue par {{ $tenues[$caisses->first()->id]->user?->name }} :
                    elle doit compter sa caisse avant que vous ouvriez la vôtre.
                </p>
            @endif

            <div>
                <label for="fond" class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Fond de caisse initial (FCFA) *</label>
                <input type="number" id="fond" name="opening_amount" required min="0" step="1" value="{{ old('opening_amount', 0) }}"
                       class="w-full px-4 py-3 text-lg border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary transition-colors font-semibold">
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-surface-dark text-white px-6 py-3 rounded-xl font-medium transition-colors shadow-sm flex justify-center items-center gap-2">
                <i data-lucide="play" class="w-5 h-5"></i>
                Ouvrir ma caisse
            </button>
        </form>
    </div>
</div>
@endsection

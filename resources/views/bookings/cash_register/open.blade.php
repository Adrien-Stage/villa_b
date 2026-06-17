@extends('layouts.hotel')

@section('title', 'Ouverture de Caisse Réception')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    @if ($message = session('warning'))
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800 flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-5 h-5"></i>
            {{ $message }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-8">
        <h1 class="text-2xl font-heading font-bold text-primary mb-6 flex items-center gap-3">
            <i data-lucide="lock-open" class="text-green-500 w-8 h-8"></i>
            Ouverture de Caisse (Réception)
        </h1>
        
        <p class="text-primary/70 mb-8 border-l-4 border-accent pl-4">
            Pour commencer à enregistrer des réservations ou des encaissements de paiement, veuillez déclarer le fond de caisse initial disponible dans le tiroir-caisse de la réception.
        </p>

        <form action="{{ route('bookings.cash_register.open.store') }}" method="POST" class="space-y-6">
            @csrf

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Fond de caisse initial (FCFA) *</label>
                <input type="number" name="opening_amount" required min="0" step="1"
                       value="0"
                       class="w-full px-4 py-3 text-lg border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary transition-colors font-semibold"
                       placeholder="Ex: 50000">
                @error('opening_amount')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-primary hover:bg-surface-dark text-white px-6 py-3 rounded-xl font-medium transition-colors shadow-sm flex justify-center items-center gap-2">
                    <i data-lucide="play" class="w-5 h-5"></i>
                    Ouvrir la caisse et démarrer le service
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

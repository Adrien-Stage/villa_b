@extends('layouts.hotel')

@section('title', 'Nouveau groupe')

@section('content')

<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <a href="{{ route('groups.index') }}"
           class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour aux groupes
        </a>
        <h1 class="font-heading text-2xl font-semibold text-primary">Nouveau dossier groupe</h1>
        <p class="text-sm text-primary/50 mt-0.5">Étape 1 — Informations du groupe</p>
    </div>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Formulaire groupe --}}
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="font-heading font-semibold text-primary mb-5">Détails du groupe</h2>

        <form method="POST" action="{{ route('groups.store') }}">
            @csrf

            <div class="mb-6">
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Contact principal du groupe *
                </label>
                <x-customer-search 
                    :customers="$customers" 
                    name="contact_customer_id" 
                    :value="old('contact_customer_id')" 
                    placeholder="Rechercher le contact principal..." 
                />
                @error('contact_customer_id')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6 pt-4 border-t border-secondary/20" x-data="{ isBooker: 'self' }">
                <label class="block text-sm font-semibold text-primary mb-2">Qui effectue cette réservation ?</label>
                <select name="is_booker" x-model="isBooker" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary mb-4">
                    <option value="self">Le contact du groupe lui-même</option>
                    <option value="other">Une tierce personne (Mandataire)</option>
                </select>

                <div x-show="isBooker === 'other'" style="display: none;" class="p-4 bg-gray-50 border border-secondary/20 rounded-xl">
                    <h3 class="text-sm font-semibold text-primary mb-3">Le mandataire (Celui qui réserve)</h3>
                    <x-customer-search :customers="$customers" name="booker_id" :value="old('booker_id')" :allow-creation="false" placeholder="Rechercher le mandataire...">
                    </x-customer-search>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Nom du groupe *
                    </label>
                    <input type="text" name="group_name"
                           value="{{ old('group_name') }}"
                           placeholder="Ex: Famille Nkomo, Séminaire UNESCO..."
                           required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary placeholder-primary/30">
                    @error('group_name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Type d'événement *
                    </label>
                    <select name="event_type" required
                            class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        <option value="">Sélectionner...</option>
                        <option value="family"     {{ old('event_type') === 'family'     ? 'selected' : '' }}>Famille</option>
                        <option value="corporate"  {{ old('event_type') === 'corporate'  ? 'selected' : '' }}>Corporate / Séminaire</option>
                        <option value="wedding"    {{ old('event_type') === 'wedding'    ? 'selected' : '' }}>Mariage</option>
                        <option value="tour_group" {{ old('event_type') === 'tour_group' ? 'selected' : '' }}>Tour groupe</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Date d'arrivée *
                    </label>
                    <input type="date" name="start_date"
                           value="{{ old('start_date') }}"
                           min="{{ now()->format('Y-m-d') }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    @error('start_date')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Date de départ *
                    </label>
                    <input type="date" name="end_date"
                           value="{{ old('end_date') }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    @error('end_date')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-6 pt-4 border-t border-secondary/20">
                <h3 class="text-sm font-semibold text-primary mb-3 flex items-center gap-2">
                    Acompte initial (Optionnel)
                </h3>
                <p class="text-xs text-primary/60 mb-3">Pour un dossier groupe, vous pouvez enregistrer une avance globale maintenant.</p>
                <div class="grid grid-cols-3 gap-4 p-4 border border-primary/20 bg-primary/5 rounded-lg">
                    <div>
                        <label class="block text-xs font-semibold text-primary/70 mb-1">Montant versé</label>
                        <div class="relative">
                            <input type="number" name="deposit_amount" value="{{ old('deposit_amount') }}" min="0" class="w-full px-3 py-2 text-sm font-bold border border-secondary/30 rounded-lg text-primary outline-none focus:border-primary pr-12">
                            <span class="absolute right-3 top-2.5 text-xs text-primary/50 font-medium">FCFA</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-primary/70 mb-1">Moyen de paiement</label>
                        <select name="payment_method" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                            <option value="">Sélectionner...</option>
                            <option value="orange_money">Orange Money</option>
                            <option value="mtn_momo">MTN Mobile Money</option>
                            <option value="cash">Espèces</option>
                            <option value="bank_transfer">Virement Bancaire</option>
                            <option value="check">Chèque</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-primary/70 mb-1">Référence transaction</label>
                        <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="N° transaction..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Notes
                </label>
                <textarea name="notes" rows="3"
                          placeholder="Besoins spéciaux, programme, remarques..."
                          class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary resize-none placeholder-primary/30">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('groups.index') }}"
                   class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                    Annuler
                </a>
                <button type="submit" id="submit-btn"
                        class="px-5 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Créer le dossier groupe
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Le composant x-customer-search gère la sélection du contact
</script>

@endsection
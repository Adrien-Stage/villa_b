@extends('layouts.hotel')

@section('title', 'Modifier le client : ' . $customer->full_name)

@section('content')
<div class="max-w-3xl mx-auto">
    {{-- Retour --}}
    <a href="{{ route('customers.show', $customer) }}"
       class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-5">
        <i data-lucide="arrow-left" class="w-3 h-3"></i>
        Retour au profil
    </a>

    <div class="mb-6">
        <h1 class="font-heading text-2xl font-semibold text-primary">Modifier les informations du client</h1>
        <p class="text-sm text-primary/60 mt-1">Mettez à jour les coordonnées, les documents officiels et le statut du client.</p>
    </div>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('customers.update', $customer) }}" class="bg-white rounded-xl shadow-sm border border-secondary/20 p-6 space-y-6">
        @csrf
        @method('PUT')

        {{-- Section Identité --}}
        <div>
            <h3 class="text-sm font-semibold text-primary mb-4 pb-2 border-b border-secondary/10">1. Identité & Coordonnées</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Prénom *</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Nom de famille *</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Adresse e-mail</label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Nationalité</label>
                    <input type="text" name="nationality" value="{{ old('nationality', $customer->nationality) }}" placeholder="Ex: Camerounaise, Française..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Date de naissance</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer->date_of_birth ? $customer->date_of_birth->format('Y-m-d') : '') }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>
        </div>

        {{-- Section Documents --}}
        <div>
            <h3 class="text-sm font-semibold text-primary mb-4 pb-2 border-b border-secondary/10">2. Documents d'identité</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Type de document</label>
                    <select name="id_document_type" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        <option value="" {{ old('id_document_type', $customer->id_document_type) == '' ? 'selected' : '' }}>Sélectionnez un document</option>
                        <option value="CNI" {{ old('id_document_type', $customer->id_document_type) == 'CNI' ? 'selected' : '' }}>Carte Nationale d'Identité (CNI)</option>
                        <option value="Passeport" {{ old('id_document_type', $customer->id_document_type) == 'Passeport' ? 'selected' : '' }}>Passeport</option>
                        <option value="Permis" {{ old('id_document_type', $customer->id_document_type) == 'Permis' ? 'selected' : '' }}>Permis de conduire</option>
                        <option value="CarteSejour" {{ old('id_document_type', $customer->id_document_type) == 'CarteSejour' ? 'selected' : '' }}>Carte de séjour</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Numéro du document</label>
                    <input type="text" name="id_document_number" value="{{ old('id_document_number', $customer->id_document_number) }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>
        </div>

        {{-- Section Adresse géographique --}}
        <div>
            <h3 class="text-sm font-semibold text-primary mb-4 pb-2 border-b border-secondary/10">3. Adresse géographique</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Adresse de résidence</label>
                    <input type="text" name="address" value="{{ old('address', $customer->address) }}" placeholder="Rue, Quartier..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary/70 mb-1.5">Ville</label>
                    <input type="text" name="city" value="{{ old('city', $customer->city) }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>
        </div>

        {{-- Section Statut et Notes --}}
        <div>
            <h3 class="text-sm font-semibold text-primary mb-4 pb-2 border-b border-secondary/10">4. Statut & Notes internes</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div class="flex items-start gap-3 p-3 bg-yellow-50/50 border border-yellow-200/50 rounded-lg">
                    <input type="checkbox" id="is_vip" name="is_vip" value="1" {{ old('is_vip', $customer->is_vip) ? 'checked' : '' }} class="mt-1 rounded border-secondary/30 text-yellow-600 focus:ring-yellow-500 h-4 w-4">
                    <div>
                        <label for="is_vip" class="text-xs font-semibold text-yellow-800">Client VIP</label>
                        <p class="text-[10px] text-yellow-700/70">Cochez si ce client bénéficie d'un accueil personnalisé de prestige.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 bg-red-50/50 border border-red-200/50 rounded-lg">
                    <input type="checkbox" id="is_blacklisted" name="is_blacklisted" value="1" {{ old('is_blacklisted', $customer->is_blacklisted) ? 'checked' : '' }} class="mt-1 rounded border-secondary/30 text-red-600 focus:ring-red-500 h-4 w-4">
                    <div>
                        <label for="is_blacklisted" class="text-xs font-semibold text-red-800">Blacklisté</label>
                        <p class="text-[10px] text-red-700/70">Restreint les réservations futures de ce client (problèmes comportementaux, impayés).</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-primary/70 mb-1.5">Notes internes (Jamais visibles pour le client)</label>
                <textarea name="notes" rows="4" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary resize-none" placeholder="Ajoutez des notes importantes pour les équipes de réception...">{{ old('notes', $customer->notes) }}</textarea>
            </div>
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-secondary/10 flex justify-end gap-3">
            <a href="{{ route('customers.show', $customer) }}" class="px-5 py-2.5 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors shadow-sm">
                Annuler
            </a>
            <button type="submit" class="px-5 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                Enregistrer les modifications
            </button>
        </div>
    </form>
</div>
@endsection

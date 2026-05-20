@extends('layouts.hotel')

@section('title', 'Fermeture de Caisse')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{
    theoretical: Math.round({{ $theoretical_amount / 100 }}),
    actual: 0,
    get gap() {
        return this.actual - this.theoretical;
    },
    formatPrice(val) {
        return new Intl.NumberFormat('fr-FR').format(val) + ' FCFA';
    }
}">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-heading font-bold text-primary flex items-center gap-2">
                <i data-lucide="lock" class="w-7 h-7 text-red-500"></i>
                Fermeture de Caisse
            </h1>
            <p class="text-sm text-secondary mt-1 ml-9">Contrôle de fin de service</p>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 mb-6 text-sm text-green-800 bg-green-50 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Bilan théorique -->
        <div>
            <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-6 mb-6">
                <h2 class="text-lg font-heading font-semibold text-primary mb-4 border-b border-secondary/10 pb-2">Bilan Théorique</h2>
                
                <div class="space-y-3 pt-2">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-primary/70">Fond de caisse initial :</span>
                        <span class="font-medium">{{ number_format($session->opening_amount / 100, 0, ',', ' ') }} FCFA</span>
                    </div>
                    <div class="flex justify-between items-center text-sm text-green-600">
                        <span>+ Ventes Cash encaissées :</span>
                        <span class="font-medium">{{ number_format($cash_orders_total / 100, 0, ',', ' ') }} FCFA</span>
                    </div>
                    <div class="flex justify-between items-center text-sm text-red-500">
                        <span>- Sorties (Décaissements) :</span>
                        <span class="font-medium">{{ number_format($disbursements_total / 100, 0, ',', ' ') }} FCFA</span>
                    </div>
                </div>

                <div class="border-t border-secondary/10 mt-4 pt-4 flex justify-between items-end">
                    <span class="font-semibold text-primary">Solde Théorique Attendu</span>
                    <span class="text-xl font-heading font-bold text-primary">{{ number_format($theoretical_amount / 100, 0, ',', ' ') }} FCFA</span>
                </div>
            </div>

            <!-- Ajout manuel de décaissement avant de fermer -->
            <div class="bg-red-50/50 rounded-xl shadow-sm border border-red-100 p-6">
                <h3 class="text-sm font-semibold text-red-800 mb-3 flex items-center">
                    <i data-lucide="minus-circle" class="w-4 h-4 mr-1"></i> Signaler une sortie d'argent
                </h3>
                <form action="{{ route('shop.cash_register.disbursements.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <input type="text" name="reason" required placeholder="Motif (ex: Achat fournitures)" class="w-full px-3 py-2 text-sm border border-red-200 rounded-lg bg-white">
                        </div>
                        <div class="md:col-span-1">
                            <input type="number" name="amount" required placeholder="Montant" class="w-full px-3 py-2 text-sm border border-red-200 rounded-lg bg-white">
                        </div>
                    </div>
                    <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-800 px-3 py-2 rounded-lg font-medium transition-colors">
                        Ajouter la sortie
                    </button>
                </form>
            </div>
        </div>

        <!-- Formulaire de clôture -->
        <div>
            <form action="{{ route('shop.cash_register.close.store') }}" method="POST">
                @csrf
                <input type="hidden" name="theoretical_closing_amount" value="{{ $theoretical_amount }}">
                
                <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-6 sticky top-6">
                    <h2 class="text-lg font-heading font-semibold text-primary mb-6 border-b border-secondary/10 pb-2">Comptage de la caisse</h2>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Solde Réel compté (FCFA) *</label>
                            <input type="number" name="actual_closing_amount" x-model.number="actual" required min="0" step="1"
                                class="w-full px-4 py-4 text-2xl font-bold bg-accent/5 border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary transition-colors text-center"
                                placeholder="0">
                            <p class="text-xs text-primary/50 mt-2 text-center">Comptez physiquement l'argent du tiroir-caisse</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 text-center border" :class="gap === 0 ? 'border-green-200 bg-green-50' : (gap > 0 ? 'border-yellow-200 bg-yellow-50' : 'border-red-200 bg-red-50')">
                            <p class="text-xs tracking-wider uppercase font-semibold text-primary/50 mb-1">Écart constaté</p>
                            <p class="text-xl font-bold" :class="gap === 0 ? 'text-green-600' : (gap > 0 ? 'text-yellow-600' : 'text-red-600')" x-text="(gap > 0 ? '+' : '') + formatPrice(gap)"></p>
                            
                            <p class="text-xs mt-2" x-show="gap === 0" style="display: none;">
                                <i data-lucide="check-circle" class="w-3 h-3 inline text-green-600"></i> Caisse juste. Tout est parfait.
                            </p>
                            <p class="text-xs mt-2 text-red-600" x-show="gap < 0" style="display: none;">
                                <i data-lucide="alert-triangle" class="w-3 h-3 inline text-red-600"></i> Manquant en caisse (perte).
                            </p>
                            <p class="text-xs mt-2 text-yellow-600" x-show="gap > 0" style="display: none;">
                                <i data-lucide="alert-circle" class="w-3 h-3 inline text-yellow-600"></i> Excédent en caisse.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Observation (Optionnel)</label>
                            <textarea name="closing_notes" rows="2"
                                class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary transition-colors resize-none placeholder-primary/30" 
                                placeholder="Justification de l'écart ou remarque..."></textarea>
                        </div>

                        <div class="pt-4 border-t border-secondary/10">
                            <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-xl font-medium transition-colors shadow-sm flex justify-center items-center gap-2">
                                <i data-lucide="lock" class="w-5 h-5"></i>
                                Valider et fermer la caisse
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

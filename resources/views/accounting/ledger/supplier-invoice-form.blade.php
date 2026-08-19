@extends('layouts.hotel')

@section('title', 'Saisir une facture fournisseur')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Saisir une facture fournisseur</h1>
    <p class="text-sm text-primary/60 mt-1">Le montant TTC suffit — le reste se déduit</p>
</div>

@include('accounting.ledger.partials.nav')

@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc pl-5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

@unless($confirme)
    {{-- Les taux viennent du document béninois cité au plan. Les appliquer
         sans confirmation expose à un redressement : le dire coûte moins cher
         que de le découvrir au contrôle. --}}
    <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-[11px] text-amber-900 leading-relaxed">
        <strong>Taux de retenue non confirmés.</strong> Les taux proposés ci-dessous
        (5 %, 10 %, 15 %) sont des valeurs par défaut issues d'un document de référence
        <em>béninois</em>. Ils attendent la validation du cabinet pour le Cameroun.
        <span class="block mt-1">
            Ils restent modifiables dans les réglages fiscaux, et chaque facture conserve
            le taux qui lui a été appliqué — une correction future ne réécrira pas le passé.
        </span>
    </div>
@endunless

<div class="px-4 py-3 mb-5 rounded-xl bg-accent/20 border border-secondary/20 text-[11px] text-primary/70 leading-relaxed">
    <strong>La retenue n'est pas une remise.</strong> Ce que vous retenez n'est pas gagné :
    c'est une somme prélevée pour le compte de l'État, à reverser. Le fournisseur reste
    créancier du montant total — il touche seulement le net.
    <span class="block mt-1">
        Assiette : le <strong>hors taxes</strong>. Retenir sur le TTC prélèverait sur une TVA
        que le fournisseur ne fait que collecter.
    </span>
</div>

<form method="POST" action="{{ route('accounting.ledger.suppliers.store') }}"
      x-data="factureFournisseur({{ $tvaBp }}, {{ Illuminate\Support\Js::from($taux) }})">
    @csrf

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl border border-secondary/20 shadow-sm p-5 space-y-4">
                <h2 class="text-sm font-semibold text-primary">Document reçu</h2>

                @if($bons->isNotEmpty())
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Bon de commande d'origine <span class="text-primary/40">(facultatif)</span></label>
                        <select name="purchase_order_id" class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                            <option value="">Aucun — facture directe</option>
                            @foreach($bons as $b)
                                <option value="{{ $b->id }}" @selected(old('purchase_order_id', $bon?->id) == $b->id)>
                                    {{ $b->number }} — {{ $b->supplier?->name }} ({{ $fcfa($b->total_amount) }} FCFA)
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-primary/45 mt-1">Bons réceptionnés et pas encore intégralement facturés.</p>
                    </div>
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Fournisseur</label>
                        <select name="supplier_id" required class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                            <option value="">Choisir…</option>
                            @foreach($fournisseurs as $f)
                                <option value="{{ $f->id }}" @selected(old('supplier_id', $bon?->supplier_id) == $f->id)>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Référence de la facture</label>
                        <input type="text" name="number" required maxlength="60" value="{{ old('number') }}"
                               placeholder="FA-2026-0142"
                               class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5 font-mono">
                        <p class="text-[11px] text-primary/45 mt-1">Celle du fournisseur, pas la vôtre.</p>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Date de facture</label>
                        <input type="date" name="invoice_date" required value="{{ old('invoice_date', now()->toDateString()) }}"
                               class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Échéance <span class="text-primary/40">(facultatif)</span></label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}"
                               class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Libellé</label>
                    <input type="text" name="label" required maxlength="255" value="{{ old('label') }}"
                           placeholder="Approvisionnement économat — juin"
                           class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                </div>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 shadow-sm p-5 space-y-4">
                <h2 class="text-sm font-semibold text-primary">Imputation et montants</h2>

                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Nature de la charge</label>
                    <select name="charge_account" required class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                        @foreach(\App\Models\SupplierInvoice::CHARGE_ACCOUNTS as $code => $libelle)
                            <option value="{{ $code }}" @selected(old('charge_account', '601000') === $code)>
                                {{ $code }} — {{ $libelle }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Montant TTC (FCFA)</label>
                        <input type="number" name="amount_ttc" required min="1" step="1"
                               x-model.number="ttc" value="{{ old('amount_ttc') }}"
                               class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Retenue à la source</label>
                        <select name="withholding_type" x-model="type"
                                class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                            <option value="">Aucune</option>
                            @foreach(\App\Models\SupplierInvoice::WITHHOLDING_TYPES as $cle => $libelle)
                                <option value="{{ $cle }}" @selected(old('withholding_type') === $cle)>
                                    {{ $libelle }} — {{ rtrim(rtrim(number_format($taux[$cle] / 100, 2, ',', ''), '0'), ',') }} %
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Notes <span class="text-primary/40">(facultatif)</span></label>
                    <textarea name="notes" rows="2" maxlength="1000"
                              class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Aperçu : ce qui sera écrit au grand livre, calculé pendant la saisie. --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-secondary/20 shadow-sm p-5 lg:sticky lg:top-4">
                <h2 class="text-sm font-semibold text-primary mb-3">Décomposition</h2>

                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-primary/60">Base hors taxes</dt>
                        <dd class="font-mono text-primary" x-text="format(ht)">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-primary/60">TVA récupérable</dt>
                        <dd class="font-mono text-primary" x-text="format(tva)">0</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-secondary/15">
                        <dt class="text-primary/70 font-medium">Total TTC</dt>
                        <dd class="font-mono font-semibold text-primary" x-text="format(centimes)">0</dd>
                    </div>
                    <div class="flex justify-between" x-show="retenue > 0" x-cloak>
                        <dt class="text-amber-700">Retenue à la source</dt>
                        <dd class="font-mono text-amber-700" x-text="'− ' + format(retenue)">0</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-secondary/15">
                        <dt class="text-primary font-semibold">Net à payer</dt>
                        <dd class="font-mono font-bold text-primary" x-text="format(net)">0</dd>
                    </div>
                </dl>

                <div class="mt-4 pt-4 border-t border-secondary/15">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40 mb-2">Écriture générée</p>
                    <div class="space-y-1 text-[11px] font-mono text-primary/60">
                        <div class="flex justify-between"><span>D 6xx</span><span x-text="format(ht)"></span></div>
                        <div class="flex justify-between" x-show="tva > 0" x-cloak><span>D 445100</span><span x-text="format(tva)"></span></div>
                        <div class="flex justify-between"><span>C 401000</span><span x-text="format(net)"></span></div>
                        <div class="flex justify-between" x-show="retenue > 0" x-cloak><span>C 442100</span><span x-text="format(retenue)"></span></div>
                    </div>
                </div>

                <button type="submit"
                        class="w-full mt-5 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i>
                    Enregistrer et comptabiliser
                </button>
                <a href="{{ route('accounting.ledger.suppliers') }}"
                   class="block text-center mt-2 text-xs text-primary/50 hover:text-primary transition-colors">Annuler</a>
            </div>
        </div>
    </div>
</form>

<script>
    function factureFournisseur(tvaBp, taux) {
        return {
            ttc: null,
            type: '',
            // Le champ est saisi en francs ; tout le calcul se fait en centimes,
            // comme au serveur. Arrondir sur les francs ferait dériver l'aperçu
            // d'une unité par rapport à l'écriture réellement produite.
            get centimes() {
                return Math.round((Number(this.ttc) || 0) * 100);
            },
            // Mêmes règles qu'au serveur : la base est arrondie, la taxe est
            // déduite par différence.
            get ht() {
                if (!this.centimes || !tvaBp) return this.centimes;
                const diviseur = 10000 + tvaBp;
                return Math.floor((this.centimes * 10000 + Math.floor(diviseur / 2)) / diviseur);
            },
            get tva() {
                return this.centimes - this.ht;
            },
            get retenue() {
                const bp = taux[this.type] || 0;
                if (!bp || !this.ht) return 0;
                return Math.floor((this.ht * bp + 5000) / 10000);
            },
            get net() {
                return this.centimes - this.retenue;
            },
            format(centimes) {
                return new Intl.NumberFormat('fr-FR').format((centimes || 0) / 100);
            },
        };
    }
</script>
@endsection

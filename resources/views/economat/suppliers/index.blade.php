@extends('layouts.hotel')

@section('title', 'Fournisseurs — Économat')

@section('content')
<div class="max-w-6xl mx-auto" x-data="suppliers()">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Fournisseurs</h1>
            <p class="text-sm text-primary/60 mt-0.5">Leur adresse email permet l'envoi automatique des bons de commande.</p>
        </div>
        <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouveau fournisseur
        </button>
    </div>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden">
        @if($suppliers->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-primary/40">Aucun fournisseur. Ajoutez-en un pour créer des bons de commande.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Fournisseur</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Contact</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-primary/50">Bons</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($suppliers as $supplier)
                            @php
                                // Précalculé : un @json inline multi-clés dans @click casse Blade.
                                $editPayload = [
                                    'id' => $supplier->id, 'name' => $supplier->name, 'code' => $supplier->code,
                                    'contact_name' => $supplier->contact_name, 'email' => $supplier->email,
                                    'phone' => $supplier->phone, 'address' => $supplier->address,
                                    'notes' => $supplier->notes, 'is_active' => (bool) $supplier->is_active,
                                ];
                            @endphp
                            <tr class="{{ $supplier->is_active ? '' : 'opacity-50' }}">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-primary">{{ $supplier->name }}
                                        @if($supplier->code)<span class="ml-1 text-[10px] font-mono text-primary/40">{{ $supplier->code }}</span>@endif
                                    </p>
                                    @unless($supplier->canReceiveOrdersByEmail())
                                        <span class="inline-flex items-center gap-1 text-[10px] text-amber-600"><i data-lucide="mail-x" class="w-3 h-3"></i> sans email</span>
                                    @endunless
                                </td>
                                <td class="px-5 py-3 text-xs text-primary/60">
                                    @if($supplier->contact_name)<p>{{ $supplier->contact_name }}</p>@endif
                                    @if($supplier->email)<p class="text-primary/40">{{ $supplier->email }}</p>@endif
                                    @if($supplier->phone)<p class="text-primary/40">{{ $supplier->phone }}</p>@endif
                                </td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ $supplier->stock_items_count }}</td>
                                <td class="px-5 py-3 text-right text-primary/70">{{ $supplier->purchase_orders_count }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:bg-accent/20">
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <form method="POST" action="{{ route('economat.suppliers.destroy', $supplier) }}" onsubmit="return confirm('Supprimer « {{ $supplier->name }} » ?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,2,1,0.5); backdrop-filter:blur(4px);">
        <div class="absolute inset-0" @click="open = false"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
                <h3 class="font-heading font-semibold text-primary" x-text="editing ? 'Modifier le fournisseur' : 'Nouveau fournisseur'"></h3>
                <button type="button" @click="open = false" class="text-primary/30 hover:text-primary"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                <div class="px-6 py-5 space-y-4 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Nom <span class="text-red-500">*</span></label>
                            <input type="text" name="name" x-model="form.name" @input="applyAutoCode()" required maxlength="160" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Code</label>
                            <input type="text" name="code" x-model="form.code" @input="autoCode = false" maxlength="30" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Contact</label>
                            <input type="text" name="contact_name" x-model="form.contact_name" maxlength="120" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Téléphone</label>
                            <input type="text" name="phone" x-model="form.phone" maxlength="30" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Email <span class="text-primary/30">(pour l'envoi des bons)</span></label>
                        <input type="email" name="email" x-model="form.email" maxlength="150" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Adresse</label>
                        <input type="text" name="address" x-model="form.address" maxlength="255" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Notes</label>
                        <textarea name="notes" x-model="form.notes" rows="2" maxlength="1000" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary"></textarea>
                    </div>
                    <label class="flex items-center gap-2.5 px-3 py-2.5 border border-secondary/30 rounded-lg cursor-pointer">
                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                        <input type="checkbox" x-model="form.is_active" class="w-4 h-4 rounded border-secondary/40 text-primary">
                        <span class="text-xs text-primary/80">Fournisseur actif</span>
                    </label>
                </div>
                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark"><span x-text="editing ? 'Enregistrer' : 'Créer'"></span></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function suppliers() {
        const storeUrl = @js(route('economat.suppliers.store'));
        const baseUrl = @js(url('/economat/fournisseurs'));
        return {
            open: false, editing: false, formAction: storeUrl, form: {},
            autoCode: true,
            blank() { return { id: null, name: '', code: '', contact_name: '', email: '', phone: '', address: '', notes: '', is_active: true }; },
            // Le code suit le nom tant qu'il n'a pas été édité à la main.
            applyAutoCode() { if (this.autoCode) this.form.code = window.suggestCode(this.form.name || ''); },
            openCreate() { this.form = this.blank(); this.autoCode = true; this.editing = false; this.formAction = storeUrl; this.open = true; },
            openEdit(s) {
                this.form = { ...this.blank(), ...s, code: s.code ?? '', contact_name: s.contact_name ?? '', email: s.email ?? '', phone: s.phone ?? '', address: s.address ?? '', notes: s.notes ?? '' };
                this.autoCode = false;
                this.editing = true; this.formAction = `${baseUrl}/${s.id}`; this.open = true;
            },
        };
    }
</script>
@endpush

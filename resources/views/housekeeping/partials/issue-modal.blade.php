{{--
    Modale de signalement de problème / incident par le personnel housekeeping.
    Permet au valet / femme de chambre / encadrement de qualifier immédiatement
    si l'incident est un problème technique (bascule automatique en maintenance)
    ou un besoin interne au service des étages (savon, linge, consommables, etc.).
--}}
<div x-data="{
        open: false,
        roomId: null,
        roomNumber: '',
        actionUrl: '',
        issueType: 'interne',
        specialty: 'plomberie',
        priority: 'high',
        notes: '',
        openModal(detail) {
            this.roomId = detail.roomId;
            this.roomNumber = detail.roomNumber;
            this.actionUrl = detail.actionUrl;
            this.issueType = detail.defaultType || 'interne';
            this.specialty = 'autre';
            this.priority = 'high';
            this.notes = '';
            this.open = true;
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
                if (this.$refs.notesField) this.$refs.notesField.focus();
            });
        },
        closeModal() {
            this.open = false;
        }
    }"
    @open-issue-modal.window="openModal($event.detail)"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true">

    {{-- Arrière-plan sombre avec flou --}}
    <div class="fixed inset-0 bg-primary/60 backdrop-blur-xs transition-opacity"
         @click="closeModal()"
         x-show="open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div x-show="open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl border border-secondary/20">

            {{-- Formulaire de soumission --}}
            <form :action="actionUrl" method="POST">
                @csrf

                {{-- En-tête de la modale --}}
                <div class="px-6 py-4 border-b border-secondary/20 flex items-center justify-between bg-surface-light">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                             :class="issueType === 'technique' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'">
                            <i data-lucide="alert-triangle" class="w-5 h-5" x-show="issueType === 'technique'"></i>
                            <i data-lucide="sparkles" class="w-5 h-5" x-show="issueType === 'interne'"></i>
                        </div>
                        <div>
                            <h3 class="font-heading font-bold text-primary text-base" id="modal-title">
                                Signaler un problème — Chambre <span class="text-secondary font-black" x-text="roomNumber"></span>
                            </h3>
                            <p class="text-xs text-primary/60">
                                Qualifiez la nature de l'incident pour orienter l'intervention.
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="closeModal()" class="text-primary/40 hover:text-primary transition-colors p-1.5 rounded-lg hover:bg-gray-100">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    {{-- 1. Choix du type de problème (Cartes interactives) --}}
                    <div>
                        <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-2">
                            Type de signalement <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- Option A : Problème Technique --}}
                            <label class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all text-left"
                                   :class="issueType === 'technique'
                                       ? 'border-red-500 bg-red-50/50 shadow-xs ring-1 ring-red-500'
                                       : 'border-secondary/20 hover:border-red-300 bg-white hover:bg-red-50/20'">
                                <input type="radio" name="issue_type" value="technique" x-model="issueType" class="sr-only">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-red-100 text-red-700 flex items-center justify-center shrink-0">
                                            <i data-lucide="wrench" class="w-4 h-4"></i>
                                        </div>
                                        <span class="font-semibold text-xs text-red-950">Problème technique</span>
                                    </div>
                                    <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                                          :class="issueType === 'technique' ? 'border-red-600 bg-red-600' : 'border-gray-300'">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white" x-show="issueType === 'technique'"></span>
                                    </span>
                                </div>
                                <p class="text-[11px] text-primary/70 leading-relaxed mb-2">
                                    Panne, fuite, électricité, clim, serrure, télévision, casse…
                                </p>
                                <span class="mt-auto inline-flex items-center gap-1 text-[10px] font-bold text-red-700 bg-red-100/80 px-2 py-0.5 rounded">
                                    <i data-lucide="lock" class="w-3 h-3"></i> Passe en maintenance auto
                                </span>
                            </label>

                            {{-- Option B : Besoin Interne Housekeeping --}}
                            <label class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all text-left"
                                   :class="issueType === 'interne'
                                       ? 'border-amber-500 bg-amber-50/50 shadow-xs ring-1 ring-amber-500'
                                       : 'border-secondary/20 hover:border-amber-300 bg-white hover:bg-amber-50/20'">
                                <input type="radio" name="issue_type" value="interne" x-model="issueType" class="sr-only">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                                            <i data-lucide="package-search" class="w-4 h-4"></i>
                                        </div>
                                        <span class="font-semibold text-xs text-amber-950">Besoin interne étages</span>
                                    </div>
                                    <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                                          :class="issueType === 'interne' ? 'border-amber-600 bg-amber-600' : 'border-gray-300'">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white" x-show="issueType === 'interne'"></span>
                                    </span>
                                </div>
                                <p class="text-[11px] text-primary/70 leading-relaxed mb-2">
                                    Savon ou produits finis, linge manquant, réassort chariot…
                                </p>
                                <span class="mt-auto inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100/80 px-2 py-0.5 rounded">
                                    <i data-lucide="check" class="w-3 h-3"></i> Reste en cycle ménage
                                </span>
                            </label>
                        </div>
                    </div>

                    {{-- Explication contextuelle claire --}}
                    <div x-show="issueType === 'technique'" class="p-3 rounded-xl bg-red-50 border border-red-200 text-left">
                        <div class="flex items-start gap-2.5">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-red-600 shrink-0 mt-0.5"></i>
                            <div class="text-xs text-red-900 leading-relaxed">
                                <span class="font-semibold">Mise en maintenance automatique :</span>
                                La chambre <span class="font-bold" x-text="roomNumber"></span> sera immédiatement retirée de la vente et marquée en Maintenance. Le service technique sera saisi pour intervention.
                            </div>
                        </div>
                    </div>

                    <div x-show="issueType === 'interne'" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-left">
                        <div class="flex items-start gap-2.5">
                            <i data-lucide="info" class="w-4 h-4 text-amber-800 shrink-0 mt-0.5"></i>
                            <div class="text-xs text-amber-900 leading-relaxed">
                                <span class="font-semibold">Gestion interne Housekeeping :</span>
                                La chambre reste dans son cycle normal et <span class="font-bold">ne passe pas en maintenance</span>. La gouvernante est alertée pour vous approvisionner en consommables (savon, linge, etc.).
                            </div>
                        </div>
                    </div>

                    {{-- Champs complémentaires spécifiques au problème technique --}}
                    <div x-show="issueType === 'technique'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left">
                        <div>
                            <label class="block text-xs font-semibold text-primary mb-1">Spécialité technique</label>
                            <select name="specialty" x-model="specialty" class="w-full text-xs rounded-lg border border-secondary/30 px-3 py-2 bg-white focus:border-primary outline-none">
                                <option value="plomberie">Plomberie / Sanitaires</option>
                                <option value="electricite">Électricité / Éclairage</option>
                                <option value="climatisation">Climatisation / Chauffage</option>
                                <option value="serrurerie">Serrurerie / Portes / Accès</option>
                                <option value="menuiserie">Menuiserie / Mobilier</option>
                                <option value="peinture">Peinture / Murs / Sols</option>
                                <option value="multimedia">TV / Multimédia / Téléphone</option>
                                <option value="autre">Autre spécialité</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-primary mb-1">Priorité</label>
                            <select name="priority" x-model="priority" class="w-full text-xs rounded-lg border border-secondary/30 px-3 py-2 bg-white focus:border-primary outline-none">
                                <option value="normal">Normale</option>
                                <option value="high" selected>Haute</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>

                    {{-- 2. Description détaillée --}}
                    <div class="text-left">
                        <label for="issue_notes_input" class="block text-xs font-semibold text-primary mb-1">
                            Description du problème ou besoin constaté <span class="text-red-500">*</span>
                        </label>
                        <textarea id="issue_notes_input"
                                  name="issue_notes"
                                  x-ref="notesField"
                                  x-model="notes"
                                  rows="3"
                                  required
                                  maxlength="1000"
                                  :placeholder="issueType === 'technique'
                                      ? 'Ex : Robinet du lavabo fuit abondamment, climatisation ne s\'allume plus, poignée de porte arrachée…'
                                      : 'Ex : Le savon et le gel douche sont finis dans cette chambre, manque 2 serviettes de bain…'"
                                  class="w-full rounded-xl border border-secondary/30 px-3.5 py-2.5 text-xs text-primary focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-primary/40"></textarea>
                        <p class="text-[11px] text-primary/50 mt-1">
                            Soyez précis pour permettre une intervention ou un approvisionnement rapide.
                        </p>
                    </div>
                </div>

                {{-- Pied de modale avec boutons --}}
                <div class="px-6 py-4 border-t border-secondary/20 flex flex-wrap items-center justify-between gap-3 bg-gray-50 rounded-b-2xl">
                    <button type="button"
                            @click="closeModal()"
                            class="px-4 py-2 text-xs font-medium text-primary/60 hover:text-primary transition-colors">
                        Annuler
                    </button>
                    <button type="submit"
                            :disabled="!notes || !notes.trim()"
                            :class="(!notes || !notes.trim())
                                ? 'opacity-50 cursor-not-allowed bg-primary/60 text-white'
                                : (issueType === 'technique' ? 'bg-red-600 hover:bg-red-700 text-white shadow-xs' : 'bg-amber-600 hover:bg-amber-700 text-white shadow-xs')"
                            class="px-4 py-2 text-xs font-semibold rounded-lg transition-all inline-flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span x-text="issueType === 'technique' ? 'Confirmer (Passer en Maintenance)' : 'Signaler le besoin interne'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

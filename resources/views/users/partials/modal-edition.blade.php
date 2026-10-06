{{--
    Fenêtre de modification d'un compte, sur la liste comme sur la fiche.
    $staff : le compte ; $viewMode : présentation de la liste à retrouver ;
    $retour : « fiche » pour revenir à la fiche après l'enregistrement.

    Le mot de passe ne se modifie pas ici : il se réinitialise (action
    « Réinitialiser le mot de passe »), et la personne choisit le sien.
    Les valeurs saisies ne reviennent qu'à la fenêtre qui les a envoyées.
--}}
@php
    $contexteEdition = 'edit_' . $staff->id;
    $repris = old('form_type') === $contexteEdition;
    $valeur = fn (string $champ, $defaut) => $repris ? old($champ, $defaut) : $defaut;
    // Rôles et niveaux actuels, pour pré-cocher les cartes.
    $staffRoleSlugs = $staff->roles->pluck('slug')->all();
    $staffLevels = $staff->roles->mapWithKeys(fn ($r) => [$r->slug => $r->pivot->level ?: 'write'])->all();
@endphp
<x-modal id="edit-user-modal-{{ $staff->id }}" title="Modifier {{ $staff->name }}" max-width="max-w-2xl" formAction="{{ route('users.update', $staff) }}" closeAction="closeEditModal('{{ $staff->id }}')">
    @method('PUT')
    <input type="hidden" name="form_type" value="{{ $contexteEdition }}">
    <input type="hidden" name="view" value="{{ $viewMode ?? 'list' }}">
    @isset($retour)<input type="hidden" name="retour" value="{{ $retour }}">@endisset

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="edit-{{ $staff->id }}-name" class="text-xs text-primary/60">Nom complet</label>
            <input id="edit-{{ $staff->id }}-name" type="text" name="name" value="{{ $valeur('name', $staff->name) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
        <div>
            <label for="edit-{{ $staff->id }}-email" class="text-xs text-primary/60">Email</label>
            <input id="edit-{{ $staff->id }}-email" type="email" name="email" value="{{ $valeur('email', $staff->email) }}" required class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
        </div>
    </div>

    <div>
        <label for="edit-{{ $staff->id }}-phone" class="text-xs text-primary/60">Téléphone</label>
        <input id="edit-{{ $staff->id }}-phone" type="text" name="phone" value="{{ $valeur('phone', $staff->phone) }}" class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none">
    </div>

    <div>
        <label for="edit-{{ $staff->id }}-dept" class="text-xs font-semibold text-primary">Département d'affectation</label>
        <select id="edit-{{ $staff->id }}-dept" name="department_id"
                onchange="onUserDepartmentSelect(this.value, '{{ $contexteEdition }}')"
                class="mt-1 w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg focus:border-secondary outline-none bg-white">
            <option value="">-- Aucun département --</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" @selected($valeur('department_id', $staff->department_id) == $dept->id)>{{ $dept->name }} ({{ $dept->code ?: 'N/A' }})</option>
            @endforeach
        </select>
    </div>

    @include('users.partials.restaurants', ['contexte' => $contexteEdition, 'departements' => $departments, 'personne' => $staff])

    <div>
        <label class="text-xs text-primary/60">Rôles & niveau d'accès <span class="text-red-500">*</span></label>
        <p class="text-[11px] text-primary/40 mb-2">Cochez un ou plusieurs rôles et leur niveau d'accès par module.</p>
        <x-role-picker :rolesByModule="$rolesByModule" :moduleLabels="$moduleLabels"
                       :selected="$valeur('roles', $staffRoleSlugs)" :levels="$valeur('levels', $staffLevels)"
                       :context="$contexteEdition" />
    </div>

    @include('users.partials.derogation')

    <label class="inline-flex items-center gap-2 text-xs text-primary/70">
        <input type="checkbox" name="is_active" value="1" @checked($valeur('is_active', $staff->is_active))>
        Compte actif
    </label>

    <p class="flex items-start gap-1.5 text-[11px] text-primary/50">
        <i data-lucide="key-round" class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true"></i>
        Le mot de passe ne se modifie pas ici : utilisez « Réinitialiser le mot de passe ».
    </p>

    <x-slot:footer>
        <button type="button" onclick="closeEditModal('{{ $staff->id }}')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
    </x-slot:footer>
</x-modal>

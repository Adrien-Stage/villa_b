{{--
    Mot de passe provisoire tout juste généré : affiché au responsable
    et expédié par e-mail à l'employé avec validité temporaire stricte de 5h.
--}}
@if($provisoire = session('motDePasseProvisoire'))
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status"
         x-data="{ copie: false }">
        <p class="font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Mot de passe de {{ $provisoire['nom'] }} réinitialisé (validité 5h)
        </p>
        <p class="mt-1 text-xs">
            Un e-mail a été envoyé à l'utilisateur avec ses identifiants. Ce mot de passe temporaire est valable pendant <strong>5 heures</strong>. À sa connexion, il devra obligatoirement définir son mot de passe personnel.
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <code class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 font-mono text-base tracking-wider text-primary select-all">{{ $provisoire['valeur'] }}</code>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"
                    @click="navigator.clipboard?.writeText(@js($provisoire['valeur'])).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                <i data-lucide="copy" class="h-3.5 w-3.5" aria-hidden="true"></i>
                <span x-text="copie ? 'Copié' : 'Copier'">Copier</span>
            </button>
        </div>
    </div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ session('error') }}</div>
@endif

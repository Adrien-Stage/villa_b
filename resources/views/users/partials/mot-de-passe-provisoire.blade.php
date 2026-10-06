{{--
    Mot de passe provisoire tout juste généré : affiché une seule fois, au
    responsable qui le remettra à la personne.
--}}
@if($provisoire = session('motDePasseProvisoire'))
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status"
         x-data="{ copie: false }">
        <p class="font-semibold">Mot de passe de {{ $provisoire['nom'] }} réinitialisé</p>
        <p class="mt-1 text-xs">
            Remettez-lui ce mot de passe provisoire. À sa prochaine connexion, il lui sera demandé d'en choisir un nouveau.
            Il ne sera plus affiché après cette page.
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

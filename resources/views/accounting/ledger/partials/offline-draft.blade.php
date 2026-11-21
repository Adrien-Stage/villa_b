<details class="bg-white rounded-xl border border-secondary/20 p-4 mb-5" data-ledger-draft
    @isset($draftScope) data-scope="{{ $draftScope }}" @endisset hidden>
    <summary class="font-semibold cursor-pointer">Brouillon d’opérations diverses hors connexion</summary>
    <p class="mt-2 text-sm">Conservez un brouillon chiffré dans ce navigateur. À la reconnexion, reprenez-le dans le formulaire d’import des échanges comptables.</p>
    <div class="mt-3 grid gap-2">
        <label class="text-sm">Référence unique du lot
            <input data-draft-reference type="text" maxlength="60" autocomplete="off" class="block w-full rounded border-secondary/30">
        </label>
        <label class="text-sm">Contenu CSV du journal
            <textarea data-draft-content rows="6" spellcheck="false" autocomplete="off" class="block w-full rounded border-secondary/30"></textarea>
        </label>
        <label class="text-sm">Phrase secrète du brouillon (12 caractères minimum)
            <input data-draft-password type="password" minlength="12" autocomplete="new-password" class="block w-full rounded border-secondary/30">
        </label>
    </div>
    <div class="flex flex-wrap gap-2 mt-3">
        <button type="button" data-draft-save class="rounded border border-secondary/30 px-3 py-2 text-sm">Conserver le brouillon</button>
        <button type="button" data-draft-load class="rounded border border-secondary/30 px-3 py-2 text-sm">Relire le brouillon</button>
        <button type="button" data-draft-delete class="rounded border border-secondary/30 px-3 py-2 text-sm">Effacer le brouillon</button>
        @isset($draftScope)
            <button type="button" data-draft-resume class="rounded border border-secondary/30 px-3 py-2 text-sm">Reprendre dans le formulaire</button>
        @endisset
    </div>
    <p data-draft-status role="status" class="mt-2 text-sm"></p>
</details>
<script defer src="/pwa/ledger-draft.js"></script>

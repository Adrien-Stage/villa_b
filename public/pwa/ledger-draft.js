(() => {
    const panel = document.querySelector('[data-ledger-draft]');
    if (!panel || !window.crypto?.subtle) return;

    const active = 'wetchah-ledger-draft-scope';
    const scope = panel.dataset.scope || sessionStorage.getItem(active);
    if (!scope) return;
    if (panel.dataset.scope) sessionStorage.setItem(active, scope);

    const key = `wetchah-ledger-draft:${scope}`;
    const input = (name) => panel.querySelector(`[data-draft-${name}]`);
    const encoder = new TextEncoder();
    const decoder = new TextDecoder();
    const header = 'date;piece;compte;libelle;debit_centimes_xaf;credit_centimes_xaf;date_origine;centre';
    input('content').placeholder = header;
    panel.hidden = false;

    async function cipherKey(salt) {
        const passphrase = input('password').value;
        if (passphrase.length < 12) throw new Error('Saisissez la phrase secrète (12 caractères au minimum).');
        const material = await crypto.subtle.importKey('raw', encoder.encode(passphrase), 'PBKDF2', false, ['deriveKey']);
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: 600000, hash: 'SHA-256' },
            material, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt'],
        );
    }

    async function save() {
        const reference = input('reference').value.trim();
        const content = input('content').value.trim();
        if (!reference || content.split(/\r?\n/)[0] !== header || !content.includes('\n')) {
            throw new Error('Renseignez une référence et le contenu CSV complet, en-têtes compris.');
        }
        const plain = encoder.encode(JSON.stringify({ reference, content }));
        if (plain.length > 192000) throw new Error('Le brouillon ne peut pas dépasser 192 Ko.');
        const previous = localStorage.getItem(key);
        if (previous) await decrypt(previous);
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv, additionalData: encoder.encode(scope) }, await cipherKey(salt), plain,
        );
        const bytes = new Uint8Array(encrypted);
        let binary = '';
        for (const byte of bytes) binary += String.fromCharCode(byte);
        localStorage.setItem(key, JSON.stringify({ salt: Array.from(salt), iv: Array.from(iv), bytes: btoa(binary) }));
        input('password').value = '';
        input('content').value = '';
        input('reference').value = '';
        return 'Brouillon chiffré et conservé sur cet appareil.';
    }

    async function decrypt(stored) {
        const { salt, iv, bytes } = JSON.parse(stored);
        const secret = await cipherKey(new Uint8Array(salt));
        try {
            const plain = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: new Uint8Array(iv), additionalData: encoder.encode(scope) },
                secret, Uint8Array.from(atob(bytes), (char) => char.charCodeAt(0)),
            );
            return JSON.parse(decoder.decode(plain));
        } catch {
            throw new Error('La phrase secrète ne permet pas de relire ce brouillon.');
        }
    }

    async function load() {
        const value = localStorage.getItem(key);
        if (!value) throw new Error('Aucun brouillon n’est conservé sur cet appareil.');
        const draft = await decrypt(value);
        input('reference').value = draft.reference;
        input('content').value = draft.content;
        input('password').value = '';
        return 'Brouillon relu.';
    }

    async function resume() {
        await load();
        const target = document.querySelector('[data-ledger-import]');
        if (!target) throw new Error('Reconnectez-vous pour reprendre le brouillon dans Échanges comptables.');
        const transfer = new DataTransfer();
        transfer.items.add(new File([input('content').value], 'operations-diverses.csv', { type: 'text/csv' }));
        target.elements.file.files = transfer.files;
        target.elements.kind.value = 'journal';
        target.elements.reference.value = input('reference').value;
        target.elements.reference.focus();
        return 'Vérifiez le fichier et lancez l’import pour comptabiliser.';
    }

    async function remove() {
        const stored = localStorage.getItem(key);
        if (stored) await decrypt(stored);
        localStorage.removeItem(key);
        input('password').value = '';
        input('content').value = '';
        input('reference').value = '';
        return 'Brouillon effacé.';
    }

    for (const [action, callback] of Object.entries({ save, load, resume, delete: remove })) {
        const button = input(action);
        button?.addEventListener('click', async () => {
            button.disabled = true;
            try {
                input('status').textContent = await callback();
            } catch (error) {
                input('status').textContent = error.message || 'Opération indisponible dans ce navigateur.';
            } finally {
                button.disabled = false;
            }
        });
    }
})();

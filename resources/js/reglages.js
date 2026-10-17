/*
 * Paramètres : un seul bouton « Enregistrer » par onglet.
 *
 * Chaque formulaire de réglage de l'onglet (form[data-reglages]) garde sa
 * route et sa validation côté serveur ; la barre fixe envoie, au clic, chacun
 * de ceux qui ont changé. Les fenêtres d'édition (form[data-reglages-fenetre])
 * ne s'enregistrent plus seules : « Appliquer » met la modification en attente
 * jusqu'au clic sur Enregistrer. Les suppressions partent tout de suite.
 *
 * Quitter l'onglet avec des modifications non enregistrées demande une
 * confirmation : enregistrer, abandonner ou rester.
 *
 * Sans JavaScript, chaque formulaire garde son propre bouton : rien ne se perd.
 */

const ENTETES = {
    'X-Requested-With': 'XMLHttpRequest',
    'X-Enregistrement-Unifie': '1',
    Accept: 'application/json',
};

function empreinte(formulaire) {
    const donnees = new FormData(formulaire);
    const morceaux = [];
    for (const [cle, valeur] of donnees.entries()) {
        if (cle === '_token') continue;
        morceaux.push(cle + '=' + (valeur instanceof File ? `fichier:${valeur.name}:${valeur.size}` : valeur));
    }
    return morceaux.join('&');
}

/** « settings.mail_from_address » → « settings[mail_from_address] ». */
function nomDuChamp(cle) {
    const [premier, ...suite] = cle.split('.');
    return premier + suite.map((s) => `[${s}]`).join('');
}

function demarrer(onglet) {
    const barre = document.getElementById('barre-reglages');
    const formulaires = () => [...onglet.querySelectorAll('form[data-reglages]')];
    const fenetres = [...onglet.querySelectorAll('form[data-reglages-fenetre]')];

    if (! barre || (formulaires().length === 0 && fenetres.length === 0)) {
        return;
    }

    onglet.classList.add('reglages-unifies');
    barre.hidden = false;

    const etat = barre.querySelector('[data-etat]');
    const boutonEnregistrer = barre.querySelector('[data-enregistrer]');
    const boutonAnnuler = barre.querySelector('[data-annuler]');
    const listeAttente = document.getElementById('reglages-en-attente');

    const initiales = new Map();
    formulaires().forEach((f) => initiales.set(f, empreinte(f)));
    const enAttente = []; // modifications appliquées depuis une fenêtre

    const modifies = () => formulaires().filter((f) => empreinte(f) !== initiales.get(f));
    const nombre = () => modifies().length + enAttente.length;

    function rafraichir() {
        const n = nombre();
        etat.textContent = n === 0
            ? 'Aucune modification'
            : `${n} modification${n > 1 ? 's' : ''} non enregistrée${n > 1 ? 's' : ''}`;
        etat.classList.toggle('text-amber-700', n > 0);
        etat.classList.remove('text-red-700');
        boutonEnregistrer.disabled = n === 0;
        boutonAnnuler.hidden = n === 0;

        if (listeAttente) {
            listeAttente.hidden = enAttente.length === 0;
            const liste = listeAttente.querySelector('ul');
            liste.replaceChildren(...enAttente.map((m, i) => {
                const ligne = document.createElement('li');
                ligne.className = 'flex items-center justify-between gap-3';
                const texte = document.createElement('span');
                texte.textContent = m.libelle;
                const retirer = document.createElement('button');
                retirer.type = 'button';
                retirer.dataset.retirer = String(i);
                retirer.className = 'text-xs font-semibold text-red-700 hover:underline';
                retirer.textContent = 'Retirer';
                ligne.append(texte, retirer);
                return ligne;
            }));
        }
    }

    onglet.addEventListener('input', rafraichir);
    onglet.addEventListener('change', rafraichir);
    listeAttente?.addEventListener('click', (e) => {
        const i = e.target.closest('[data-retirer]')?.dataset.retirer;
        if (i !== undefined) {
            enAttente.splice(Number(i), 1);
            rafraichir();
        }
    });

    // Les fenêtres d'édition : « Appliquer » met en attente.
    fenetres.forEach((f) => {
        f.addEventListener('submit', (e) => {
            e.preventDefault();
            if (! f.reportValidity()) return;
            const nom = f.querySelector('[name="name"]')?.value?.trim();
            const titre = f.dataset.reglagesFenetre || 'Élément';
            const donnees = new FormData(f);
            const modification = { action: f.action, donnees, libelle: `${titre} : ${nom || 'modification'}` };
            // Un élément existant modifié deux fois : la dernière version remplace la précédente.
            const deja = donnees.has('_method')
                ? enAttente.findIndex((m) => m.action === f.action && m.donnees.get('_method') === donnees.get('_method'))
                : -1;
            if (deja >= 0) {
                enAttente[deja] = modification;
            } else {
                enAttente.push(modification);
            }
            const composant = [...ancetres(f)].find((el) => el._x_dataStack && 'open' in window.Alpine.$data(el));
            if (composant) {
                window.Alpine.$data(composant).open = false;
            } else {
                f.closest('.fixed[id]')?.classList.add('hidden'); // fenêtre x-modal
            }
            rafraichir();
        });
    });

    // Entrée dans un champ : on enregistre tout l'onglet.
    onglet.addEventListener('submit', (e) => {
        if (e.target.matches('form[data-reglages]')) {
            e.preventDefault();
            enregistrer();
        }
    });

    function effacerErreurs() {
        onglet.querySelectorAll('.reglages-erreur').forEach((el) => el.remove());
    }

    function montrerErreur(formulaire, champ, message) {
        const p = document.createElement('p');
        p.className = 'reglages-erreur mt-1 text-xs font-medium text-red-600';
        p.setAttribute('role', 'alert');
        p.textContent = message;
        const cible = champ ? formulaire.querySelector(`[name="${nomDuChamp(champ)}"]`) : null;
        if (cible) {
            (cible.closest('label') ?? cible).insertAdjacentElement('afterend', p);
        } else {
            formulaire.prepend(p);
        }
        return p;
    }

    async function envoyer(action, donnees) {
        let reponse;
        try {
            reponse = await fetch(action, { method: 'POST', body: donnees, headers: ENTETES, credentials: 'same-origin' });
        } catch {
            // Refus des droits (la fenêtre « accès refusé » s'est ouverte) ou réseau coupé.
            return { ok: false, statut: 0, corps: { message: "Enregistrement impossible : accès refusé ou connexion perdue." } };
        }
        let corps = {};
        try { corps = await reponse.json(); } catch { /* réponse sans corps */ }
        return { ok: reponse.ok, statut: reponse.status, corps };
    }

    /** Enregistre tout l'onglet. Rend true quand tout est passé. */
    async function enregistrer() {
        const aEnvoyer = modifies();
        for (const f of aEnvoyer) {
            if (! f.reportValidity()) return false;
        }

        effacerErreurs();
        boutonEnregistrer.disabled = true;
        etat.textContent = 'Enregistrement…';

        const total = aEnvoyer.length + enAttente.length;
        const echecs = [];
        for (const f of aEnvoyer) {
            const resultat = await envoyer(f.action, new FormData(f));
            if (resultat.ok) {
                initiales.set(f, empreinte(f));
                continue;
            }
            echecs.push(f);
            const erreurs = resultat.corps.errors ?? null;
            if (erreurs) {
                Object.entries(erreurs).forEach(([champ, messages]) => montrerErreur(f, champ, [].concat(messages)[0]));
            } else {
                montrerErreur(f, null, resultat.corps.message || `Enregistrement refusé (${resultat.statut}).`);
            }
        }

        for (let i = 0; i < enAttente.length;) {
            const m = enAttente[i];
            const resultat = await envoyer(m.action, m.donnees);
            if (resultat.ok) {
                enAttente.splice(i, 1);
                continue;
            }
            const erreurs = resultat.corps.errors ? Object.values(resultat.corps.errors).flat() : [];
            m.libelle = m.libelle.replace(/ — .*$/, '') + ' — ' + (erreurs[0] || resultat.corps.message || 'refusé');
            echecs.push(m);
            i++;
        }

        rafraichir();
        if (echecs.length > 0) {
            etat.textContent = `${echecs.length} modification${echecs.length > 1 ? 's' : ''} à corriger`
                + (echecs.length < total ? ' — le reste est enregistré.' : '.');
            etat.classList.add('text-red-700');
            onglet.querySelector('.reglages-erreur')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }

        return true;
    }

    function recharger() {
        const url = new URL(window.location.href);
        url.searchParams.set('enregistre', '1');
        window.location.href = url.toString();
    }

    boutonEnregistrer.addEventListener('click', async () => {
        if (await enregistrer()) recharger();
    });

    boutonAnnuler.addEventListener('click', () => {
        if (window.confirm('Abandonner les modifications non enregistrées de cet onglet ?')) {
            enAttente.length = 0;
            formulaires().forEach((f) => initiales.set(f, empreinte(f)));
            window.location.reload();
        }
    });

    // ── Quitter avec des modifications non enregistrées ──
    const dialogue = document.getElementById('dialogue-reglages');
    let suite = null;
    let laisserPasser = false;

    function demander(action) {
        suite = action;
        dialogue.hidden = false;
        dialogue.querySelector('[data-choix="enregistrer"]').focus();
    }

    dialogue.addEventListener('click', async (e) => {
        const choix = e.target.closest('[data-choix]')?.dataset.choix;
        if (! choix) return;
        dialogue.hidden = true;
        if (choix === 'rester') { suite = null; return; }
        if (choix === 'enregistrer' && ! (await enregistrer())) { suite = null; return; }
        laisserPasser = true;
        suite?.();
        // Une confirmation de suppression refusée laisse la page en place :
        // la garde reprend.
        setTimeout(() => { laisserPasser = false; }, 1000);
    });
    dialogue.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { dialogue.hidden = true; suite = null; }
    });

    document.addEventListener('click', (e) => {
        const lien = e.target.closest('a[href]');
        if (! lien || laisserPasser || nombre() === 0) return;
        const href = lien.getAttribute('href');
        if (! href || href.startsWith('#') || href.startsWith('javascript:') || lien.target === '_blank'
            || lien.hasAttribute('download') || e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        demander(() => lien.click()); // rejoue le clic : sa confirmation éventuelle comprise
    }, true);

    // Une suppression (ou une autre action immédiate) recharge la page.
    document.addEventListener('submit', (e) => {
        const f = e.target;
        if (laisserPasser || nombre() === 0 || f.matches('form[data-reglages], form[data-reglages-fenetre]')) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        demander(() => f.requestSubmit());
    }, true);

    window.addEventListener('beforeunload', (e) => {
        if (! laisserPasser && nombre() > 0) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    rafraichir();
}

function* ancetres(el) {
    for (let n = el; n; n = n.parentElement) yield n;
}

// Après Alpine : ses x-model ont rempli les champs, l'état de départ est juste.
document.addEventListener('DOMContentLoaded', () => {
    const onglet = document.querySelector('[data-reglages-onglet]');
    if (onglet) setTimeout(() => demarrer(onglet), 50);
});

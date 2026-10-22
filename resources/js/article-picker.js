/*
 * Choix d'un article par sa saisie (x-article-picker) : on tape quelques
 * lettres du nom ou de la référence, la liste propose les articles qui
 * correspondent, comme la recherche du client à la réservation.
 *
 * - Chaque mot tapé doit se retrouver dans le nom, la référence ou le détail
 *   de l'article, sans tenir compte des accents ni de la casse ; les noms
 *   qui commencent par la saisie viennent d'abord.
 * - Le clavier suffit : flèches, Entrée pour choisir, Échap pour fermer.
 * - L'identifiant choisi part dans un champ caché ; un texte tapé qui ne
 *   désigne aucun article bloque l'envoi du formulaire (validité du champ).
 * - x-modelable : le parent peut lier l'identifiant par x-model, et réagir au
 *   choix par l'événement « article-choisi » (detail : { id, article }).
 */

const sansAccents = (texte) => String(texte ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export default function articlePicker({ source, libelle = null, detail = null, initial = '', max = 60, obligatoire = false } = {}) {
    // L'élément racine du composant, hors des données réactives : la liste est
    // téléportée dans <body>, un événement émis depuis elle n'atteindrait pas
    // le parent.
    let racine = null;

    return {
        choisi: initial ?? '',
        saisie: '',
        ouvert: false,
        actif: 0,
        position: { top: 0, left: 0, width: 0 },

        init() {
            racine = this.$el;
            this.$watch('choisi', () => this.afficherChoix());
            // La liste peut arriver après (fournisseur choisi, restaurant changé).
            this.$watch(() => this.articles().length, () => this.afficherChoix());
            this.afficherChoix();

            const replacer = () => { if (this.ouvert) this.placer(); };
            window.addEventListener('scroll', replacer, { capture: true, passive: true });
            window.addEventListener('resize', replacer, { passive: true });
        },

        articles() {
            const liste = typeof source === 'function' ? source() : source;
            return Array.isArray(liste) ? liste : Object.values(liste ?? {});
        },

        libelleDe(article) {
            if (! article) return '';
            return libelle ? libelle(article) : (article.name ?? article.nom ?? '');
        },

        detailDe(article) {
            if (! article) return '';
            if (detail) return detail(article) ?? '';
            return [article.reference, article.unit ?? article.unite].filter(Boolean).join(' · ');
        },

        article() {
            if (this.choisi === '' || this.choisi === null || this.choisi === undefined) return null;
            return this.articles().find((a) => String(a.id) === String(this.choisi)) ?? null;
        },

        /** Le champ montre le nom de l'article choisi, sauf pendant la saisie. */
        afficherChoix() {
            const article = this.article();
            if (! this.ouvert) {
                this.saisie = article ? this.libelleDe(article) : (this.choisi ? this.saisie : '');
            }
            this.valider();
        },

        resultats() {
            const mots = sansAccents(this.saisie).split(/\s+/).filter(Boolean);
            const articles = this.articles();
            const choisi = this.article();
            // Le champ montre l'article choisi : on propose toute la liste.
            if (mots.length === 0 || (choisi && this.saisie === this.libelleDe(choisi))) {
                return articles.slice(0, max);
            }

            const trouves = [];
            for (const article of articles) {
                const nom = sansAccents(this.libelleDe(article));
                const texte = `${nom} ${sansAccents(article.reference)} ${sansAccents(this.detailDe(article))}`;
                if (! mots.every((mot) => texte.includes(mot))) continue;
                const rang = nom.startsWith(mots[0]) ? 0 : (nom.includes(' ' + mots[0]) ? 1 : 2);
                trouves.push({ article, rang, nom });
            }

            return trouves
                .sort((a, b) => a.rang - b.rang || a.nom.localeCompare(b.nom))
                .slice(0, max)
                .map((t) => t.article);
        },

        ouvrir() {
            this.ouvert = true;
            this.placer();
            this.$nextTick(() => this.defiler());
        },

        fermer() {
            this.ouvert = false;
            // Texte laissé sans choix : on revient à l'article choisi, s'il y en a un.
            const article = this.article();
            if (article) this.saisie = this.libelleDe(article);
            this.valider();
        },

        taper() {
            const article = this.article();
            if (article && this.saisie !== this.libelleDe(article)) {
                this.choisi = '';
                this.signaler(null);
            }
            this.actif = 0;
            this.ouvrir();
            this.valider();
        },

        choisir(article) {
            this.choisi = article.id;
            this.saisie = this.libelleDe(article);
            this.ouvert = false;
            this.valider();
            this.signaler(article);
        },

        vider() {
            this.choisi = '';
            this.saisie = '';
            this.valider();
            this.signaler(null);
            this.$refs.saisie?.focus();
        },

        /**
         * Après la mise à jour du x-model du parent (x-modelable se synchronise
         * de façon différée) : son action lit déjà la nouvelle valeur.
         */
        signaler(article) {
            this.$nextTick(() => setTimeout(() => {
                racine?.dispatchEvent(new CustomEvent('article-choisi', { detail: { id: article?.id ?? '', article }, bubbles: true }));
                // Le champ caché a changé : le formulaire l'apprend (barre
                // « Enregistrer » des paramètres, contrôles de saisie).
                this.$refs.valeur?.dispatchEvent(new Event('change', { bubbles: true }));
            }, 0));
        },

        clavier(evenement) {
            const liste = this.resultats();
            switch (evenement.key) {
                case 'ArrowDown':
                    evenement.preventDefault();
                    if (! this.ouvert) { this.ouvrir(); return; }
                    this.actif = Math.min(this.actif + 1, liste.length - 1);
                    this.defiler();
                    break;
                case 'ArrowUp':
                    evenement.preventDefault();
                    this.actif = Math.max(this.actif - 1, 0);
                    this.defiler();
                    break;
                case 'Enter':
                    if (this.ouvert && liste[this.actif]) {
                        evenement.preventDefault();
                        this.choisir(liste[this.actif]);
                    }
                    break;
                case 'Escape':
                    if (this.ouvert) {
                        evenement.preventDefault();
                        evenement.stopPropagation();
                        this.fermer();
                    }
                    break;
                case 'Tab':
                    if (this.ouvert) this.fermer();
                    break;
            }
        },

        defiler() {
            this.$refs.liste?.querySelector(`[data-rang="${this.actif}"]`)?.scrollIntoView({ block: 'nearest' });
        },

        /** Liste en position fixe, sous le champ : une fenêtre qui défile ne la coupe pas. */
        placer() {
            const champ = this.$refs.saisie?.getBoundingClientRect();
            if (! champ) return;
            const hauteur = Math.min(288, window.innerHeight * 0.45);
            const enDessous = window.innerHeight - champ.bottom;
            this.position = {
                top: enDessous < hauteur + 12 && champ.top > enDessous ? Math.max(8, champ.top - hauteur - 4) : champ.bottom + 4,
                left: champ.left,
                width: Math.max(champ.width, 260),
            };
        },

        valider() {
            const champ = this.$refs.saisie;
            if (! champ) return;
            const message = this.saisie.trim() !== '' && ! this.article()
                ? 'Choisissez un article dans la liste proposée.'
                : '';
            champ.setCustomValidity(message);
            if (obligatoire) champ.required = true;
        },
    };
}

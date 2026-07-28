/* ── Admin QCM — interactions ── */

/* ─────────────────────────────────────────────────────────────────────
   admin/admin.js — Interactions côté client de l'espace d'administration
   -----------------------------------------------------------------------
   Gère : l'ouverture/fermeture des modales, les champs "tags" (réponses
   et options), l'aperçu du slug, la recherche de questions, et l'auto-
   masquage des messages de confirmation.
   Ce fichier ne contient AUCUNE vérification de sécurité : toute la
   validation faisant foi est faite côté serveur dans admin/index.php
   (voir includes/qcm.php et includes/security.php). Le code ici ne sert
   qu'au confort d'utilisation (UX), jamais à la sécurité.
   ───────────────────────────────────────────────────────────────────── */
const Admin = {
    questionsData: {},

    // Nombre de questions affichées par page dans la liste d'édition d'un
    // QCM (partie admin). Évite d'avoir à scroller une très longue liste
    // quand un QCM contient plus de 100 questions.
    QUESTIONS_PER_PAGE: 10,
    // Page actuellement affichée (réinitialisée à 1 à chaque recherche).
    currentQuestionPage: 1,

    init() {
        const el = document.getElementById('admin-questions-data');
        if (el) {
            try {
                this.questionsData = JSON.parse(el.textContent);
            } catch (e) {
                this.questionsData = {};
            }
        }

        this.initTagInputs();
        this.initQuestionSearch();
        this.initModalClose();
        this.initAutoDismissAlerts();
        this.initSlugPreview();
        this.initPresenceTracking();
    },

    showModal(id) {
        document.getElementById(id).classList.remove('cache');
        document.body.style.overflow = 'hidden';
    },

    closeModal(event, id) {
        if (!event || event.target === document.getElementById(id)) {
            document.getElementById(id).classList.add('cache');
            document.body.style.overflow = '';
        }
    },

    initModalClose() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay:not(.cache)').forEach(modal => {
                    modal.classList.add('cache');
                });
                document.body.style.overflow = '';
            }
        });
    },

    showNewQcmModal() {
        this.showModal('modal-new-qcm');
    },

    initSlugPreview() {
        const titreInput = document.getElementById('new-qcm-titre');
        const slugInput = document.getElementById('new-qcm-slug');
        if (!titreInput || !slugInput) return;

        titreInput.addEventListener('input', () => {
            if (slugInput.dataset.manual === 'true') return;
            slugInput.value = this.slugify(titreInput.value);
        });

        slugInput.addEventListener('input', () => {
            slugInput.dataset.manual = 'true';
        });
    },

    slugify(text) {
        return text
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'qcm';
    },

    newQuestion(slug) {
        document.getElementById('modal-question-title').textContent = 'Ajouter une question';
        document.getElementById('fq-qcm-slug').value = slug;
        document.getElementById('fq-q-idx').value = '';
        document.getElementById('fq-type').value = 'ecrit';
        document.getElementById('fq-theme').value = '';
        document.getElementById('fq-principale').value = '';
        document.getElementById('fq-secondaire').value = '';
        this.setTagValues('reponses-tags', []);
        this.setTagValues('options-tags', []);
        this.toggleOptionsField();
        this.populateThemeList(slug);
        this.showModal('modal-question');
        setTimeout(() => document.getElementById('fq-principale').focus(), 100);
    },

    editQuestion(slug, qIdx) {
        const q = this.questionsData[slug]?.[qIdx];
        if (!q) return;

        document.getElementById('modal-question-title').textContent = `Modifier la question #${qIdx + 1}`;
        document.getElementById('fq-qcm-slug').value = slug;
        document.getElementById('fq-q-idx').value = qIdx;
        document.getElementById('fq-type').value = q.type || 'ecrit';
        document.getElementById('fq-theme').value = q.theme || '';
        document.getElementById('fq-principale').value = q.principale || '';
        document.getElementById('fq-secondaire').value = q.secondaire || '';
        this.setTagValues('reponses-tags', q.reponses || []);
        this.setTagValues('options-tags', q.options || []);
        this.toggleOptionsField();
        this.populateThemeList(slug);
        this.showModal('modal-question');
    },

    /**
     * Remplit la <datalist> associée au champ "Thème" avec les thèmes déjà
     * utilisés par les autres questions de ce QCM (sans doublons, triés
     * alphabétiquement), pour proposer une liste de suggestions et éviter
     * de retaper un thème existant avec une faute de frappe ou une casse
     * différente (ex : "Animaux" vs "animaux").
     */
    populateThemeList(slug) {
        const list = document.getElementById('fq-theme-list');
        if (!list) return;
        list.innerHTML = '';

        const questions = this.questionsData[slug] || [];
        const themes = [...new Set(
            questions.map(q => q.theme).filter(t => t && t.trim() !== '')
        )].sort((a, b) => a.localeCompare(b));

        themes.forEach(theme => {
            const option = document.createElement('option');
            option.value = theme;
            list.appendChild(option);
        });
    },

    /**
     * Affiche/masque le champ "Options" selon le type de question choisi.
     * Les types "qcm" (une seule bonne réponse) et "qcm_multi" (plusieurs
     * bonnes réponses) ont tous les deux besoin d'une liste d'options ;
     * seul le type "ecrit" (saisie libre) n'en a pas besoin.
     */
    toggleOptionsField() {
        const type = document.getElementById('fq-type').value;
        const group = document.getElementById('fq-options-group');
        const needsOptions = (type === 'qcm' || type === 'qcm_multi');
        group.classList.toggle('cache', !needsOptions);

        // Met à jour le texte d'aide selon qu'on attend une ou plusieurs bonnes réponses.
        const hint = document.getElementById('fq-reponses-hint');
        if (hint) {
            hint.textContent = type === 'qcm_multi'
                ? 'Ajoutez toutes les bonnes réponses (plusieurs possibles) — Entrée ou virgule pour valider'
                : 'Appuyez sur Entrée ou virgule pour ajouter une réponse';
        }
    },

    initTagInputs() {
        document.querySelectorAll('.tag-input-wrap').forEach(wrap => {
            const input = wrap.querySelector('.tag-input-field');
            const hidden = wrap.querySelector('input[type="hidden"]');

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    this.addTag(wrap, input.value);
                    input.value = '';
                } else if (e.key === 'Backspace' && input.value === '') {
                    const tags = wrap.querySelectorAll('.tag-chip');
                    if (tags.length) {
                        tags[tags.length - 1].remove();
                        this.syncHidden(wrap, hidden);
                    }
                }
            });

            input.addEventListener('blur', () => {
                if (input.value.trim()) {
                    this.addTag(wrap, input.value);
                    input.value = '';
                }
            });
        });

        document.getElementById('form-question')?.addEventListener('submit', (e) => {
            document.querySelectorAll('.tag-input-wrap').forEach(wrap => {
                const input = wrap.querySelector('.tag-input-field');
                const hidden = wrap.querySelector('input[type="hidden"]');
                if (input.value.trim()) {
                    this.addTag(wrap, input.value);
                    input.value = '';
                }
                this.syncHidden(wrap, hidden);
            });

            const reponses = document.getElementById('fq-reponses').value.trim();
            if (!reponses) {
                e.preventDefault();
                alert('Ajoutez au moins une réponse correcte.');
                return;
            }

            if (['qcm', 'qcm_multi'].includes(document.getElementById('fq-type').value)) {
                const options = document.getElementById('fq-options').value.trim();
                if (!options) {
                    e.preventDefault();
                    alert('Ajoutez au moins une option pour le QCM.');
                }
            }
        });
    },

    addTag(wrap, value) {
        value = value.trim().replace(/,$/, '');
        if (!value) return;

        const existing = [...wrap.querySelectorAll('.tag-chip')].map(t => t.dataset.value);
        if (existing.includes(value)) return;

        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.dataset.value = value;
        chip.innerHTML = `<span>${this.escapeHtml(value)}</span><button type="button" aria-label="Supprimer">&times;</button>`;
        chip.querySelector('button').onclick = () => {
            chip.remove();
            this.syncHidden(wrap, wrap.querySelector('input[type="hidden"]'));
        };

        wrap.insertBefore(chip, wrap.querySelector('.tag-input-field'));
        this.syncHidden(wrap, wrap.querySelector('input[type="hidden"]'));
    },

    setTagValues(wrapId, values) {
        const wrap = document.getElementById(wrapId);
        if (!wrap) return;
        wrap.querySelectorAll('.tag-chip').forEach(c => c.remove());
        values.forEach(v => this.addTag(wrap, v));
    },

    syncHidden(wrap, hidden) {
        const values = [...wrap.querySelectorAll('.tag-chip')].map(t => t.dataset.value);
        hidden.value = values.join(', ');
    },

    escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    },

    initQuestionSearch() {
        const search = document.getElementById('question-search');

        // La pagination doit s'appliquer même s'il n'y a pas de barre de
        // recherche affichée (elle n'existe que si le QCM a des questions),
        // donc on calcule l'affichage initial dans tous les cas.
        this.updateQuestionsView();

        if (!search) return;

        search.addEventListener('input', () => {
            // Toute nouvelle recherche repart de la page 1.
            this.currentQuestionPage = 1;
            this.updateQuestionsView();
        });
    },

    /**
     * Recalcule quelles cartes de question doivent être visibles :
     * d'abord on applique le filtre de recherche (texte), puis parmi les
     * questions qui correspondent à la recherche, on n'affiche que celles
     * de la page courante (Admin.QUESTIONS_PER_PAGE questions par page).
     * Met aussi à jour les boutons "Précédent"/"Suivant" et le compteur
     * de pages.
     */
    updateQuestionsView() {
        const cards = [...document.querySelectorAll('.question-card')];
        if (cards.length === 0) return;

        const search = document.getElementById('question-search');
        const q = search ? search.value.toLowerCase().trim() : '';

        // 1) Filtre de recherche : une carte qui ne correspond pas à la
        // recherche est masquée et exclue du calcul de pagination.
        const matched = [];
        cards.forEach(card => {
            const text = card.dataset.search || '';
            const isMatch = q === '' || text.includes(q);
            card.classList.toggle('filtered-out', !isMatch);
            if (isMatch) matched.push(card);
        });

        // 2) Pagination sur les seules cartes correspondant à la recherche.
        const totalPages = Math.max(1, Math.ceil(matched.length / this.QUESTIONS_PER_PAGE));
        if (this.currentQuestionPage > totalPages) this.currentQuestionPage = totalPages;
        if (this.currentQuestionPage < 1) this.currentQuestionPage = 1;

        const start = (this.currentQuestionPage - 1) * this.QUESTIONS_PER_PAGE;
        const end = start + this.QUESTIONS_PER_PAGE;
        matched.forEach((card, i) => {
            card.classList.toggle('page-hidden', i < start || i >= end);
        });

        // 3) Mise à jour des boutons de pagination (masqués si une seule
        // page suffit, par exemple un QCM avec moins de 10 questions).
        const pagination = document.getElementById('questions-pagination');
        if (!pagination) return;
        pagination.classList.toggle('cache', totalPages <= 1);

        const info = document.getElementById('q-page-info');
        if (info) info.textContent = `Page ${this.currentQuestionPage} / ${totalPages}`;

        const prevBtn = document.getElementById('q-page-prev');
        const nextBtn = document.getElementById('q-page-next');
        if (prevBtn) prevBtn.disabled = this.currentQuestionPage <= 1;
        if (nextBtn) nextBtn.disabled = this.currentQuestionPage >= totalPages;
    },

    /**
     * Change de page dans la liste des questions.
     * @param {number} delta -1 pour "Précédent", +1 pour "Suivant".
     */
    changeQuestionPage(delta) {
        this.currentQuestionPage += delta;
        this.updateQuestionsView();
        // Remonte en haut de la liste pour que l'utilisateur voie bien
        // le changement de page (utile si la liste précédente était longue).
        document.querySelector('.questions-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    toggleJsonEditor() {
        const body = document.getElementById('json-editor-body');
        const icon = document.getElementById('json-toggle-icon');
        if (!body) return;
        const hidden = body.classList.toggle('cache');
        if (icon) icon.textContent = hidden ? '▶' : '▼';
    },

    initAutoDismissAlerts() {
        const alert = document.querySelector('.status-msg.ok');
        if (alert) {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-8px)';
                setTimeout(() => alert.remove(), 300);
            }, 4000);
        }
    },

    /**
     * Bloc "Admins connectés" : envoie un "battement de coeur" à
     * admin/presence.php toutes les 10 secondes (et une première fois
     * immédiatement) pour signaler que cette session admin est toujours
     * active, et met à jour le compteur affiché avec la réponse — sans
     * jamais recharger la page.
     */
    initPresenceTracking() {
        const valueEl = document.getElementById('admins-online-count');
        const labelEl = document.getElementById('admins-online-label');
        if (!valueEl || !labelEl) return;

        const rafraichir = () => {
            fetch('presence.php', { credentials: 'same-origin' })
                .then(res => res.ok ? res.json() : null)
                .then(data => {
                    if (!data || typeof data.count !== 'number') return;
                    valueEl.textContent = data.count;
                    labelEl.textContent = data.count > 1 ? 'Admins connectés' : 'Admin connecté';
                })
                .catch(() => {
                    // Erreur réseau ponctuelle : on ignore silencieusement,
                    // le prochain battement de coeur réessaiera.
                });
        };

        rafraichir();
        setInterval(rafraichir, 10000);
    }
};

document.addEventListener('DOMContentLoaded', () => Admin.init());

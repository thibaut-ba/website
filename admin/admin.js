/* ── Admin QCM — interactions ── */

const Admin = {
    questionsData: {},

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
        this.showModal('modal-question');
    },

    toggleOptionsField() {
        const type = document.getElementById('fq-type').value;
        const group = document.getElementById('fq-options-group');
        group.classList.toggle('cache', type !== 'qcm');
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

            if (document.getElementById('fq-type').value === 'qcm') {
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
        if (!search) return;

        search.addEventListener('input', () => {
            const q = search.value.toLowerCase().trim();
            document.querySelectorAll('.question-card').forEach(card => {
                const text = card.dataset.search || '';
                card.classList.toggle('filtered-out', q !== '' && !text.includes(q));
            });
        });
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
    }
};

document.addEventListener('DOMContentLoaded', () => Admin.init());

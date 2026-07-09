/* ─────────────────────────────────────────────────────────────────────
   script.js — Logique du quiz côté visiteur (page d'accueil)
   -----------------------------------------------------------------------
   Gère la sélection d'un ou plusieurs modules QCM à combiner, le
   filtrage par thème (indépendant pour chaque module sélectionné),
   l'affichage des questions et la vérification des réponses pour les
   3 types de question possibles :
     - "ecrit"     : réponse tapée au clavier
     - "qcm"       : un seul choix parmi plusieurs options
     - "qcm_multi" : plusieurs choix corrects parmi plusieurs options
   Toutes les données affichées (quiz.titre, q.principale, etc.) sont
   insérées via textContent/innerText (jamais innerHTML) afin d'éviter
   toute injection de code HTML/JS dans le navigateur.
   ───────────────────────────────────────────────────────────────────── */
let quizActuel = null;
let questionsFiltered = [];
let indexQuestion = 0;
let score = 0;
let repondu = false;

/**
 * Espace de noms regroupant toute la logique de sélection des modules
 * et des thèmes à combiner avant de lancer un quiz.
 *
 * Modèle de données :
 *  - Quiz.data              : tableau de tous les QCM actifs (chargés
 *                              une fois depuis le JSON embarqué dans la page)
 *  - Quiz.selectedModules   : ensemble des slugs de modules cochés
 *  - Quiz.selectedThemes    : { slug: Set(thèmes cochés pour ce module) }
 *                              un ensemble VIDE pour un module signifie
 *                              "toutes les questions de ce module incluses"
 */
const Quiz = {
    data: [],
    selectedModules: new Set(),
    selectedThemes: {},

    /**
     * Charge la liste des QCM actifs depuis le <script type="application/json">
     * généré côté serveur (voir index.php). Ce format évite d'avoir à
     * transmettre les données via des attributs HTML (plus sûr et plus
     * simple pour combiner plusieurs modules).
     */
    init() {
        const raw = document.getElementById('quiz-data');
        try {
            this.data = raw ? JSON.parse(raw.textContent) : [];
        } catch (e) {
            this.data = [];
        }
    },

    /**
     * Appelé à chaque coche/décoche d'un module : met à jour l'ensemble
     * des modules sélectionnés et régénère le panneau des thèmes.
     */
    onModuleToggle() {
        this.selectedModules = new Set(
            [...document.querySelectorAll('.module-checkbox:checked')].map(cb => cb.value)
        );
        document.getElementById('selection-error').classList.add('cache');
        this.renderThemePanels();
    },

    /**
     * Affiche, pour chaque module coché possédant des thèmes, un groupe
     * de "tags" cliquables permettant de restreindre ce module à des
     * thèmes précis. Si aucun thème n'est coché pour un module, TOUTES
     * ses questions seront incluses (comportement par défaut).
     */
    renderThemePanels() {
        const container = document.getElementById('themes-par-module');
        container.innerHTML = '';

        let hasAnyThemeGroup = false;

        this.data.forEach(quiz => {
            if (!this.selectedModules.has(quiz.slug)) return;

            const themes = [...new Set(
                quiz.questions.map(q => q.theme).filter(t => t && t.trim() !== '')
            )];
            if (themes.length === 0) return;

            hasAnyThemeGroup = true;
            if (!this.selectedThemes[quiz.slug]) {
                this.selectedThemes[quiz.slug] = new Set();
            }
            const themesActifs = this.selectedThemes[quiz.slug];

            const group = document.createElement('div');
            group.className = 'theme-group';

            const titre = document.createElement('div');
            titre.className = 'theme-group-title';
            titre.textContent = quiz.titre;
            group.appendChild(titre);

            const hint = document.createElement('div');
            hint.className = 'theme-group-hint';
            hint.textContent = 'Aucun thème coché = toutes les questions de ce module incluses';
            group.appendChild(hint);

            const tagsWrap = document.createElement('div');
            tagsWrap.className = 'theme-tags';

            themes.forEach(theme => {
                const count = quiz.questions.filter(q => q.theme === theme).length;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'tag-theme' + (themesActifs.has(theme) ? ' active' : '');
                btn.textContent = `${capitalise(theme)} (${count})`;
                btn.onclick = () => {
                    if (themesActifs.has(theme)) {
                        themesActifs.delete(theme);
                        btn.classList.remove('active');
                    } else {
                        themesActifs.add(theme);
                        btn.classList.add('active');
                    }
                };
                tagsWrap.appendChild(btn);
            });

            group.appendChild(tagsWrap);
            container.appendChild(group);
        });

        container.classList.toggle('cache', !hasAnyThemeGroup);
    },

    /**
     * Construit le pool de questions combiné à partir des modules et
     * thèmes sélectionnés, puis lance le quiz. Exemple : 2 modules pris
     * en entier + seulement 2 thèmes d'un 3e module → toutes les
     * questions des 2 premiers modules, plus uniquement les questions
     * des thèmes choisis du 3e module.
     */
    commencer() {
        const errEl = document.getElementById('selection-error');

        if (this.selectedModules.size === 0) {
            errEl.textContent = 'Sélectionne au moins un module pour commencer.';
            errEl.classList.remove('cache');
            return;
        }

        let pool = [];
        const titresChoisis = [];

        this.data.forEach(quiz => {
            if (!this.selectedModules.has(quiz.slug)) return;
            titresChoisis.push(quiz.titre);

            const themesActifs = this.selectedThemes[quiz.slug];
            let questions = quiz.questions;
            if (themesActifs && themesActifs.size > 0) {
                questions = questions.filter(q => q.theme && themesActifs.has(q.theme));
            }
            pool = pool.concat(questions);
        });

        if (pool.length === 0) {
            errEl.textContent = 'Aucune question ne correspond à cette combinaison de modules/thèmes.';
            errEl.classList.remove('cache');
            return;
        }

        errEl.classList.add('cache');

        const titreCombine = titresChoisis.length > 1
            ? `Quiz combiné — ${titresChoisis.join(' + ')}`
            : titresChoisis[0];

        lancerQuizCombine(titreCombine, pool);
    },
};

document.addEventListener('DOMContentLoaded', () => Quiz.init());

/**
 * Démarre le quiz à partir d'un titre (éventuellement combiné) et d'un
 * pool de questions déjà filtré selon les modules/thèmes choisis.
 */
function lancerQuizCombine(titre, questions) {
    quizActuel = { titre };
    score = 0;
    repondu = false;

    questionsFiltered = shuffle([...questions]);
    indexQuestion = 0;

    document.getElementById('selection-quiz').classList.add('cache');
    document.getElementById('zone-quiz').classList.remove('cache');

    afficherQuestion();
}

function updateProgress() {
    const total = questionsFiltered.length;
    const current = indexQuestion + 1;
    const progress = (indexQuestion / total) * 100;

    document.getElementById('progress-bar').style.width = progress + '%';
    document.getElementById('progress-label').textContent = `Question ${current} / ${total}`;
}

function afficherQuestion() {
    let q = questionsFiltered[indexQuestion];
    repondu = false;

    updateProgress();
    document.getElementById('titre-quiz').innerText = quizActuel.titre;

    const badge = document.getElementById('q-theme-badge');
    if (q.theme) {
        badge.textContent = '# ' + capitalise(q.theme);
        badge.classList.remove('cache');
    } else {
        badge.classList.add('cache');
    }

    document.getElementById('q-principale').innerText = q.principale;
    document.getElementById('q-secondaire').innerText = q.secondaire || '';
    document.getElementById('feedback').innerText = '';
    document.getElementById('feedback').className = 'feedback';

    let container = document.getElementById('input-container');
    container.innerHTML = '';

    if (q.type === 'ecrit') {
        const wrap = document.createElement('div');
        wrap.style.width = '100%';
        wrap.style.display = 'flex';
        wrap.style.justifyContent = 'center';

        let input = document.createElement('input');
        input.type = 'text';
        input.id = 'reponse-utilisateur';
        input.placeholder = 'Tapez la réponse…';
        input.autocomplete = 'off';
        input.autocorrect = 'off';
        input.spellcheck = false;

        input.addEventListener('input', function () {
            if (repondu) return;
            const saisie = this.value.toLowerCase().trim();
            if (q.reponses.some(r => r.toLowerCase() === saisie)) {
                repondu = true;
                this.classList.add('correct');
                score++;
                feedback('Bravo !', false);
                setTimeout(prochaineQuestion, 900);
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !repondu) {
                const saisie = this.value.toLowerCase().trim();
                if (saisie && !q.reponses.some(r => r.toLowerCase() === saisie)) {
                    feedback('Ce n\'est pas la bonne réponse…', true);
                }
            }
        });

        wrap.appendChild(input);
        container.appendChild(wrap);
        input.focus();
    } else if (q.type === 'qcm') {
        // QCM classique : une seule bonne réponse, on valide au premier clic.
        const grid = document.createElement('div');
        grid.className = 'qcm-grid';

        shuffle([...q.options]).forEach(opt => {
            let btn = document.createElement('button');
            btn.innerText = opt;
            btn.onclick = () => verifierQCM(btn, opt, q.reponses);
            grid.appendChild(btn);
        });

        container.appendChild(grid);
    } else if (q.type === 'qcm_multi') {
        // QCM à choix multiples : l'utilisateur peut sélectionner
        // plusieurs options avant de valider avec un bouton dédié.
        afficherQuestionMulti(q, container);
    }
}

/**
 * Affiche une question de type "qcm_multi" : chaque option est un bouton
 * qui bascule entre sélectionné/non-sélectionné (comme une case à
 * cocher), et un bouton "Valider" compare l'ensemble des choix cochés
 * avec l'ensemble des bonnes réponses attendues.
 */
function afficherQuestionMulti(q, container) {
    const grid = document.createElement('div');
    grid.className = 'qcm-grid';

    const optionsMelangees = shuffle([...q.options]);
    const selection = new Set();

    optionsMelangees.forEach(opt => {
        const btn = document.createElement('button');
        btn.innerText = opt;
        btn.type = 'button';
        btn.onclick = () => {
            if (repondu) return;
            // Bascule l'état sélectionné du bouton (équivalent d'une case à cocher).
            if (selection.has(opt)) {
                selection.delete(opt);
                btn.classList.remove('selected-multi');
            } else {
                selection.add(opt);
                btn.classList.add('selected-multi');
            }
        };
        grid.appendChild(btn);
    });

    container.appendChild(grid);

    const validerBtn = document.createElement('button');
    validerBtn.className = 'btn btn-primary btn-valider-multi';
    validerBtn.type = 'button';
    validerBtn.textContent = 'Valider ma sélection';
    validerBtn.onclick = () => verifierQCMMulti(grid, selection, q.reponses);
    container.appendChild(validerBtn);
}

/**
 * Vérifie une réponse de type "qcm_multi" : la sélection de l'utilisateur
 * doit correspondre EXACTEMENT à l'ensemble des bonnes réponses (ni
 * bonne réponse manquante, ni mauvaise réponse cochée en trop).
 */
function verifierQCMMulti(grid, selection, bonnesReponses) {
    if (repondu) return;

    const attendu = new Set(bonnesReponses);
    const estCorrect = selection.size === attendu.size &&
        [...selection].every(v => attendu.has(v));

    // Colore chaque bouton selon son statut réel, indépendamment du résultat global.
    grid.querySelectorAll('button').forEach(btn => {
        const valeur = btn.innerText;
        const estAttendu = attendu.has(valeur);
        const estSelectionne = selection.has(valeur);
        btn.classList.remove('selected-multi');
        if (estAttendu) {
            btn.classList.add(estSelectionne ? 'correct-choice' : 'missed-choice');
        } else if (estSelectionne) {
            btn.classList.add('wrong');
        }
        btn.onclick = null;
    });

    const validerBtn = document.querySelector('.btn-valider-multi');
    if (validerBtn) validerBtn.remove();

    if (estCorrect) {
        repondu = true;
        score++;
        feedback('Correct !', false);
        setTimeout(prochaineQuestion, 1100);
    } else {
        repondu = true;
        feedback('Pas tout à fait — bonne(s) réponse(s) : ' + bonnesReponses.join(' / '), true);
        setTimeout(prochaineQuestion, 1800);
    }
}

function verifierQCM(btn, choix, bonnesReponses) {
    if (repondu) return;
    document.querySelectorAll('.qcm-grid button').forEach(b => b.onclick = null);

    if (bonnesReponses.includes(choix)) {
        repondu = true;
        btn.classList.add('correct-choice');
        score++;
        feedback('Correct !', false);
        setTimeout(prochaineQuestion, 900);
    } else {
        btn.classList.add('wrong');
        feedback('Réessayez…', true);
        setTimeout(() => {
            btn.classList.remove('wrong');
            document.querySelectorAll('.qcm-grid button').forEach(b => {
                b.onclick = () => verifierQCM(b, b.innerText, bonnesReponses);
            });
        }, 600);
    }
}

function feedback(msg, isError) {
    const el = document.getElementById('feedback');
    el.innerText = msg;
    el.className = 'feedback' + (isError ? ' error' : '');
}

function abandonner() {
    if (repondu) return;
    repondu = true;
    let q = questionsFiltered[indexQuestion];
    feedback('Réponse : ' + q.reponses.join(' / '), true);
    setTimeout(prochaineQuestion, 1800);
}

function prochaineQuestion() {
    indexQuestion++;
    const total = questionsFiltered.length;

    if (indexQuestion < total) {
        afficherQuestion();
    } else {
        afficherFin(total);
    }
}

function afficherFin(total) {
    const container = document.getElementById('input-container');
    container.innerHTML = '';
    document.getElementById('q-theme-badge').classList.add('cache');
    document.getElementById('q-secondaire').innerText = '';
    document.getElementById('progress-bar').style.width = '100%';
    document.getElementById('progress-label').textContent = 'Terminé !';

    const pct = total > 0 ? Math.round((score / total) * 100) : 0;

    document.getElementById('q-principale').innerHTML = `
        <div class="finish-screen">
            <div class="finish-icon">🎉</div>
            <div>Quiz terminé !</div>
            <div class="finish-score">${score} / ${total}</div>
            <div style="color:var(--text2);font-size:15px;font-weight:400;">${pct}% de bonnes réponses</div>
        </div>`;

    feedback(pct >= 80 ? 'Excellent travail !' : pct >= 50 ? 'Bon effort, continue !' : 'Tu peux recommencer pour t\'améliorer.', false);

    const btnGroup = document.createElement('div');
    btnGroup.className = 'btn-group';
    btnGroup.style.justifyContent = 'center';
    btnGroup.style.marginTop = '24px';

    const retryBtn = document.createElement('button');
    retryBtn.className = 'btn btn-primary';
    retryBtn.textContent = 'Recommencer';
    retryBtn.onclick = () => location.reload();

    btnGroup.appendChild(retryBtn);
    container.appendChild(btnGroup);

    document.querySelector('.btn-abandon').classList.add('cache');
}

function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

function capitalise(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

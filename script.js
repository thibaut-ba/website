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
   Gère aussi le rythme et les options avancées du quiz, réglables sur la
   page de sélection :
     - Quiz.delaiMs        : délai entre chaque question une fois validée
                              (null = désactivé, avance via le bouton
                              "Suivant" uniquement — planifierAvance() /
                              avancerMaintenant()).
     - Quiz.qcmUnSeulEssai : pour les questions "qcm" (choix unique), ne
                              compte que le premier essai dans le score
                              final au lieu d'autoriser des essais
                              illimités jusqu'à la bonne réponse.
     - Quiz.ecritTimerMs   : temps limite (ms) pour répondre à une
                              question "ecrit" avant qu'elle soit comptée
                              comme fausse automatiquement (null = pas de
                              limite de temps — voir demarrerChronoEcrit()).
   Toutes les données affichées (quiz.titre, q.principale, etc.) sont
   insérées via textContent/innerText (jamais innerHTML) afin d'éviter
   toute injection de code HTML/JS dans le navigateur.
   ───────────────────────────────────────────────────────────────────── */
let quizActuel = null;
let questionsFiltered = [];
let indexQuestion = 0;
let score = 0;
let repondu = false;
// Identifiant du setTimeout planifié pour l'avancement automatique vers la
// question suivante (voir planifierAvance()) ; permet de l'annuler si
// l'utilisateur clique sur "Suivant" avant son expiration.
let questionTimeoutId = null;
// true tant qu'aucune réponse n'a encore été tentée sur la question "qcm"
// (choix unique) affichée actuellement ; utilisé par verifierQCM() pour
// savoir si un essai raté est le tout premier (option Quiz.qcmUnSeulEssai).
let premierEssaiQCM = true;
// Identifiant du setInterval du chronomètre des questions "ecrit" (voir
// demarrerChronoEcrit()) ; permet de l'arrêter si la question est validée
// avant la fin du temps imparti, ou lors du passage à la question suivante.
let ecritTimerId = null;

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
    // Délai (en ms) avant de passer automatiquement à la question
    // suivante après une bonne réponse. null = délai désactivé (avance
    // uniquement via le bouton "Suivant"). Valeur par défaut : 1 seconde,
    // recalculée dans commencer() à partir des réglages de la page.
    delaiMs: 1000,
    // Si true, pour les questions "qcm" (choix unique), un premier essai
    // faux compte définitivement comme une réponse fausse dans le score
    // final (pas de nouvel essai). Si false (par défaut), l'utilisateur
    // peut réessayer jusqu'à trouver la bonne réponse, qui compte alors
    // comme réussie quel que soit le nombre d'essais.
    qcmUnSeulEssai: false,
    // Temps limite (ms) pour répondre à une question "ecrit" avant
    // qu'elle soit automatiquement comptée comme fausse. null = pas de
    // limite de temps (comportement par défaut).
    ecritTimerMs: null,

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
     * Appelé quand on coche/décoche "Désactiver le délai" : grise le
     * champ de saisie du délai en secondes tant que le délai automatique
     * est désactivé, pour bien indiquer qu'il n'est plus pris en compte.
     */
    onDelaiToggle() {
        const checkbox = document.getElementById('delai-desactive');
        const input = document.getElementById('delai-secondes');
        if (input && checkbox) input.disabled = checkbox.checked;
    },

    /**
     * Appelé quand on coche/décoche "Activer un temps limite" pour les
     * questions écrites : active/désactive le champ de saisie du nombre
     * de secondes en conséquence.
     */
    onEcritTimerToggle() {
        const checkbox = document.getElementById('ecrit-timer-actif');
        const input = document.getElementById('ecrit-timer-secondes');
        if (input && checkbox) input.disabled = !checkbox.checked;
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

        // Lecture des réglages de rythme définis sur la page de sélection :
        // soit un délai en secondes (converti en ms), soit un avancement
        // entièrement manuel si la case "Désactiver le délai" est cochée.
        const delaiDesactive = document.getElementById('delai-desactive')?.checked;
        if (delaiDesactive) {
            this.delaiMs = null;
        } else {
            let secondes = parseFloat(document.getElementById('delai-secondes')?.value);
            // Valeur de repli si le champ est vide, non numérique ou négatif.
            if (isNaN(secondes) || secondes < 0) secondes = 1;
            this.delaiMs = secondes * 1000;
        }

        // Option "un seul essai" pour les QCM à choix unique.
        this.qcmUnSeulEssai = !!document.getElementById('qcm-un-seul-essai')?.checked;

        // Option de temps limite pour les questions "écrit".
        const ecritTimerActif = document.getElementById('ecrit-timer-actif')?.checked;
        if (ecritTimerActif) {
            let secondesEcrit = parseFloat(document.getElementById('ecrit-timer-secondes')?.value);
            if (isNaN(secondesEcrit) || secondesEcrit <= 0) secondesEcrit = 15;
            this.ecritTimerMs = secondesEcrit * 1000;
        } else {
            this.ecritTimerMs = null;
        }

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
    // Chaque nouvelle question "qcm" repart avec un essai "vierge" pour
    // l'option Quiz.qcmUnSeulEssai (voir verifierQCM()).
    premierEssaiQCM = true;

    // Sécurité : annule un éventuel délai encore en attente (ne devrait pas
    // arriver en usage normal) et masque le bouton "Suivant", qui ne doit
    // réapparaître qu'une fois cette nouvelle question validée (voir
    // planifierAvance()).
    if (questionTimeoutId) {
        clearTimeout(questionTimeoutId);
        questionTimeoutId = null;
    }
    document.getElementById('btn-suivant').classList.add('cache');

    // Arrête le chronomètre d'une éventuelle question "ecrit" précédente
    // avant d'en démarrer un nouveau plus bas si besoin.
    arreterChronoEcrit();

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
                arreterChronoEcrit();
                this.classList.add('correct');
                score++;
                feedback('Bravo !', false);
                planifierAvance();
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

        // Si un temps limite est configuré pour les questions "ecrit",
        // démarre le compte à rebours pour cette question.
        if (Quiz.ecritTimerMs !== null) {
            demarrerChronoEcrit(Quiz.ecritTimerMs, q);
        }
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
        planifierAvance();
    } else {
        repondu = true;
        feedback('Pas tout à fait — bonne(s) réponse(s) : ' + bonnesReponses.join(' / '), true);
        planifierAvance();
    }
}

/**
 * Vérifie une réponse de type "qcm" (choix unique). Comportement selon
 * Quiz.qcmUnSeulEssai :
 *  - false (par défaut) : l'utilisateur peut réessayer jusqu'à trouver
 *    la bonne réponse, qui compte alors comme réussie.
 *  - true : seul le premier essai compte. S'il est faux, la question
 *    est définitivement comptée comme fausse (pas de score++) et la
 *    bonne réponse est révélée avant de passer à la suite, sans
 *    possibilité de réessayer.
 */
function verifierQCM(btn, choix, bonnesReponses) {
    if (repondu) return;

    const estPremierEssai = premierEssaiQCM;
    premierEssaiQCM = false;

    document.querySelectorAll('.qcm-grid button').forEach(b => b.onclick = null);

    if (bonnesReponses.includes(choix)) {
        repondu = true;
        btn.classList.add('correct-choice');
        score++;
        feedback('Correct !', false);
        planifierAvance();
        return;
    }

    if (Quiz.qcmUnSeulEssai && estPremierEssai) {
        // Option "un seul essai" activée et c'était le premier essai :
        // la question est définitivement comptée comme fausse, sans
        // nouvel essai possible. On révèle la bonne réponse.
        repondu = true;
        btn.classList.add('wrong');
        document.querySelectorAll('.qcm-grid button').forEach(b => {
            if (bonnesReponses.includes(b.innerText)) b.classList.add('correct-choice');
        });
        feedback('Faux — la bonne réponse était : ' + bonnesReponses.join(' / '), true);
        planifierAvance();
        return;
    }

    // Comportement par défaut : nouvel essai autorisé.
    btn.classList.add('wrong');
    feedback('Réessayez…', true);
    setTimeout(() => {
        btn.classList.remove('wrong');
        document.querySelectorAll('.qcm-grid button').forEach(b => {
            b.onclick = () => verifierQCM(b, b.innerText, bonnesReponses);
        });
    }, 600);
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
    planifierAvance();
}

/**
 * Démarre le compte à rebours d'une question de type "ecrit" quand
 * l'option de temps limite (Quiz.ecritTimerMs) est activée. Affiche un
 * petit chronomètre au-dessus du champ de réponse et, si le temps
 * s'écoule sans réponse correcte, marque automatiquement la question
 * comme fausse (sans incrémenter le score) avant de passer à la suite.
 * @param {number} dureeMs Durée totale du compte à rebours, en ms.
 * @param {object} q La question "ecrit" actuellement affichée.
 */
function demarrerChronoEcrit(dureeMs, q) {
    const chrono = document.getElementById('q-chrono');
    if (!chrono) return;

    let restant = Math.ceil(dureeMs / 1000);
    chrono.textContent = `⏱ ${restant}s`;
    chrono.classList.remove('cache');

    ecritTimerId = setInterval(() => {
        restant--;
        if (restant <= 0) {
            arreterChronoEcrit();
            if (!repondu) {
                // Temps écoulé : la question est comptée comme fausse
                // (pas de score++) et on révèle la bonne réponse.
                repondu = true;
                feedback('Temps écoulé — réponse : ' + q.reponses.join(' / '), true);
                planifierAvance();
            }
            return;
        }
        chrono.textContent = `⏱ ${restant}s`;
    }, 1000);
}

/**
 * Arrête le compte à rebours en cours d'une question "ecrit" (s'il y en
 * a un) et masque son affichage. Appelé dès qu'une question est validée
 * ou quand on passe à la question suivante, pour éviter qu'un ancien
 * minuteur ne continue à tourner en arrière-plan.
 */
function arreterChronoEcrit() {
    if (ecritTimerId) {
        clearInterval(ecritTimerId);
        ecritTimerId = null;
    }
    document.getElementById('q-chrono')?.classList.add('cache');
}

/**
 * Planifie le passage à la question suivante après une bonne réponse (ou
 * un abandon) : affiche systématiquement le bouton "Suivant" pour
 * permettre d'avancer tout de suite, et si le délai automatique n'est
 * pas désactivé (Quiz.delaiMs !== null), programme aussi un passage
 * automatique après ce délai configuré par l'utilisateur.
 */
function planifierAvance() {
    document.getElementById('btn-suivant').classList.remove('cache');

    if (Quiz.delaiMs !== null) {
        questionTimeoutId = setTimeout(avancerMaintenant, Quiz.delaiMs);
    }
    // Si Quiz.delaiMs est null (délai désactivé dans les réglages), rien
    // n'est planifié ici : seul un clic sur "Suivant" fera avancer le quiz.
}

/**
 * Avance immédiatement à la question suivante, déclenché soit par le
 * clic sur le bouton "Suivant", soit par l'expiration du délai
 * automatique. Annule le minuteur en attente pour éviter un double
 * avancement si les deux se produisaient presque en même temps.
 */
function avancerMaintenant() {
    if (questionTimeoutId) {
        clearTimeout(questionTimeoutId);
        questionTimeoutId = null;
    }
    arreterChronoEcrit();
    document.getElementById('btn-suivant').classList.add('cache');
    prochaineQuestion();
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
    arreterChronoEcrit();
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
    retryBtn.textContent = 'Retour accueil';
    retryBtn.onclick = () => location.reload();

    btnGroup.appendChild(retryBtn);
    container.appendChild(btnGroup);

    document.getElementById('btn-suivant').classList.add('cache');
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

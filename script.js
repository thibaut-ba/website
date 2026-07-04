let quizActuel = null;
let questionsFiltered = [];
let indexQuestion = 0;
let themeActif = null;
let quizEnAttente = null;
let score = 0;
let repondu = false;

function selectionnerQuiz(quiz) {
    quizEnAttente = quiz;

    const themes = [...new Set(
        quiz.questions
            .map(q => q.theme)
            .filter(t => t && t.trim() !== '')
    )];

    if (themes.length > 0) {
        document.getElementById('selection-quiz').classList.add('cache');
        const panel = document.getElementById('theme-selection-panel');
        panel.classList.remove('cache');

        document.getElementById('theme-panel-titre').textContent = quiz.titre;

        const container = document.getElementById('theme-tags-container');
        container.innerHTML = '';

        const all = document.createElement('button');
        all.className = 'tag-theme all active';
        all.dataset.theme = 'all';
        all.textContent = `Tous (${quiz.questions.length})`;
        all.onclick = () => selectThemeTag('all', themes, quiz);
        container.appendChild(all);

        themes.forEach(theme => {
            const count = quiz.questions.filter(q => q.theme === theme).length;
            const btn = document.createElement('button');
            btn.className = 'tag-theme';
            btn.dataset.theme = theme;
            btn.textContent = `${capitalise(theme)} (${count})`;
            btn.onclick = () => selectThemeTag(theme, themes, quiz);
            container.appendChild(btn);
        });

        themeActif = null;
    } else {
        lancerQuiz(quiz, null);
    }
}

function selectThemeTag(theme, themes, quiz) {
    themeActif = theme === 'all' ? null : theme;
    document.querySelectorAll('.tag-theme').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.theme === theme);
    });
}

function lancerQuizAvecTheme() {
    lancerQuiz(quizEnAttente, themeActif);
}

function annulerSelection() {
    document.getElementById('theme-selection-panel').classList.add('cache');
    document.getElementById('selection-quiz').classList.remove('cache');
    quizEnAttente = null;
    themeActif = null;
}

function lancerQuiz(quiz, theme) {
    quizActuel = quiz;
    score = 0;
    repondu = false;

    if (theme) {
        questionsFiltered = quiz.questions.filter(q => q.theme === theme);
    } else {
        questionsFiltered = [...quiz.questions];
    }

    questionsFiltered = shuffle(questionsFiltered);
    indexQuestion = 0;

    document.getElementById('selection-quiz').classList.add('cache');
    document.getElementById('theme-selection-panel').classList.add('cache');
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
    } else {
        const grid = document.createElement('div');
        grid.className = 'qcm-grid';

        shuffle([...q.options]).forEach(opt => {
            let btn = document.createElement('button');
            btn.innerText = opt;
            btn.onclick = () => verifierQCM(btn, opt, q.reponses);
            grid.appendChild(btn);
        });

        container.appendChild(grid);
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

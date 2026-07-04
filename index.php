<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QCM · Entraînement</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav>
        <a href="index.php" class="brand">
            <span class="brand-icon">Q</span>
            QCM Lab
        </a>
        <a href="admin/login.php" class="nav-link">Administration</a>
    </nav>

    <div class="hero">
        <div class="hero-badge">Apprentissage interactif</div>
        <h1>Entraîne-toi avec<br><span>des QCM interactifs</span></h1>
        <p>Choisis un module et progresse à ton rythme grâce aux quiz personnalisés.</p>
    </div>

    <div id="selection-quiz" class="card">
        <div class="card-section-title">Modules disponibles</div>
        <div id="liste-boutons">
            <?php
            require_once __DIR__ . '/includes/qcm.php';
            $activeQuizzes = qcmListActive();

            if (empty($activeQuizzes)): ?>
            <p style="color:var(--text3);font-size:14px;margin-top:16px;">Aucun QCM actif pour le moment.</p>
            <?php else:
            foreach ($activeQuizzes as $quiz):
                $themes = [];
                foreach ($quiz['questions'] as $q) {
                    if (!empty($q['theme']) && !in_array($q['theme'], $themes)) {
                        $themes[] = $q['theme'];
                    }
                }
                $quizJson = json_encode($quiz, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                $count = count($quiz['questions']);
            ?>
            <button class="btn-quiz" onclick='selectionnerQuiz(<?= $quizJson ?>)'>
                <span class="quiz-label"><?= $count ?> question<?= $count > 1 ? 's' : '' ?><?= count($themes) ? ' · ' . count($themes) . ' thème' . (count($themes) > 1 ? 's' : '') : '' ?></span>
                <span class="quiz-title"><?= htmlspecialchars($quiz['titre']) ?></span>
            </button>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div id="theme-selection-panel" class="card cache" style="margin-top:16px;">
        <div class="card-section-title">Filtrage</div>
        <h4 id="theme-panel-titre" style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.2rem;margin:8px 0 4px;"></h4>
        <p style="color:var(--text3);font-size:13px;margin-bottom:16px;">Filtrer par thème (optionnel)</p>
        <div class="theme-tags" id="theme-tags-container"></div>
        <div class="btn-group" style="justify-content:center;margin-top:24px;">
            <button class="btn btn-primary" onclick="lancerQuizAvecTheme()">Commencer</button>
            <button class="btn btn-secondary" onclick="annulerSelection()">Retour</button>
        </div>
    </div>

    <div id="zone-quiz" class="card cache" style="margin-top:16px;">
        <div class="progress-label" id="progress-label">Question 1 / 1</div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" id="progress-bar" style="width:0%"></div>
        </div>
        <div class="card-header">
            <div id="titre-quiz"></div>
            <div id="q-theme-badge" class="cache"></div>
            <p class="info-secondaire" id="q-secondaire"></p>
            <h2 id="q-principale"></h2>
        </div>

        <div id="input-container"></div>

        <div id="feedback" class="feedback"></div>
        <button class="btn-abandon" onclick="abandonner()">Passer cette question</button>
    </div>

    <script src="script.js"></script>
</body>
</html>

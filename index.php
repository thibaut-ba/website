<?php
/**
 * index.php — Page publique du site : sélection et passage d'un QCM.
 * Les en-têtes de sécurité sont envoyés avant toute sortie HTML.
 */
require_once __DIR__ . '/includes/security.php';
securitySendHeaders();
?>
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
        <a href="admin/login.php" class="nav-link">Connexion</a>
    </nav>

    <div class="hero">
        <div class="hero-badge">Apprentissage interactif</div>
        <h1>Entraîne-toi avec<br><span>des QCM interactifs</span></h1>
        <p>Choisis un module et progresse à ton rythme grâce aux quiz personnalisés.</p>
    </div>

    <div id="selection-quiz" class="card">
        <div class="card-section-title">Modules disponibles</div>
        <p class="selection-hint">Coche un ou plusieurs modules à combiner (les questions seront mélangées), puis affine éventuellement par thème pour chaque module.</p>
        <div id="liste-modules">
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
                $count = count($quiz['questions']);
            ?>
            <label class="module-check">
                <input type="checkbox" class="module-checkbox" value="<?= htmlspecialchars($quiz['slug'], ENT_QUOTES) ?>" onchange="Quiz.onModuleToggle()">
                <span class="module-check-content">
                    <span class="module-check-title"><?= htmlspecialchars($quiz['titre']) ?></span>
                    <span class="module-check-meta"><?= $count ?> question<?= $count > 1 ? 's' : '' ?><?= count($themes) ? ' · ' . count($themes) . ' thème' . (count($themes) > 1 ? 's' : '') : '' ?></span>
                </span>
            </label>
            <?php endforeach; endif; ?>
        </div>

        <div id="themes-par-module" class="cache"></div>

        <p id="selection-error" class="selection-error cache"></p>
        <div class="btn-group" style="justify-content:center;margin-top:24px;">
            <button class="btn btn-primary" onclick="Quiz.commencer()">Commencer le quiz</button>
        </div>
    </div>

    <script type="application/json" id="quiz-data"><?= json_encode($activeQuizzes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

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

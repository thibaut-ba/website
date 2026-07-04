<?php
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/qcm.php';

adminRequireLogin();

$message = null;
$messageType = 'ok';

if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $slug = qcmSanitizeSlug($_GET['slug'] ?? '');
    $qcm = qcmLoad($slug);
    if ($qcm) {
        $qcm['statut'] = ($qcm['statut'] === 'actif' ? 'inactif' : 'actif');
        qcmSave($slug, $qcm);
    }
    header('Location: index.php?msg=toggle&type=ok');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_qcm') {
    $slug = qcmSanitizeSlug($_GET['slug'] ?? '');
    qcmDelete($slug);
    header('Location: index.php?msg=deleted&type=ok');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_question') {
    $slug = qcmSanitizeSlug($_GET['slug'] ?? '');
    $qIdx = (int)($_GET['q_idx'] ?? -1);
    $qcm = qcmLoad($slug);
    if ($qcm && isset($qcm['questions'][$qIdx])) {
        array_splice($qcm['questions'], $qIdx, 1);
        qcmSave($slug, $qcm);
    }
    header('Location: index.php?msg=q_deleted&type=ok&edit=' . urlencode($slug));
    exit;
}

if (isset($_POST['save_json'])) {
    $slug = qcmSanitizeSlug($_POST['qcm_slug'] ?? '');
    $nouveauContenu = $_POST['json_data'] ?? '';
    $decoded = json_decode($nouveauContenu, true);
    if ($decoded !== null && qcmSave($slug, $decoded)) {
        $message = 'Fichier JSON mis à jour avec succès.';
    } else {
        $message = 'JSON invalide — modifications non enregistrées.';
        $messageType = 'err';
    }
}

if (isset($_POST['save_qcm'])) {
    $slug = qcmSanitizeSlug($_POST['qcm_slug'] ?? '');
    $oldSlug = qcmSanitizeSlug($_POST['old_slug'] ?? $slug);
    $titre = trim($_POST['titre'] ?? '');
    $statut = $_POST['statut'] ?? 'actif';
    $isNew = !empty($_POST['is_new']);

    if ($isNew) {
        $baseSlug = $slug !== '' ? $slug : qcmSlugify($titre);
        $newSlug = qcmUniqueSlug($baseSlug);
        $newQcm = ['titre' => $titre, 'statut' => $statut, 'questions' => []];
        qcmSave($newSlug, $newQcm);
        header('Location: index.php?msg=created&type=ok&edit=' . urlencode($newSlug));
        exit;
    }

    $qcm = qcmLoad($oldSlug);
    if ($qcm) {
        $qcm['titre'] = $titre;
        $qcm['statut'] = $statut;
        $questions = $qcm['questions'] ?? [];

        if ($slug !== $oldSlug && !qcmExists($slug)) {
            qcmDelete($oldSlug);
            qcmSave($slug, ['titre' => $titre, 'statut' => $statut, 'questions' => $questions]);
            header('Location: index.php?msg=updated&type=ok&edit=' . urlencode($slug));
        } elseif ($slug !== $oldSlug && qcmExists($slug)) {
            header('Location: index.php?msg=slug_exists&type=err&edit=' . urlencode($oldSlug));
        } else {
            qcmSave($oldSlug, $qcm);
            header('Location: index.php?msg=updated&type=ok&edit=' . urlencode($oldSlug));
        }
        exit;
    }
}

if (isset($_POST['save_question'])) {
    $slug = qcmSanitizeSlug($_POST['qcm_slug'] ?? '');
    $qIdx = $_POST['q_idx'] !== '' ? (int)$_POST['q_idx'] : null;
    $type = $_POST['q_type'] ?? 'ecrit';
    $principale = trim($_POST['principale'] ?? '');
    $secondaire = trim($_POST['secondaire'] ?? '');
    $theme = trim($_POST['q_theme'] ?? '');
    $reponsesRaw = trim($_POST['reponses'] ?? '');
    $reponses = array_values(array_filter(array_map('trim', explode(',', $reponsesRaw)), fn($r) => $r !== ''));

    $newQ = [
        'type' => $type,
        'principale' => $principale,
        'secondaire' => $secondaire,
        'reponses' => $reponses,
    ];
    if ($theme !== '') {
        $newQ['theme'] = $theme;
    }

    if ($type === 'qcm') {
        $optionsRaw = trim($_POST['options'] ?? '');
        $options = array_values(array_filter(array_map('trim', explode(',', $optionsRaw)), fn($o) => $o !== ''));
        $newQ['options'] = $options;
    }

    $qcm = qcmLoad($slug);
    if ($qcm) {
        if ($qIdx === null) {
            $qcm['questions'][] = $newQ;
        } else {
            $qcm['questions'][$qIdx] = $newQ;
        }
        qcmSave($slug, $qcm);
    }
    header('Location: index.php?msg=q_saved&type=ok&edit=' . urlencode($slug));
    exit;
}

$data = qcmListAll();

if (!$message && isset($_GET['msg'])) {
    $msgs = [
        'toggle' => 'Statut mis à jour.',
        'deleted' => 'QCM supprimé.',
        'q_deleted' => 'Question supprimée.',
        'created' => 'QCM créé avec succès.',
        'updated' => 'QCM mis à jour.',
        'q_saved' => 'Question enregistrée.',
        'slug_exists' => 'Ce nom de fichier existe déjà.',
    ];
    $message = $msgs[$_GET['msg']] ?? null;
    $messageType = $_GET['type'] ?? 'ok';
}

$editSlug = isset($_GET['edit']) ? qcmSanitizeSlug($_GET['edit']) : null;
$editQcm = $editSlug ? qcmLoad($editSlug) : null;
$contenuBrut = $editQcm ? file_get_contents(qcmFilePath($editSlug)) : '';

$totalQuestions = array_sum(array_map(fn($q) => count($q['questions'] ?? []), $data));
$activeCount = count(array_filter($data, fn($q) => ($q['statut'] ?? '') === 'actif'));
$inactiveCount = count($data) - $activeCount;

$questionsJson = [];
foreach ($data as $qcm) {
    $questionsJson[$qcm['slug']] = $qcm['questions'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration · QCM</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">
<div class="admin-wrap">

    <div class="admin-topbar">
        <h1>Espace <span>Admin</span></h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="../index.php" class="admin-nav-link">← Retour au site</a>
            <a href="logout.php" class="admin-nav-link">Déconnexion</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="status-msg <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="admin-stats">
        <div class="stat-card accent">
            <div class="stat-value"><?= count($data) ?></div>
            <div class="stat-label">QCM total</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $activeCount ?></div>
            <div class="stat-label">Actifs</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-value"><?= $inactiveCount ?></div>
            <div class="stat-label">Inactifs</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalQuestions ?></div>
            <div class="stat-label">Questions</div>
        </div>
    </div>

    <?php if (!$editQcm): ?>
    <div class="admin-section">
        <div class="admin-section-header">
            <div class="admin-section-title"><span class="dot"></span>Gestion des QCM</div>
            <button class="btn btn-primary" onclick="Admin.showNewQcmModal()">＋ Nouveau QCM</button>
        </div>
        <p class="admin-section-desc">Chaque QCM est stocké dans un fichier JSON séparé (dossier <code>json/</code>).</p>

        <?php if (empty($data)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <p>Aucun QCM pour le moment.<br>Créez votre premier module pour commencer.</p>
        </div>
        <?php else: ?>
        <div class="qcm-grid-admin">
            <?php foreach ($data as $quiz): ?>
            <div class="qcm-card">
                <div class="qcm-card-header">
                    <div>
                        <div class="qcm-card-id"><?= htmlspecialchars($quiz['slug']) ?>.json</div>
                        <div class="qcm-card-title"><?= htmlspecialchars($quiz['titre']) ?></div>
                    </div>
                    <span class="badge-status <?= htmlspecialchars($quiz['statut']) ?>"><?= htmlspecialchars($quiz['statut']) ?></span>
                </div>
                <div class="qcm-card-meta">
                    <span class="qcm-card-count"><?= count($quiz['questions'] ?? []) ?> question<?= count($quiz['questions'] ?? []) > 1 ? 's' : '' ?></span>
                    <?php
                    $themes = array_unique(array_filter(array_column($quiz['questions'] ?? [], 'theme')));
                    if ($themes): ?>
                    <span class="qcm-card-count">· <?= count($themes) ?> thème<?= count($themes) > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <div class="qcm-card-actions">
                    <a class="action-link primary" href="index.php?edit=<?= urlencode($quiz['slug']) ?>">Modifier</a>
                    <a class="action-link success" href="index.php?action=toggle&slug=<?= urlencode($quiz['slug']) ?>">
                        <?= ($quiz['statut'] ?? '') === 'actif' ? 'Désactiver' : 'Activer' ?>
                    </a>
                    <a class="action-link danger" href="index.php?action=delete_qcm&slug=<?= urlencode($quiz['slug']) ?>"
                       onclick="return confirm('Supprimer ce QCM et son fichier JSON ?')">Supprimer</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($editQcm): ?>
    <div class="admin-section">
        <div class="edit-panel-header">
            <div>
                <a href="index.php" class="edit-back-link">← Retour à la liste</a>
                <h2><?= htmlspecialchars($editQcm['titre']) ?></h2>
                <p style="color:var(--text3);font-size:13px;margin-top:4px;font-family:monospace;">json/<?= htmlspecialchars($editSlug) ?>.json</p>
            </div>
            <span class="badge-status <?= htmlspecialchars($editQcm['statut']) ?>"><?= htmlspecialchars($editQcm['statut']) ?></span>
        </div>

        <form method="POST" style="margin-bottom: 32px;">
            <input type="hidden" name="old_slug" value="<?= htmlspecialchars($editSlug) ?>">
            <input type="hidden" name="is_new" value="0">
            <div class="form-grid">
                <div class="form-group">
                    <label>Titre du QCM</label>
                    <input class="form-input" type="text" name="titre" value="<?= htmlspecialchars($editQcm['titre']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select class="form-select" name="statut">
                        <option value="actif" <?= ($editQcm['statut'] ?? '') === 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactif" <?= ($editQcm['statut'] ?? '') === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Nom du fichier <span class="form-hint">(sans .json)</span></label>
                    <input class="form-input" type="text" name="qcm_slug" value="<?= htmlspecialchars($editSlug) ?>" pattern="[a-z0-9\-_]+" required>
                </div>
            </div>
            <div class="btn-group">
                <button type="submit" name="save_qcm" class="btn btn-primary">Sauvegarder le QCM</button>
            </div>
        </form>

        <div class="admin-section-header">
            <div class="admin-section-title">
                <span class="dot" style="background:var(--accent2)"></span>
                Questions (<?= count($editQcm['questions'] ?? []) ?>)
            </div>
            <?php if (count($editQcm['questions'] ?? []) > 3): ?>
            <div class="search-box">
                <input type="text" id="question-search" placeholder="Rechercher une question…">
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($editQcm['questions'])): ?>
        <div class="empty-state">
            <div class="empty-icon">❓</div>
            <p>Ce QCM n'a pas encore de questions.<br>Ajoutez-en une pour commencer.</p>
        </div>
        <?php else: ?>
        <div class="questions-list">
            <?php foreach ($editQcm['questions'] as $qi => $q):
                $searchText = strtolower(($q['principale'] ?? '') . ' ' . ($q['secondaire'] ?? '') . ' ' . ($q['theme'] ?? '') . ' ' . implode(' ', $q['reponses'] ?? []));
            ?>
            <div class="question-card" data-search="<?= htmlspecialchars($searchText) ?>">
                <div class="question-card-top">
                    <span class="question-num">#<?= $qi + 1 ?></span>
                    <span class="badge-type <?= htmlspecialchars($q['type']) ?>"><?= ($q['type'] ?? '') === 'qcm' ? 'QCM' : 'Écrit' ?></span>
                    <?php if (!empty($q['theme'])): ?>
                    <span class="detail-chip theme"><?= htmlspecialchars($q['theme']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="q-text"><?= htmlspecialchars($q['principale']) ?></div>
                <?php if (!empty($q['secondaire'])): ?>
                <div class="q-hint"><?= htmlspecialchars($q['secondaire']) ?></div>
                <?php endif; ?>
                <div class="question-details">
                    <?php foreach ($q['reponses'] as $rep): ?>
                    <span class="detail-chip correct">✓ <?= htmlspecialchars($rep) ?></span>
                    <?php endforeach; ?>
                    <?php if (($q['type'] ?? '') === 'qcm' && !empty($q['options'])): ?>
                        <?php foreach ($q['options'] as $opt): ?>
                            <?php if (!in_array($opt, $q['reponses'])): ?>
                            <span class="detail-chip"><?= htmlspecialchars($opt) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="question-card-actions">
                    <button type="button" class="action-link" onclick="Admin.editQuestion('<?= htmlspecialchars($editSlug, ENT_QUOTES) ?>', <?= $qi ?>)">Modifier</button>
                    <a class="action-link danger" href="index.php?action=delete_question&slug=<?= urlencode($editSlug) ?>&q_idx=<?= $qi ?>"
                       onclick="return confirm('Supprimer cette question ?')">Supprimer</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <button type="button" class="add-question-btn" onclick="Admin.newQuestion('<?= htmlspecialchars($editSlug, ENT_QUOTES) ?>')">＋ Ajouter une question</button>
    </div>

    <div class="admin-section">
        <div class="json-toggle" onclick="Admin.toggleJsonEditor()">
            <span id="json-toggle-icon">▶</span>
            <span class="admin-section-title" style="margin:0"><span class="dot" style="background:var(--text3)"></span>Éditeur JSON — <?= htmlspecialchars($editSlug) ?>.json</span>
        </div>
        <div id="json-editor-body" class="cache">
            <p class="admin-section-desc" style="margin-top:12px">Édition directe du fichier JSON de ce QCM.</p>
            <form method="POST">
                <input type="hidden" name="qcm_slug" value="<?= htmlspecialchars($editSlug) ?>">
                <textarea class="json-editor" name="json_data"><?= htmlspecialchars($contenuBrut) ?></textarea>
                <div class="btn-group">
                    <button type="submit" name="save_json" class="btn btn-secondary">Sauvegarder le JSON</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script type="application/json" id="admin-questions-data"><?= json_encode($questionsJson, JSON_UNESCAPED_UNICODE) ?></script>

<div id="modal-new-qcm" class="modal-overlay cache" onclick="Admin.closeModal(event, 'modal-new-qcm')">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-title">Créer un nouveau QCM</div>
        <form method="POST">
            <input type="hidden" name="is_new" value="1">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Titre du QCM</label>
                    <input class="form-input" type="text" name="titre" id="new-qcm-titre" placeholder="Ex : Vocabulaire espagnol" required autofocus>
                </div>
                <div class="form-group full">
                    <label>Nom du fichier <span class="form-hint">(sans .json)</span></label>
                    <input class="form-input" type="text" name="qcm_slug" id="new-qcm-slug" pattern="[a-z0-9\-_]+" placeholder="Ex : vocabulaire-espagnol" required>
                </div>
                <div class="form-group">
                    <label>Statut initial</label>
                    <select class="form-select" name="statut">
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                    </select>
                </div>
            </div>
            <div class="btn-group">
                <button type="submit" name="save_qcm" class="btn btn-primary">Créer</button>
                <button type="button" class="btn btn-secondary" onclick="Admin.closeModal(null, 'modal-new-qcm')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-question" class="modal-overlay cache" onclick="Admin.closeModal(event, 'modal-question')">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-title" id="modal-question-title">Ajouter une question</div>
        <form method="POST" id="form-question">
            <input type="hidden" name="qcm_slug" id="fq-qcm-slug">
            <input type="hidden" name="q_idx" id="fq-q-idx" value="">

            <div class="form-grid">
                <div class="form-group">
                    <label>Type de question</label>
                    <select class="form-select" name="q_type" id="fq-type" onchange="Admin.toggleOptionsField()">
                        <option value="ecrit">Écrit (saisie libre)</option>
                        <option value="qcm">QCM (choix multiple)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Thème <span class="form-hint">(optionnel)</span></label>
                    <input class="form-input" type="text" name="q_theme" id="fq-theme" placeholder="Ex : animal, verbe…">
                </div>
                <div class="form-group full">
                    <label>Question principale</label>
                    <input class="form-input" type="text" name="principale" id="fq-principale" placeholder="Ex : Chien" required>
                </div>
                <div class="form-group full">
                    <label>Indication <span class="form-hint">(optionnel)</span></label>
                    <input class="form-input" type="text" name="secondaire" id="fq-secondaire" placeholder="Ex : Mon chien est joueur">
                </div>
                <div class="form-group full">
                    <label>Réponses correctes</label>
                    <div class="tag-input-wrap" id="reponses-tags">
                        <input type="text" class="tag-input-field" placeholder="Tapez et appuyez sur Entrée…">
                        <input type="hidden" name="reponses" id="fq-reponses">
                    </div>
                    <span class="form-hint" style="margin-top:4px;display:block">Appuyez sur Entrée ou virgule pour ajouter une réponse</span>
                </div>
                <div class="form-group full cache" id="fq-options-group">
                    <label>Options QCM</label>
                    <div class="tag-input-wrap" id="options-tags">
                        <input type="text" class="tag-input-field" placeholder="Ajoutez les choix proposés…">
                        <input type="hidden" name="options" id="fq-options">
                    </div>
                    <span class="form-hint" style="margin-top:4px;display:block">Incluez la bonne réponse parmi les options</span>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" name="save_question" class="btn btn-primary">Enregistrer</button>
                <button type="button" class="btn btn-secondary" onclick="Admin.closeModal(null, 'modal-question')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script src="admin.js"></script>
</body>
</html>

<?php
require_once dirname(__DIR__) . '/admin/auth.php';
securitySendHeaders();

$isAdmin = adminIsLoggedIn();
$csrfToken = securityCsrfToken();
$dataFile = __DIR__ . '/manga-data.json';
$mangaData = ['headers' => [], 'rows' => []];

if (is_file($dataFile)) {
    $decoded = json_decode(file_get_contents($dataFile), true);
    if (is_array($decoded)) {
        $mangaData['headers'] = $decoded['headers'] ?? [];
        $mangaData['rows'] = $decoded['rows'] ?? [];
    }
}

$messages = [
    'import_ok' => 'Import CSV réussi — le tableau a été mis à jour.',
    'upload_err' => 'Erreur lors de l\'envoi du fichier.',
    'type_err' => 'Seuls les fichiers CSV sont autorisés.',
    'size_err' => 'Le fichier est trop volumineux (max 5 Mo).',
    'parse_err' => 'Impossible de lire le fichier CSV.',
    'save_err' => 'Erreur lors de la sauvegarde des données.',
];

$message = null;
$messageType = 'ok';
if (isset($_GET['msg'])) {
    $message = $messages[$_GET['msg']] ?? null;
    $messageType = $_GET['type'] ?? 'ok';
}

$hasData = !empty($mangaData['headers']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manga Review</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { align-items: stretch; }
        .manga-wrap {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 28px 20px 60px;
        }
        .manga-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .manga-topbar h1 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(1.4rem, 3vw, 2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
        }
        .manga-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .manga-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }
        .manga-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .manga-table thead {
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .manga-table thead th {
            background: var(--surface2);
            color: var(--text);
            text-align: left;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-strong);
            white-space: nowrap;
        }
        .manga-table thead th.sortable {
            cursor: pointer;
            user-select: none;
            transition: var(--transition);
        }
        .manga-table thead th.sortable:hover {
            background: var(--surface-hover);
            color: var(--accent2);
        }
        .manga-table thead th.sortable::after {
            content: '↕';
            margin-left: 6px;
            opacity: 0.35;
            font-size: 10px;
        }
        .manga-table thead th.sort-asc::after { content: '↑'; opacity: 1; color: var(--accent2); }
        .manga-table thead th.sort-desc::after { content: '↓'; opacity: 1; color: var(--accent2); }
        .manga-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            color: var(--text2);
            vertical-align: top;
        }
        .manga-table tbody tr:hover td {
            background: rgba(255,255,255,0.02);
            color: var(--text);
        }
        .manga-table tbody tr.filtered-out { display: none; }
        .import-box {
            background: var(--bg3);
            border: 1px dashed var(--border);
            border-radius: var(--radius-sm);
            padding: 18px;
            margin-bottom: 24px;
        }
        .import-box label {
            display: block;
            margin-bottom: 10px;
        }
        .file-input-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .file-input-wrap input[type="file"] {
            color: var(--text2);
            font-size: 13px;
        }
    </style>
</head>
<body class="admin-page">
<div class="manga-wrap">

    <div class="manga-topbar">
        <h1>Manga <span style="background:linear-gradient(135deg,var(--accent2),var(--green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Review</span></h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="../index.php" class="admin-nav-link">← Accueil QCM</a>
            <?php if ($isAdmin): ?>
            <a href="../admin/index.php" class="admin-nav-link">Administration</a>
            <a href="../admin/logout.php" class="admin-nav-link">Déconnexion</a>
            <?php else: ?>
            <a href="../admin/login.php" class="admin-nav-link">Connexion admin</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="status-msg <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <div class="import-box">
        <label class="form-label">Importer un fichier CSV</label>
        <form action="upload.php" method="POST" enctype="multipart/form-data" class="file-input-wrap">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="file" id="monFichier" name="monFichier" accept=".csv,text/csv" required>
            <button type="submit" name="submit" class="btn btn-primary">Importer</button>
        </form>
        <p class="form-hint" style="margin-top:10px;">La première ligne du CSV doit contenir les titres des colonnes. L'import remplace entièrement le tableau.</p>
    </div>
    <?php endif; ?>

    <div class="admin-section">
        <?php if ($hasData): ?>
        <div class="manga-toolbar">
            <div class="admin-section-title" style="margin:0;">
                <span class="dot"></span>
                <?= count($mangaData['rows']) ?> entrée<?= count($mangaData['rows']) > 1 ? 's' : '' ?>
            </div>
            <div class="search-box">
                <input type="text" id="manga-search" placeholder="Rechercher dans le tableau…">
            </div>
        </div>

        <div class="manga-table-wrap">
            <table class="manga-table" id="manga-table">
                <thead>
                    <tr>
                        <?php foreach ($mangaData['headers'] as $header): ?>
                        <th class="sortable"><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mangaData['rows'] as $row): ?>
                    <tr>
                        <?php foreach ($mangaData['headers'] as $i => $_): ?>
                        <td><?= htmlspecialchars($row[$i] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📊</div>
            <p>Aucune donnée à afficher.<br>
            <?php if ($isAdmin): ?>
                Importez un fichier CSV pour remplir le tableau.
            <?php else: ?>
                Connectez-vous en tant qu'administrateur pour importer des données.
            <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php if ($hasData): ?>
<script src="manga.js"></script>
<?php endif; ?>
</body>
</html>

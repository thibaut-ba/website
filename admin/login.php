<?php
require_once __DIR__ . '/auth.php';
securitySendHeaders();

if (adminIsLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = null;
$lockedSeconds = adminLockoutSecondsRemaining();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifie le jeton anti-CSRF avant tout traitement du formulaire.
    securityRequireCsrf($_POST['csrf_token'] ?? null);

    if ($lockedSeconds > 0) {
        $error = 'Trop de tentatives échouées. Réessayez dans ' . ceil($lockedSeconds / 60) . ' minute(s).';
    } else {
        // securityCleanText() retire les caractères de contrôle avant traitement.
        $username = securityCleanText($_POST['username'] ?? '', 100);
        $password = (string)($_POST['password'] ?? '');

        if (adminAttemptLogin($username, $password)) {
            header('Location: index.php');
            exit;
        }
        $lockedSeconds = adminLockoutSecondsRemaining();
        $error = $lockedSeconds > 0
            ? 'Trop de tentatives échouées. Réessayez dans ' . ceil($lockedSeconds / 60) . ' minute(s).'
            : 'Identifiants incorrects.';
    }
}

$csrfToken = securityCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion · Administration QCM</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">
<div class="admin-wrap" style="max-width:420px;margin-top:80px;">

    <div style="text-align:center;margin-bottom:32px;">
        <h1 style="font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;letter-spacing:-0.03em;">
            Espace <span style="background:linear-gradient(135deg,var(--accent2),var(--green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Admin</span>
        </h1>
        <p style="color:var(--text3);font-size:14px;margin-top:8px;">Connectez-vous pour gérer les QCM</p>
    </div>

    <?php if ($error): ?>
        <div class="status-msg err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="admin-section">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="form-group" style="margin-bottom:16px;">
                <label>Nom d'utilisateur</label>
                <input class="form-input" type="text" name="username" required autofocus autocomplete="username" maxlength="100">
            </div>
            <div class="form-group" style="margin-bottom:24px;">
                <label>Mot de passe</label>
                <input class="form-input" type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;" <?= $lockedSeconds > 0 ? 'disabled' : '' ?>>Se connecter</button>
        </form>
    </div>

    <p style="text-align:center;margin-top:20px;">
        <a href="../index.php" class="edit-back-link">← Retour au site</a>
    </p>

</div>
</body>
</html>

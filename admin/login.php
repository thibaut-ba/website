<?php
require_once __DIR__ . '/auth.php';

if (adminIsLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (adminAttemptLogin($username, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Identifiants incorrects.';
}
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
            <div class="form-group" style="margin-bottom:16px;">
                <label>Nom d'utilisateur</label>
                <input class="form-input" type="text" name="username" required autofocus autocomplete="username">
            </div>
            <div class="form-group" style="margin-bottom:24px;">
                <label>Mot de passe</label>
                <input class="form-input" type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Se connecter</button>
        </form>
    </div>

    <p style="text-align:center;margin-top:20px;">
        <a href="../index.php" class="edit-back-link">← Retour au site</a>
    </p>

</div>
</body>
</html>

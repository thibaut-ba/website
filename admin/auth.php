<?php
/**
 * admin/auth.php
 * -----------------------------------------------------------------------
 * Authentification de l'espace d'administration :
 *   - lecture des identifiants (admin/credentials.json)
 *   - vérification du mot de passe (haché OU en clair pour compatibilité)
 *   - protection basique contre le "brute force" (limitation des essais)
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/security.php'; // démarre la session de façon sécurisée

define('ADMIN_CREDENTIALS_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'credentials.json');

// Nombre maximum de tentatives de connexion échouées avant blocage temporaire.
define('ADMIN_MAX_LOGIN_ATTEMPTS', 5);
// Durée du blocage en secondes après avoir atteint le nombre maximum d'essais.
define('ADMIN_LOGIN_LOCKOUT_SECONDS', 300); // 5 minutes

/**
 * Lit le fichier des identifiants admin (username + password).
 * Retourne null si le fichier est absent ou invalide.
 */
function adminGetCredentials(): ?array
{
    if (!is_file(ADMIN_CREDENTIALS_FILE)) {
        return null;
    }
    $data = json_decode(file_get_contents(ADMIN_CREDENTIALS_FILE), true);
    return is_array($data) ? $data : null;
}

/**
 * Indique si l'utilisateur courant est connecté à l'espace admin.
 */
function adminIsLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

/**
 * Bloque l'accès à la page si l'utilisateur n'est pas connecté.
 * A appeler en tout début des pages réservées à l'admin.
 */
function adminRequireLogin(): void
{
    if (!adminIsLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Vérifie si le compte est actuellement bloqué suite à trop de tentatives
 * de connexion échouées (protection anti brute-force basique, basée sur
 * la session). Retourne le nombre de secondes restantes avant déblocage,
 * ou 0 si le compte n'est pas bloqué.
 */
function adminLockoutSecondsRemaining(): int
{
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;

    if ($attempts < ADMIN_MAX_LOGIN_ATTEMPTS) {
        return 0;
    }

    $elapsed = time() - $lastAttempt;
    $remaining = ADMIN_LOGIN_LOCKOUT_SECONDS - $elapsed;

    if ($remaining <= 0) {
        // Le délai de blocage est écoulé : on réinitialise le compteur.
        unset($_SESSION['login_attempts'], $_SESSION['login_last_attempt']);
        return 0;
    }

    return $remaining;
}

/**
 * Tente une connexion avec les identifiants fournis.
 * Compare le mot de passe soit avec password_verify() si le mot de passe
 * stocké est un hash bcrypt/argon2 (recommandé), soit en clair sinon
 * (pour compatibilité avec un fichier credentials.json existant non
 * encore migré — il est fortement recommandé de stocker un hash généré
 * avec password_hash() plutôt qu'un mot de passe en clair).
 */
function adminAttemptLogin(string $username, string $password): bool
{
    // Vérifie d'abord si le compte est temporairement bloqué.
    if (adminLockoutSecondsRemaining() > 0) {
        return false;
    }

    $credentials = adminGetCredentials();
    if ($credentials === null) {
        return false;
    }

    $storedUser = (string)($credentials['username'] ?? '');
    $storedPass = (string)($credentials['password'] ?? '');

    // hash_equals() évite les failles de timing lors de la comparaison du nom d'utilisateur.
    $validUser = hash_equals($storedUser, $username);

    // Si le mot de passe stocké ressemble à un hash password_hash() (bcrypt/argon2),
    // on le vérifie avec password_verify(). Sinon, comparaison directe (mot de passe en clair).
    $isHashed = (bool)preg_match('/^\$(2y|argon2i|argon2id)\$/', $storedPass);
    $validPass = $isHashed
        ? password_verify($password, $storedPass)
        : hash_equals($storedPass, $password);

    if ($validUser && $validPass && $storedPass !== '') {
        // Connexion réussie : on régénère l'identifiant de session
        // (protection contre la fixation de session) et on réinitialise
        // le compteur d'essais échoués.
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $storedUser;
        unset($_SESSION['login_attempts'], $_SESSION['login_last_attempt']);
        return true;
    }

    // Échec : on incrémente le compteur de tentatives pour le anti brute-force.
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_attempt'] = time();
    return false;
}

/**
 * Déconnecte l'utilisateur : vide la session et supprime le cookie associé.
 */
function adminLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

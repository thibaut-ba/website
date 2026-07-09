<?php
/**
 * includes/security.php
 * -----------------------------------------------------------------------
 * Fonctions de sécurité transverses utilisées par tout le site :
 *   - en-têtes HTTP de durcissement (anti clickjacking, anti sniffing, CSP…)
 *   - jetons anti-CSRF (protection contre les requêtes falsifiées)
 *   - nettoyage / validation générique des entrées utilisateur
 *
 * Ce fichier ne doit contenir aucune logique métier : uniquement des
 * fonctions transversales appelées par les autres fichiers PHP du site.
 * -----------------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    // Cookie de session durci : inaccessible en JS (httponly), envoyé
    // uniquement en HTTPS si disponible (secure), et protégé contre les
    // envois cross-site (samesite=Lax suffit ici car on n'a pas besoin
    // d'un accès cross-domaine).
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Envoie un ensemble d'en-têtes HTTP de sécurité de base.
 * A appeler en tout début de script, avant tout affichage HTML.
 */
function securitySendHeaders(): void
{
    // Empêche le navigateur de deviner le type MIME d'un fichier
    // (protection contre certaines attaques par upload de fichier).
    header('X-Content-Type-Options: nosniff');

    // Empêche le site d'être affiché dans une <iframe> d'un autre site
    // (protection contre le clickjacking).
    header('X-Frame-Options: DENY');

    // N'envoie jamais l'URL complète comme "referrer" vers d'autres sites.
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Politique de sécurité du contenu (CSP).
    // Le site utilise de nombreux attributs onclick="…" directement dans le
    // HTML (admin.js, script.js) : on autorise donc 'unsafe-inline' pour
    // script-src, sans quoi TOUS ces boutons (modales, suppression, etc.)
    // cessent de fonctionner. La protection principale contre les
    // injections reste l'échappement systématique des sorties HTML
    // (htmlspecialchars) côté serveur, pas la CSP elle-même.
    // On autorise aussi Google Fonts (utilisé par style.css) pour les
    // styles et les polices.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; frame-ancestors 'none';");
}

/**
 * Génère (si besoin) et retourne le jeton CSRF de la session en cours.
 * Ce jeton doit être inclus dans tous les formulaires POST et dans tous
 * les liens GET qui déclenchent une action sensible (suppression, etc.).
 */
function securityCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie qu'un jeton fourni par le client correspond bien à celui de la
 * session. Utilise hash_equals() pour éviter les attaques par timing.
 */
function securityCsrfCheck(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Stoppe l'exécution avec un code 403 si le jeton CSRF est invalide.
 * A appeler au début du traitement de chaque action POST/GET sensible.
 */
function securityRequireCsrf(?string $token): void
{
    if (!securityCsrfCheck($token)) {
        http_response_code(403);
        die('Requête refusée : jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    }
}

/**
 * Nettoie une chaîne de texte fournie par l'utilisateur :
 *  - retire les octets NUL et caractères de contrôle (protection contre
 *    l'injection de caractères invisibles / null byte injection)
 *  - coupe les espaces superflus
 *  - limite la longueur pour éviter les abus de stockage
 * Ne fait PAS d'échappement HTML : cela reste la responsabilité de
 * htmlspecialchars() au moment de l'affichage (échappement contextuel).
 */
function securityCleanText(string $value, int $maxLength = 500): string
{
    // Supprime les caractères de contrôle (dont l'octet NUL) sauf saut de ligne/tabulation.
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    $value = trim($value);
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLength, 'UTF-8');
    } else {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

/**
 * Vérifie qu'une valeur fait partie d'une liste blanche autorisée.
 * Utilisé pour valider les champs "type", "statut", etc. plutôt que de
 * faire confiance aveuglément à ce qu'envoie le formulaire.
 */
function securityInWhitelist(string $value, array $whitelist, string $default): string
{
    return in_array($value, $whitelist, true) ? $value : $default;
}

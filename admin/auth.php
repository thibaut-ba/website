<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ADMIN_CREDENTIALS_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'credentials.json');

function adminGetCredentials(): ?array
{
    if (!is_file(ADMIN_CREDENTIALS_FILE)) {
        return null;
    }
    $data = json_decode(file_get_contents(ADMIN_CREDENTIALS_FILE), true);
    return is_array($data) ? $data : null;
}

function adminIsLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function adminRequireLogin(): void
{
    if (!adminIsLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function adminAttemptLogin(string $username, string $password): bool
{
    $credentials = adminGetCredentials();
    if ($credentials === null) {
        return false;
    }

    $validUser = ($username === ($credentials['username'] ?? ''));
    $validPass = ($password === ($credentials['password'] ?? ''));

    if ($validUser && $validPass) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $credentials['username'];
        return true;
    }

    return false;
}

function adminLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

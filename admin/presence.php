<?php
/**
 * admin/presence.php
 * -----------------------------------------------------------------------
 * Endpoint AJAX interrogé périodiquement par admin.js (sans recharger la
 * page) pour mettre à jour le bloc "Admins connectés" du tableau de bord.
 * Chaque appel vaut aussi "battement de coeur" pour la session courante.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/presence.php';

// Accès réservé aux admins connectés : on ne veut pas exposer publiquement
// le nombre de sessions actives.
if (!adminIsLoggedIn()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['count' => presenceHeartbeat()]);

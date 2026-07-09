<?php
/**
 * includes/presence.php
 * -----------------------------------------------------------------------
 * Suivi léger des sessions admin actives, pour afficher dans le tableau
 * de bord un bloc "Admins connectés" qui se met à jour tout seul.
 *
 * Principe : à chaque page vue ET à intervalles réguliers via une requête
 * AJAX (voir admin/presence.php + admin.js), la session admin courante
 * enregistre un "battement de coeur" (timestamp) dans un fichier partagé.
 * Une session est considérée "active" tant que son dernier battement
 * date de moins de PRESENCE_TIMEOUT_SECONDS. Les entrées plus anciennes
 * (session fermée, onglet fermé sans déconnexion, etc.) sont purgées à
 * chaque appel.
 *
 * Ce mécanisme est volontairement simple (fichier JSON + verrou), adapté
 * à un usage avec un petit nombre d'admins sur un serveur de test. Pour
 * un usage à plus grande échelle, une base de données serait préférable.
 * -----------------------------------------------------------------------
 */

// Fichier de stockage des heartbeats, en dehors du dossier json/ (données QCM).
define('PRESENCE_FILE', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'admin_presence.json');

// Durée au-delà de laquelle une session sans nouveau battement de coeur
// est considérée comme déconnectée (en secondes).
define('PRESENCE_TIMEOUT_SECONDS', 30);

/**
 * Enregistre un battement de coeur pour la session admin courante, purge
 * les sessions expirées, et retourne le nombre de sessions actuellement
 * actives (y compris la session courante).
 */
function presenceHeartbeat(): int
{
    $dir = dirname(PRESENCE_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // SÉCURITÉ : on ne stocke jamais l'identifiant de session brut (qui
    // sert de secret d'authentification pour le cookie de session), mais
    // seulement son empreinte SHA-256. Ainsi, même en cas de lecture
    // accidentelle de ce fichier, aucune session ne peut être détournée.
    $sessionKey = hash('sha256', session_id());

    // Ouverture en lecture/écriture avec verrou exclusif : évite qu'un
    // battement de coeur concurrent (plusieurs admins en même temps)
    // corrompe le fichier.
    $fp = fopen(PRESENCE_FILE, 'c+');
    if (!$fp) {
        return 1; // dégradation silencieuse : au minimum, la session courante compte pour 1
    }

    flock($fp, LOCK_EX);
    $contenu = stream_get_contents($fp);
    $sessions = json_decode((string)$contenu, true);
    if (!is_array($sessions)) {
        $sessions = [];
    }

    $maintenant = time();
    $sessions[$sessionKey] = $maintenant;

    // Purge des sessions dont le dernier battement est trop ancien.
    foreach ($sessions as $cle => $horodatage) {
        if ($maintenant - $horodatage > PRESENCE_TIMEOUT_SECONDS) {
            unset($sessions[$cle]);
        }
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($sessions));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return count($sessions);
}

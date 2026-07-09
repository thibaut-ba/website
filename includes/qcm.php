<?php
/**
 * includes/qcm.php
 * -----------------------------------------------------------------------
 * Fonctions de gestion des QCM : chargement / sauvegarde des fichiers
 * JSON, génération de "slug" (nom de fichier), validation des données,
 * et calcul de l'espace disque utilisé par les fichiers du serveur.
 * -----------------------------------------------------------------------
 */

// Dossier où sont stockés les fichiers JSON de chaque QCM.
define('QCM_JSON_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR);

/**
 * Types de questions autorisés (liste blanche de sécurité).
 * On ne fait jamais confiance directement à la valeur envoyée par un
 * formulaire : elle doit obligatoirement appartenir à cette liste.
 *
 *  - ecrit      : réponse tapée au clavier par l'utilisateur
 *  - qcm        : choix à une seule bonne réponse parmi plusieurs options
 *  - qcm_multi  : choix à PLUSIEURS bonnes réponses parmi plusieurs options
 */
define('QCM_VALID_TYPES', ['ecrit', 'qcm', 'qcm_multi']);

/**
 * Statuts autorisés pour un QCM (liste blanche de sécurité).
 */
define('QCM_VALID_STATUTS', ['actif', 'inactif']);

/**
 * -----------------------------------------------------------------------
 * Gestion de l'espace disque utilisé par les fichiers du serveur
 * -----------------------------------------------------------------------
 */

// Quota de stockage affiché dans l'espace admin, exprimé en octets.
// Cette constante est volontairement codée "en dur" : elle ne peut être
// modifiée que directement dans ce fichier, pas depuis l'interface web,
// comme demandé. 2 Go = 2 * 1024 * 1024 * 1024 octets.
define('QCM_STORAGE_LIMIT_BYTES', 1 * 1024 * 1024 * 1024);

/**
 * Calcule récursivement la taille totale (en octets) des fichiers
 * contenus dans un dossier, en ignorant certains dossiers techniques
 * qui ne font pas partie des "données" du site (.git, node_modules...).
 */
function qcmDirectorySize(string $dir): int
{
    $total = 0;

    if (!is_dir($dir)) {
        return 0;
    }

    $excluded = ['.git', 'node_modules', '.github'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        // Ignore les dossiers exclus, à n'importe quel niveau de profondeur.
        foreach ($excluded as $ex) {
            if (strpos($item->getPathname(), DIRECTORY_SEPARATOR . $ex . DIRECTORY_SEPARATOR) !== false
                || strpos($item->getPathname(), DIRECTORY_SEPARATOR . $ex) === strlen($item->getPathname()) - strlen(DIRECTORY_SEPARATOR . $ex)) {
                continue 2;
            }
        }
        if ($item->isFile()) {
            $total += $item->getSize();
        }
    }

    return $total;
}

/**
 * Retourne un résumé de l'utilisation du stockage serveur :
 * octets utilisés, quota, pourcentage, et valeurs déjà arrondies en Mo
 * prêtes à être affichées dans le bloc d'information de l'admin.
 */
function qcmGetStorageUsage(): array
{
    $racineSite = dirname(__DIR__); // dossier racine du site (parent de includes/)
    $usedBytes = qcmDirectorySize($racineSite);
    $limitBytes = QCM_STORAGE_LIMIT_BYTES;

    $percent = $limitBytes > 0 ? ($usedBytes / $limitBytes) * 100 : 0;
    $percent = max(0, min(100, $percent));

    return [
        'used_bytes' => $usedBytes,
        'limit_bytes' => $limitBytes,
        'used_mo' => round($usedBytes / (1024 * 1024), 2),
        'limit_mo' => round($limitBytes / (1024 * 1024), 0),
        'percent' => round($percent, 1),
    ];
}

/**
 * -----------------------------------------------------------------------
 * Validation des données d'un QCM
 * -----------------------------------------------------------------------
 */

/**
 * Valide et normalise entièrement un tableau représentant un QCM complet
 * (utilisé notamment par l'éditeur JSON brut, qui permet à l'utilisateur
 * de coller n'importe quel contenu : il faut donc vérifier sa structure
 * avant de l'enregistrer sur le disque).
 *
 * Retourne le tableau nettoyé, ou null si la structure est invalide.
 */
function qcmValidateStructure(array $data): ?array
{
    if (!isset($data['titre']) || !is_string($data['titre']) || trim($data['titre']) === '') {
        return null;
    }
    if (!isset($data['questions']) || !is_array($data['questions'])) {
        return null;
    }

    $clean = [
        'titre' => mb_substr(trim($data['titre']), 0, 150, 'UTF-8'),
        'statut' => securityInWhitelist((string)($data['statut'] ?? 'actif'), QCM_VALID_STATUTS, 'actif'),
        'questions' => [],
    ];

    foreach ($data['questions'] as $q) {
        $question = qcmValidateQuestion(is_array($q) ? $q : []);
        if ($question !== null) {
            $clean['questions'][] = $question;
        }
    }

    return $clean;
}

/**
 * Valide et normalise une question unique. Retourne null si la question
 * n'a pas le minimum requis (texte principal + au moins une réponse).
 */
function qcmValidateQuestion(array $q): ?array
{
    $principale = securityCleanText((string)($q['principale'] ?? ''), 500);
    if ($principale === '') {
        return null;
    }

    $type = securityInWhitelist((string)($q['type'] ?? 'ecrit'), QCM_VALID_TYPES, 'ecrit');

    $reponses = [];
    foreach ((array)($q['reponses'] ?? []) as $r) {
        if (is_string($r) || is_numeric($r)) {
            $r = securityCleanText((string)$r, 200);
            if ($r !== '') {
                $reponses[] = $r;
            }
        }
    }
    if (empty($reponses)) {
        return null;
    }

    $clean = [
        'type' => $type,
        'principale' => $principale,
        'secondaire' => securityCleanText((string)($q['secondaire'] ?? ''), 300),
        'reponses' => array_values(array_unique($reponses)),
    ];

    if (!empty($q['theme'])) {
        $clean['theme'] = securityCleanText((string)$q['theme'], 100);
    }

    // Les questions à choix (simple ou multiple) ont besoin d'une liste
    // d'options parmi lesquelles piocher les mauvaises réponses.
    if ($type === 'qcm' || $type === 'qcm_multi') {
        $options = [];
        foreach ((array)($q['options'] ?? []) as $o) {
            if (is_string($o) || is_numeric($o)) {
                $o = securityCleanText((string)$o, 200);
                if ($o !== '') {
                    $options[] = $o;
                }
            }
        }
        // S'assure que toutes les bonnes réponses figurent bien parmi les options
        // (cohérence des données : sinon la question serait impossible à réussir).
        foreach ($clean['reponses'] as $rep) {
            if (!in_array($rep, $options, true)) {
                $options[] = $rep;
            }
        }
        $clean['options'] = array_values(array_unique($options));
    }

    return $clean;
}

/**
 * Transforme un titre libre ("Vocabulaire Espagnol !") en un slug propre
 * utilisable comme nom de fichier ("vocabulaire-espagnol").
 */
function qcmSlugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'qcm';
}

/**
 * SÉCURITÉ : ne conserve dans le slug que des lettres minuscules, chiffres,
 * tirets et underscores. C'est cette fonction qui empêche toute tentative
 * de "path traversal" (ex: slug = "../../etc/passwd") puisque les
 * caractères '/', '\' et '.' sont systématiquement retirés avant de
 * construire un chemin de fichier avec qcmFilePath().
 */
function qcmSanitizeSlug(string $slug): string
{
    $slug = mb_strtolower(trim($slug), 'UTF-8');
    $slug = preg_replace('/[^a-z0-9\-_]+/', '', $slug);
    return $slug;
}

/**
 * Construit le chemin absolu du fichier JSON correspondant à un slug.
 * Le slug est toujours nettoyé ici (défense en profondeur), et un slug
 * vide retombe sur "qcm" pour éviter d'écrire un fichier nommé ".json"
 * ou de pointer accidentellement vers le dossier lui-même.
 */
function qcmFilePath(string $slug): string
{
    $slug = qcmSanitizeSlug($slug);
    if ($slug === '') {
        $slug = 'qcm';
    }
    return QCM_JSON_DIR . $slug . '.json';
}

/**
 * Charge un QCM depuis son fichier JSON. Retourne null si le fichier
 * n'existe pas ou si son contenu n'est pas un JSON valide.
 */
function qcmLoad(string $slug): ?array
{
    $path = qcmFilePath($slug);
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return null;
    }
    $data['slug'] = qcmSanitizeSlug($slug);
    return $data;
}

/**
 * Enregistre un QCM sur le disque au format JSON.
 * Les clés techniques ("slug", "filename") sont retirées avant écriture
 * car elles sont recalculées dynamiquement au chargement.
 */
function qcmSave(string $slug, array $data): bool
{
    if (!is_dir(QCM_JSON_DIR)) {
        mkdir(QCM_JSON_DIR, 0755, true);
    }

    unset($data['slug'], $data['filename']);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents(qcmFilePath($slug), $json) !== false;
}

/**
 * Supprime le fichier JSON d'un QCM. Retourne false si le fichier
 * n'existait pas (rien à supprimer) plutôt que de lever une erreur.
 */
function qcmDelete(string $slug): bool
{
    $path = qcmFilePath($slug);
    if (!is_file($path)) {
        return false;
    }
    return unlink($path);
}

/**
 * Indique si un QCM (fichier JSON) existe déjà pour ce slug.
 */
function qcmExists(string $slug): bool
{
    return is_file(qcmFilePath($slug));
}

/**
 * Génère un slug unique à partir d'un slug de base, en ajoutant un
 * suffixe numérique ("-2", "-3", …) tant qu'un fichier du même nom
 * existe déjà. Évite d'écraser un QCM existant lors d'une création.
 */
function qcmUniqueSlug(string $baseSlug, ?string $excludeSlug = null): string
{
    $slug = qcmSanitizeSlug($baseSlug);
    if ($slug === '' || $slug === ($excludeSlug ?? '')) {
        $slug = 'qcm';
    }

    $candidate = $slug;
    $i = 2;
    while (qcmExists($candidate) && $candidate !== ($excludeSlug ?? '')) {
        $candidate = $slug . '-' . $i;
        $i++;
    }
    return $candidate;
}

/**
 * Charge et retourne tous les QCM présents dans le dossier json/,
 * triés par titre (ordre alphabétique, insensible à la casse).
 */
function qcmListAll(): array
{
    if (!is_dir(QCM_JSON_DIR)) {
        return [];
    }

    $quizzes = [];
    foreach (glob(QCM_JSON_DIR . '*.json') as $file) {
        $slug = basename($file, '.json');
        $data = qcmLoad($slug);
        if ($data !== null) {
            $quizzes[] = $data;
        }
    }

    usort($quizzes, fn($a, $b) => strcasecmp($a['titre'] ?? '', $b['titre'] ?? ''));
    return $quizzes;
}

/**
 * Retourne uniquement les QCM dont le statut est "actif" : ce sont ceux
 * proposés aux utilisateurs sur la page d'accueil publique.
 */
function qcmListActive(): array
{
    return array_values(array_filter(qcmListAll(), fn($q) => ($q['statut'] ?? '') === 'actif'));
}

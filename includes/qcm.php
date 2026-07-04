<?php

define('QCM_JSON_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR);

function qcmSlugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'qcm';
}

function qcmSanitizeSlug(string $slug): string
{
    $slug = mb_strtolower(trim($slug), 'UTF-8');
    $slug = preg_replace('/[^a-z0-9\-_]+/', '', $slug);
    return $slug;
}

function qcmFilePath(string $slug): string
{
    return QCM_JSON_DIR . qcmSanitizeSlug($slug) . '.json';
}

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

function qcmDelete(string $slug): bool
{
    $path = qcmFilePath($slug);
    if (!is_file($path)) {
        return false;
    }
    return unlink($path);
}

function qcmExists(string $slug): bool
{
    return is_file(qcmFilePath($slug));
}

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

function qcmListActive(): array
{
    return array_values(array_filter(qcmListAll(), fn($q) => ($q['statut'] ?? '') === 'actif'));
}

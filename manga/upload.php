<?php
require_once dirname(__DIR__) . '/admin/auth.php';

if (!adminIsLoggedIn()) {
    header('Location: ../admin/login.php');
    exit;
}

define('MANGA_DATA_FILE', __DIR__ . '/manga-data.json');

function mangaDetectDelimiter(string $path): string
{
    $line = '';
    if (($handle = fopen($path, 'r')) !== false) {
        $line = fgets($handle) ?: '';
        fclose($handle);
    }
    $semicolons = substr_count($line, ';');
    $commas = substr_count($line, ',');
    return $semicolons > $commas ? ';' : ',';
}

/**
 * Convertit une chaîne vers de l'UTF-8 valide, quelle que soit son
 * encodage d'origine (Windows-1252/ISO-8859-1 le plus souvent quand
 * Excel exporte mal malgré le libellé "CSV UTF-8").
 */
function mangaToUtf8(string $value): string
{
    if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
        return $value;
    }

    $detected = mb_detect_encoding($value, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
    $from = $detected ?: 'Windows-1252';

    $converted = @iconv($from, 'UTF-8//TRANSLIT//IGNORE', $value);
    if ($converted === false) {
        $converted = mb_convert_encoding($value, 'UTF-8', $from);
    }

    return $converted;
}

function mangaParseCsv(string $path): ?array
{
    $delimiter = mangaDetectDelimiter($path);
    $lines = [];

    if (($handle = fopen($path, 'r')) !== false) {
        $isFirstRow = true;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null] || (count($row) === 1 && trim((string)$row[0]) === '')) {
                continue;
            }
            $firstCell = true;
            $lines[] = array_map(function ($cell) use (&$isFirstRow, &$firstCell) {
                $cell = (string)$cell;
                if ($isFirstRow && $firstCell) {
                    // Retire le BOM UTF-8 (EF BB BF) qu'Excel ajoute
                    // en tête de la toute première cellule du fichier.
                    $cell = preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                }
                $firstCell = false;
                return trim(mangaToUtf8($cell));
            }, $row);
            $isFirstRow = false;
        }
        fclose($handle);
    }

    if (empty($lines)) {
        return null;
    }

    $headers = array_shift($lines);
    $headers = array_values(array_filter($headers, fn($h) => $h !== ''));

    if (empty($headers)) {
        return null;
    }

    $rows = [];
    foreach ($lines as $line) {
        if (count(array_filter($line, fn($c) => $c !== '')) === 0) {
            continue;
        }
        $padded = array_pad(array_slice($line, 0, count($headers)), count($headers), '');
        $rows[] = $padded;
    }

    return ['headers' => $headers, 'rows' => $rows];
}

function mangaSaveData(array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        error_log('manga upload: json_encode a échoué - ' . json_last_error_msg());
        return false;
    }

    if (file_put_contents(MANGA_DATA_FILE, $json) === false) {
        error_log('manga upload: impossible d\'écrire dans ' . MANGA_DATA_FILE);
        return false;
    }

    return true;
}

if (!isset($_POST['submit'])) {
    header('Location: manga-review.php');
    exit;
}

$file = $_FILES['monFichier'] ?? null;

if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header('Location: manga-review.php?msg=upload_err&type=err');
    exit;
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($extension !== 'csv') {
    header('Location: manga-review.php?msg=type_err&type=err');
    exit;
}

if ($file['size'] > 5_000_000) {
    header('Location: manga-review.php?msg=size_err&type=err');
    exit;
}

$parsed = mangaParseCsv($file['tmp_name']);

if ($parsed === null) {
    header('Location: manga-review.php?msg=parse_err&type=err');
    exit;
}

if (!mangaSaveData($parsed)) {
    header('Location: manga-review.php?msg=save_err&type=err');
    exit;
}

header('Location: manga-review.php?msg=import_ok&type=ok');
exit;
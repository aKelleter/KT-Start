<?php
declare(strict_types=1);

/**
 * Migration des favoris depuis les fichiers .ini (ancienne version KT-Start)
 * vers la base SQLite.
 *
 * Usage :
 *   php scripts/migrate_ini.php /chemin/vers/dossier/datas
 *
 * Le dossier doit contenir les fichiers *.ini exportés.
 * Idempotent : les URLs déjà présentes en base sont ignorées.
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/config/bootstrap.php';

use App\Core\Database;

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------

if ($argc < 2) {
    fwrite(STDERR, "Usage : php scripts/migrate_ini.php /chemin/vers/dossier/datas\n");
    exit(1);
}

$dataDir = rtrim($argv[1], '/\\');

if (!is_dir($dataDir)) {
    fwrite(STDERR, "Erreur : le dossier « $dataDir » n'existe pas.\n");
    exit(1);
}

$files = glob($dataDir . DIRECTORY_SEPARATOR . '*.ini');

if ($files === false || count($files) === 0) {
    fwrite(STDERR, "Aucun fichier .ini trouvé dans « $dataDir ».\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Mapping des styles de badge (anciens noms → nouveaux noms)
// Les styles identiques n'ont pas besoin d'entrée.
// ---------------------------------------------------------------------------

const BADGE_MAP = [
    'lightRed'   => 'red',
    'blueSky'    => 'lightBlue',
    'grassGreen' => 'lightGreen',
    'lightYellow'=> 'grey',
    'camouflage' => 'turquoise',
];

const VALID_STYLES = [
    'deepBlue', 'deepPurple', 'lightViolet', 'lightBlue', 'turquoise',
    'lightGreen', 'lightOrange', 'deepOrange', 'red', 'pink', 'brown', 'grey',
];

function resolveBadgeStyle(string $style): string
{
    $mapped = BADGE_MAP[$style] ?? $style;
    return in_array($mapped, VALID_STYLES, true) ? $mapped : 'deepBlue';
}

// ---------------------------------------------------------------------------
// Parsing d'un fichier .ini
// Retourne null si le fichier est invalide ou sans URL.
// ---------------------------------------------------------------------------

/**
 * Convertit une chaîne en UTF-8 si elle ne l'est pas déjà (Latin-1 → UTF-8),
 * complète les entités HTML tronquées (sans `;` final), puis les décode.
 * Ex : "Emp&ecirc" → "Emp&ecirc;" → "Empê"
 */
function toUtf8(string $value): string
{
    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Parse manuel du fichier .ini ligne par ligne.
 * parse_ini_string/file interprète `;` comme commentaire, ce qui tronque
 * les valeurs contenant des entités HTML (ex: &ecirc;cher → &ecirc).
 *
 * @return array<string,string>|false
 */
function parseIniManual(string $content): array|false
{
    $result = [];
    foreach (explode("\n", $content) as $line) {
        $line = rtrim($line, "\r");
        // Ignorer les sections [xxx] et les lignes vides
        if ($line === '' || $line[0] === '[') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        $result[$key] = $value;
    }
    return $result ?: false;
}

function parseIni(string $path): ?array
{
    $content = file_get_contents($path);
    if ($content === false) {
        return null;
    }
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
    }

    $raw = parseIniManual($content);
    if ($raw === false || empty($raw['url'])) {
        return null;
    }

    $timestamp = isset($raw['timestamp']) ? (int) $raw['timestamp'] : 0;
    $createdAt = $timestamp > 0
        ? date('Y-m-d H:i:s', $timestamp)
        : date('Y-m-d H:i:s');

    $title       = toUtf8($raw['title']       ?? '');
    $description = toUtf8($raw['description'] ?? '');

    return [
        'url'         => trim($raw['url']),
        'host'        => toUtf8(trim($raw['host'] ?? '')),
        'title'       => ($title === 'Titre non disponible')            ? '' : trim($title),
        'description' => ($description === 'Description non disponible') ? '' : trim($description),
        'badge_style' => resolveBadgeStyle(trim($raw['bgThumb'] ?? 'deepBlue')),
        'badge_text'  => toUtf8(trim($raw['textThumb'] ?? '')),
        'tags'        => toUtf8(trim($raw['tags'] ?? '')),
        'visibility'  => trim($raw['visibility'] ?? 'private') === 'public' ? 'public' : 'private',
        'list'        => toUtf8(trim($raw['list'] ?? '')),
        'timestamp'   => $timestamp,
        'created_at'  => $createdAt,
    ];
}

// ---------------------------------------------------------------------------
// Exécution
// ---------------------------------------------------------------------------

$pdo = Database::connection();

echo "Lecture de " . count($files) . " fichiers .ini...\n";

// 1. Parser tous les fichiers
$bookmarks = [];
$skipped   = 0;

foreach ($files as $file) {
    $data = parseIni($file);
    if ($data === null) {
        $skipped++;
        continue;
    }
    $bookmarks[] = $data;
}

echo count($bookmarks) . " entrées valides, $skipped ignorées.\n";

// 2. Trier par timestamp pour l'attribution des positions
usort($bookmarks, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

// 3. Récupérer les URLs déjà en base (idempotence)
$existing = $pdo->query('SELECT url FROM bookmarks')->fetchAll(PDO::FETCH_COLUMN);
$existingUrls = array_flip($existing);

// 4. Récupérer ou créer les listes
$listCache = [];  // name → id

$stmtFindList   = $pdo->prepare('SELECT id FROM lists WHERE name = :name');
$stmtCreateList = $pdo->prepare(
    "INSERT INTO lists (name, created_at) VALUES (:name, :created_at)"
);

function getOrCreateList(string $name): ?int
{
    global $pdo, $listCache, $stmtFindList, $stmtCreateList;

    if ($name === '') {
        return null;
    }

    if (isset($listCache[$name])) {
        return $listCache[$name];
    }

    $stmtFindList->execute(['name' => $name]);
    $row = $stmtFindList->fetch();

    if ($row) {
        $listCache[$name] = (int) $row['id'];
    } else {
        $stmtCreateList->execute(['name' => $name, 'created_at' => date('Y-m-d H:i:s')]);
        $listCache[$name] = (int) $pdo->lastInsertId();
        echo "  Liste créée : « $name »\n";
    }

    return $listCache[$name];
}

// 5. Compteurs de position par liste (null = sans liste)
$positionCounters = [];  // list_id|'null' → int

// 6. Insérer les favoris
$stmtInsert = $pdo->prepare("
    INSERT INTO bookmarks
        (url, host, title, description, badge_style, badge_text, tags,
         visibility, list_id, user_id, position, created_at)
    VALUES
        (:url, :host, :title, :description, :badge_style, :badge_text, :tags,
         :visibility, :list_id, :user_id, :position, :created_at)
");

$inserted  = 0;
$duplicate = 0;

$pdo->beginTransaction();

foreach ($bookmarks as $bm) {
    if (isset($existingUrls[$bm['url']])) {
        $duplicate++;
        continue;
    }

    $listId  = getOrCreateList($bm['list']);
    $listKey = $listId !== null ? (string) $listId : 'null';

    if (!isset($positionCounters[$listKey])) {
        // Récupérer la position max existante pour cette liste
        if ($listId !== null) {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM bookmarks WHERE list_id = :lid');
            $stmt->execute(['lid' => $listId]);
        } else {
            $stmt = $pdo->query('SELECT COALESCE(MAX(position), 0) FROM bookmarks WHERE list_id IS NULL');
        }
        $positionCounters[$listKey] = (int) $stmt->fetchColumn();
    }

    $positionCounters[$listKey]++;

    $stmtInsert->execute([
        'url'         => $bm['url'],
        'host'        => $bm['host']        !== '' ? $bm['host']        : null,
        'title'       => $bm['title']       !== '' ? $bm['title']       : null,
        'description' => $bm['description'] !== '' ? $bm['description'] : null,
        'badge_style' => $bm['badge_style'],
        'badge_text'  => $bm['badge_text'],
        'tags'        => $bm['tags']        !== '' ? $bm['tags']        : null,
        'visibility'  => $bm['visibility'],
        'list_id'     => $listId,
        'user_id'     => 1,
        'position'    => $positionCounters[$listKey],
        'created_at'  => $bm['created_at'],
    ]);

    $existingUrls[$bm['url']] = true;
    $inserted++;
}

$pdo->commit();

// ---------------------------------------------------------------------------
// Résumé
// ---------------------------------------------------------------------------

echo "\n--- Résumé ---\n";
echo "  Insérés   : $inserted\n";
echo "  Doublons  : $duplicate\n";
echo "  Invalides : $skipped\n";
echo "  Listes    : " . count($listCache) . " créée(s)\n";
echo "Migration terminée.\n";

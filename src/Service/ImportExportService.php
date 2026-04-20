<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\BookmarkRepository;
use App\Repository\FolderRepository;
use App\Repository\ListRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserRepository;

final class ImportExportService
{
    // ── Export v1 : favoris uniquement ────────────────────────────────────────

    public function export(int $userId): array
    {
        $pdo   = Database::connection();
        $lists = $pdo->query('SELECT name FROM lists ORDER BY name ASC')->fetchAll();

        $stmt = $pdo->prepare("
            SELECT b.url, b.host, b.title, b.description,
                   b.badge_style, b.badge_text, b.tags,
                   b.visibility, b.position, b.created_at,
                   b.folder_id,
                   l.name AS list_name
            FROM bookmarks b
            LEFT JOIN lists l ON l.id = b.list_id
            WHERE b.user_id = :user_id
            ORDER BY b.position ASC, b.created_at ASC
        ");
        $stmt->execute(['user_id' => $userId]);
        $bookmarks = $stmt->fetchAll();

        $fStmt = $pdo->prepare("
            SELECT f.id, f.name, f.parent_id, f.position, l.name AS list_name
            FROM folders f
            JOIN lists l ON l.id = f.list_id
            WHERE f.user_id = :user_id
            ORDER BY f.id ASC
        ");
        $fStmt->execute(['user_id' => $userId]);
        $folders = $fStmt->fetchAll();

        return [
            'version'     => 1,
            'exported_at' => date('Y-m-d H:i:s'),
            'lists'       => array_column($lists, 'name'),
            'folders'     => array_map(fn($f) => [
                'id'        => (int) $f['id'],
                'name'      => $f['name'],
                'parent_id' => $f['parent_id'] !== null ? (int) $f['parent_id'] : null,
                'position'  => (int) $f['position'],
                'list_name' => $f['list_name'],
            ], $folders),
            'bookmarks'   => array_map(fn($b) => [
                'url'         => $b['url'],
                'host'        => $b['host'],
                'title'       => $b['title'],
                'description' => $b['description'],
                'badge_style' => $b['badge_style'],
                'badge_text'  => $b['badge_text'],
                'tags'        => $b['tags'],
                'visibility'  => $b['visibility'],
                'list_name'   => $b['list_name'],
                'folder_id'   => $b['folder_id'] !== null ? (int) $b['folder_id'] : null,
                'position'    => (int) $b['position'],
                'created_at'  => $b['created_at'],
            ], $bookmarks),
        ];
    }

    // ── Export v2 : backup complet ────────────────────────────────────────────

    public function exportFull(): array
    {
        $pdo = Database::connection();

        // Users (hash inclus — déjà bcrypt, aucune valeur sans le mot de passe)
        $users = array_map(fn($u) => [
            'email'         => $u['email'],
            'password_hash' => $u['password_hash'],
            'role'          => $u['role'],
            'created_at'    => $u['created_at'],
        ], $pdo->query('SELECT * FROM users ORDER BY id ASC')->fetchAll());

        // Settings
        $settingsRows = $pdo->query('SELECT key, value FROM settings')->fetchAll();
        $settings = array_column($settingsRows, 'value', 'key');

        // Lists (avec flag is_default)
        $lists = array_map(fn($l) => [
            'name'       => $l['name'],
            'is_default' => (bool) $l['is_default'],
        ], $pdo->query('SELECT name, is_default FROM lists ORDER BY name ASC')->fetchAll());

        // Folders (tous utilisateurs)
        $folders = array_map(fn($f) => [
            'id'         => (int) $f['id'],
            'name'       => $f['name'],
            'parent_id'  => $f['parent_id'] !== null ? (int) $f['parent_id'] : null,
            'position'   => (int) $f['position'],
            'list_name'  => $f['list_name'],
            'user_email' => $f['user_email'],
        ], $pdo->query("
            SELECT f.id, f.name, f.parent_id, f.position,
                   l.name AS list_name, u.email AS user_email
            FROM folders f
            JOIN lists l ON l.id = f.list_id
            JOIN users u ON u.id = f.user_id
            ORDER BY f.id ASC
        ")->fetchAll());

        // Bookmarks (tous utilisateurs)
        $bookmarks = array_map(fn($b) => [
            'url'          => $b['url'],
            'host'         => $b['host'],
            'title'        => $b['title'],
            'description'  => $b['description'],
            'badge_style'  => $b['badge_style'],
            'badge_text'   => $b['badge_text'],
            'tags'         => $b['tags'],
            'visibility'   => $b['visibility'],
            'list_name'    => $b['list_name'],
            'folder_id'    => $b['folder_id'] !== null ? (int) $b['folder_id'] : null,
            'user_email'   => $b['user_email'],
            'position'     => (int) $b['position'],
            'created_at'   => $b['created_at'],
        ], $pdo->query("
            SELECT b.*, l.name AS list_name, u.email AS user_email
            FROM bookmarks b
            LEFT JOIN lists l ON l.id = b.list_id
            LEFT JOIN users u ON u.id = b.user_id
            ORDER BY b.user_id ASC, b.position ASC, b.created_at ASC
        ")->fetchAll());

        return [
            'version'     => 2,
            'exported_at' => date('Y-m-d H:i:s'),
            'settings'    => $settings,
            'users'       => $users,
            'lists'       => $lists,
            'folders'     => $folders,
            'bookmarks'   => $bookmarks,
        ];
    }

    // ── Import (v1 ou v2) ─────────────────────────────────────────────────────

    /**
     * @return array{
     *   imported: int, lists_created: int, skipped: int,
     *   users_created: int, users_skipped: int, settings_updated: int,
     *   errors: string[]
     * }
     */
    public function import(array $data, int $currentUserId, bool $fullRestore = false): array
    {
        $result = [
            'imported'         => 0,
            'lists_created'    => 0,
            'folders_created'  => 0,
            'skipped'          => 0,
            'users_created'    => 0,
            'users_skipped'    => 0,
            'settings_updated' => 0,
            'errors'           => [],
        ];

        $version = (int) ($data['version'] ?? 0);

        if ($version !== 1 && $version !== 2) {
            $result['errors'][] = 'Format non reconnu (version manquante ou invalide).';
            return $result;
        }

        if ($fullRestore && $version !== 2) {
            $result['errors'][] = 'La restauration complète nécessite un fichier backup v2. Utilisez "Import favoris" pour un export v1.';
            return $result;
        }

        if ($fullRestore) {
            $this->truncateAll();
        } elseif ($version === 1) {
            // Import favoris : vider bookmarks + dossiers + listes avant réinsertion
            $pdo = Database::connection();
            $pdo->prepare('DELETE FROM bookmarks WHERE user_id = :uid')->execute(['uid' => $currentUserId]);
            $pdo->prepare('DELETE FROM folders WHERE user_id = :uid')->execute(['uid' => $currentUserId]);
            $pdo->exec('DELETE FROM lists');
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('bookmarks', 'folders', 'lists')");
        }

        if ($version === 2) {
            $this->importSettings($data['settings'] ?? [], $result);
            $this->importUsers($data['users'] ?? [], $result);
        }

        // Pour v1 : les favoris appartiennent à l'utilisateur courant
        // Pour v2 : on résout l'utilisateur par email (user_email dans chaque favori)
        $userEmailMap = $this->buildUserEmailMap();

        $listMap = $this->importLists($data['lists'] ?? [], $result);
        $folderIdMap = $this->importFolders($data['folders'] ?? [], $result, $listMap, $userEmailMap, $currentUserId, $version);
        $this->importBookmarks($data['bookmarks'] ?? [], $result, $currentUserId, $userEmailMap, $version, $folderIdMap);

        return $result;
    }

    // ── Purge complète (restauration) ────────────────────────────────────────

    private function truncateAll(): void
    {
        $pdo = Database::connection();
        $pdo->exec('DELETE FROM bookmarks');
        $pdo->exec('DELETE FROM folders');
        $pdo->exec('DELETE FROM lists');
        $pdo->exec('DELETE FROM settings');
        $pdo->exec('DELETE FROM users');
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('bookmarks', 'folders', 'lists', 'users')");
    }

    // ── Helpers d'import ─────────────────────────────────────────────────────

    private function importSettings(mixed $settings, array &$result): void
    {
        if (!is_array($settings)) {
            return;
        }

        $repo = new SettingsRepository();
        foreach ($settings as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $repo->set($key, (string) $value);
            $result['settings_updated']++;
        }
    }

    private function importUsers(mixed $users, array &$result): void
    {
        if (!is_array($users)) {
            return;
        }

        $repo = new UserRepository();
        foreach ($users as $i => $u) {
            if (!is_array($u)) {
                continue;
            }
            $email = trim((string) ($u['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['errors'][] = 'Utilisateur #' . ($i + 1) . ' : email invalide.';
                continue;
            }
            if ($repo->emailExists($email)) {
                $result['users_skipped']++;
                continue;
            }
            $hash = (string) ($u['password_hash'] ?? '');
            if ($hash === '') {
                $result['errors'][] = "Utilisateur {$email} : hash manquant, ignoré.";
                continue;
            }
            $role      = ($u['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            $createdAt = (string) ($u['created_at'] ?? date('Y-m-d H:i:s'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt)) {
                $createdAt = date('Y-m-d H:i:s');
            }
            $repo->create([
                'email'         => $email,
                'password_hash' => $hash,
                'role'          => $role,
                'created_at'    => $createdAt,
            ]);
            $result['users_created']++;
        }
    }

    /** @return array<string, int>  name → id */
    private function importLists(mixed $lists, array &$result): array
    {
        if (!is_array($lists)) {
            return [];
        }

        $repo    = new ListRepository();
        $listMap = [];
        foreach ($repo->findAll() as $l) {
            $listMap[$l['name']] = (int) $l['id'];
        }

        $defaultListName = null;

        foreach ($lists as $entry) {
            // v1 : chaîne simple — v2 : objet {name, is_default}
            if (is_string($entry)) {
                $listName  = trim($entry);
                $isDefault = false;
            } elseif (is_array($entry)) {
                $listName  = trim((string) ($entry['name'] ?? ''));
                $isDefault = !empty($entry['is_default']);
            } else {
                continue;
            }

            if ($listName === '') {
                continue;
            }

            if (!isset($listMap[$listName])) {
                $listMap[$listName] = $repo->create($listName);
                $result['lists_created']++;
            }

            if ($isDefault) {
                $defaultListName = $listName;
            }
        }

        if ($defaultListName !== null && isset($listMap[$defaultListName])) {
            $repo->setDefault($listMap[$defaultListName]);
        }

        return $listMap;
    }

    /**
     * Recrée la hiérarchie de dossiers. Tri topologique BFS (parents avant enfants).
     * @return array<int, int>  export_id → new_db_id
     */
    private function importFolders(
        mixed $folders, array &$result,
        array $listMap, array $userEmailMap,
        int $currentUserId, int $version
    ): array {
        if (!is_array($folders) || empty($folders)) {
            return [];
        }

        $repo        = new FolderRepository();
        $folderIdMap = [];

        // Indexer par export_id
        $indexed = [];
        foreach ($folders as $f) {
            if (!is_array($f)) {
                continue;
            }
            $eid = (int) ($f['id'] ?? 0);
            if ($eid > 0) {
                $indexed[$eid] = $f;
            }
        }

        // BFS : commencer par les racines (parent_id null ou absent de l'export)
        $remaining = $indexed;
        $queue     = [];
        foreach ($indexed as $eid => $f) {
            $parentRef = isset($f['parent_id']) ? (int) $f['parent_id'] : null;
            if ($parentRef === null || !isset($indexed[$parentRef])) {
                $queue[] = $eid;
                unset($remaining[$eid]);
            }
        }

        while (!empty($queue)) {
            $eid = array_shift($queue);
            $f   = $indexed[$eid];

            $listName = trim((string) ($f['list_name'] ?? ''));
            $listId   = $listName !== '' ? ($listMap[$listName] ?? null) : null;
            if ($listId === null) {
                continue;
            }

            $userId = $currentUserId;
            if ($version === 2 && isset($f['user_email'])) {
                $userEmail = trim((string) $f['user_email']);
                $userId    = $userEmailMap[$userEmail] ?? $currentUserId;
            }

            $parentRef   = isset($f['parent_id']) ? (int) $f['parent_id'] : null;
            $newParentId = $parentRef !== null ? ($folderIdMap[$parentRef] ?? null) : null;

            $name = trim((string) ($f['name'] ?? ''));
            if ($name === '') {
                $name = 'Dossier';
            }

            $newId               = $repo->create($userId, $listId, $newParentId, $name);
            $folderIdMap[$eid]   = $newId;
            $result['folders_created']++;

            // Ajouter les enfants à la file
            foreach ($remaining as $ceid => $cf) {
                $cParent = isset($cf['parent_id']) ? (int) $cf['parent_id'] : null;
                if ($cParent === $eid) {
                    $queue[] = $ceid;
                    unset($remaining[$ceid]);
                }
            }
        }

        return $folderIdMap;
    }

    private function importBookmarks(
        mixed $bookmarks, array &$result,
        int $currentUserId, array $userEmailMap, int $version,
        array $folderIdMap = []
    ): void {
        if (!is_array($bookmarks)) {
            return;
        }

        $listRepo = new ListRepository();
        $bmRepo   = new BookmarkRepository();

        // Reconstruction du map name → id (après importLists)
        $listMap = [];
        foreach ($listRepo->findAll() as $l) {
            $listMap[$l['name']] = (int) $l['id'];
        }

        foreach ($bookmarks as $i => $bm) {
            $lineNum = $i + 1;

            if (!is_array($bm)) {
                $result['errors'][] = "Favori #{$lineNum} : format invalide.";
                $result['skipped']++;
                continue;
            }

            $url = trim((string) ($bm['url'] ?? ''));
            if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
                $result['errors'][] = "Favori #{$lineNum} : URL invalide ({$url}).";
                $result['skipped']++;
                continue;
            }

            $visibility = (string) ($bm['visibility'] ?? 'private');
            if (!in_array($visibility, ['public', 'private'], true)) {
                $visibility = 'private';
            }

            $badgeStyle = trim((string) ($bm['badge_style'] ?? 'deepBlue'));
            if ($badgeStyle === '') {
                $badgeStyle = 'deepBlue';
            }

            $listName = isset($bm['list_name']) ? trim((string) $bm['list_name']) : null;
            $listId   = ($listName !== null && $listName !== '') ? ($listMap[$listName] ?? null) : null;

            $createdAt = (string) ($bm['created_at'] ?? date('Y-m-d H:i:s'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt)) {
                $createdAt = date('Y-m-d H:i:s');
            }

            // v2 : résoudre l'utilisateur par email, fallback sur courant
            if ($version === 2) {
                $userEmail = trim((string) ($bm['user_email'] ?? ''));
                $userId    = $userEmailMap[$userEmail] ?? $currentUserId;
            } else {
                $userId = $currentUserId;
            }

            $folderRef = isset($bm['folder_id']) && $bm['folder_id'] !== null
                ? (int) $bm['folder_id']
                : null;
            $folderId = $folderRef !== null ? ($folderIdMap[$folderRef] ?? null) : null;

            $bmRepo->create([
                'url'         => $url,
                'host'        => trim((string) ($bm['host'] ?? parse_url($url, PHP_URL_HOST) ?? '')),
                'title'       => substr(trim((string) ($bm['title'] ?? '')), 0, 500),
                'description' => substr(trim((string) ($bm['description'] ?? '')), 0, 2000),
                'badge_style' => $badgeStyle,
                'badge_text'  => substr(trim((string) ($bm['badge_text'] ?? '')), 0, 100),
                'tags'        => substr(trim((string) ($bm['tags'] ?? '')), 0, 500),
                'visibility'  => $visibility,
                'list_id'     => $listId,
                'folder_id'   => $folderId,
                'user_id'     => $userId,
                'position'    => (int) ($bm['position'] ?? 0),
                'created_at'  => $createdAt,
            ]);

            $result['imported']++;
        }
    }

    // ── Import HTML (Netscape Bookmark Format — Firefox / Chrome / Safari) ────

    /**
     * Parse and import a Netscape Bookmark HTML file.
     * Folder hierarchy is preserved as KT-Start folders within the target list.
     *
     * @return array{imported: int, folders_created: int, skipped: int, errors: string[]}
     */
    public function importHtml(string $html, int $userId, int $listId): array
    {
        $result = [
            'imported'        => 0,
            'folders_created' => 0,
            'skipped'         => 0,
            'errors'          => [],
        ];

        $folderRepo = new FolderRepository();
        $bmRepo     = new BookmarkRepository();

        // Stack: each entry is ['id' => ?int folderId, 'pos' => int nextPosition]
        $stack   = [['id' => null, 'pos' => 0]];
        $pending = null;  // folder name from <H3>, waiting for the following <DL>
        $lastId  = null;  // last inserted bookmark ID (for <DD> description pairing)

        // Split HTML into tag-prefixed segments (each part begins after a '<')
        $parts = preg_split('/</s', $html, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
            $gtPos = strpos($part, '>');
            if ($gtPos === false) {
                continue;
            }

            $rawTag  = substr($part, 0, $gtPos);
            $rawText = substr($part, $gtPos + 1);

            // Tag name: lowercase, first word (strip leading whitespace/slash for closing tags)
            $tagLower = ltrim(strtolower($rawTag));
            $tagName  = substr($tagLower, 0, strcspn($tagLower, " \t\n\r"));

            switch ($tagName) {

                case 'dl':
                    if ($pending !== null) {
                        $parent  = end($stack);
                        $fid     = $folderRepo->create($userId, $listId, $parent['id'], $pending);
                        $stack[] = ['id' => $fid, 'pos' => 0];
                        $result['folders_created']++;
                        $pending = null;
                    } else {
                        // Root DL or DL without a preceding H3 — stay in same folder context
                        $top     = end($stack);
                        $stack[] = ['id' => $top['id'], 'pos' => $top['pos']];
                    }
                    $lastId = null;
                    break;

                case '/dl':
                    if (count($stack) > 1) {
                        array_pop($stack);
                    }
                    $pending = null;
                    $lastId  = null;
                    break;

                case 'h3':
                    // Text content is the text immediately after <H3 ...> up to the next '<'
                    $name    = trim(html_entity_decode($rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $pending = $name !== '' ? $name : 'Dossier';
                    $lastId  = null;
                    break;

                case 'a':
                    $href = '';
                    if (preg_match('/\bhref\s*=\s*"([^"]*)"/i', $rawTag, $m)) {
                        $href = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    } elseif (preg_match("/\\bhref\\s*=\\s*'([^']*)'/i", $rawTag, $m)) {
                        $href = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }

                    // Skip non-http(s) entries (javascript:, place:, data:, …)
                    if (!preg_match('~^https?://~i', $href)) {
                        $result['skipped']++;
                        $lastId = null;
                        break;
                    }

                    $title = trim(html_entity_decode($rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($title === '') {
                        $title = $href;
                    }

                    $addDate = null;
                    if (preg_match('/\badd_date\s*=\s*"?(\d+)/i', $rawTag, $m)) {
                        $addDate = (int) $m[1];
                    }

                    // TAGS attribute (Firefox only)
                    $tags = '';
                    if (preg_match('/\btags\s*=\s*"([^"]*)"/i', $rawTag, $m)) {
                        $tags = trim($m[1]);
                    }

                    $createdAt = $addDate !== null
                        ? date('Y-m-d H:i:s', $addDate)
                        : date('Y-m-d H:i:s');

                    $lastKey = array_key_last($stack);
                    $top     = $stack[$lastKey];

                    $lastId = $bmRepo->create([
                        'url'         => $href,
                        'host'        => (string) (parse_url($href, PHP_URL_HOST) ?? ''),
                        'title'       => substr($title, 0, 500),
                        'description' => '',
                        'badge_style' => 'deepBlue',
                        'badge_text'  => '',
                        'tags'        => substr($tags, 0, 500),
                        'visibility'  => 'private',
                        'list_id'     => $listId,
                        'folder_id'   => $top['id'],
                        'user_id'     => $userId,
                        'position'    => $top['pos'],
                        'created_at'  => $createdAt,
                    ]);

                    $stack[$lastKey]['pos']++;
                    $result['imported']++;
                    break;

                case 'dd':
                    // Description immediately follows <DD> as plain text until the next tag
                    if ($lastId !== null) {
                        $desc = trim(html_entity_decode($rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        if ($desc !== '') {
                            $bmRepo->update($lastId, ['description' => substr($desc, 0, 2000)]);
                        }
                    }
                    // A DD belongs to one bookmark only — reset to prevent duplication
                    $lastId = null;
                    break;
            }
        }

        return $result;
    }

    // ── Import JSON Firefox (backup interne text/x-moz-place) ────────────────

    /**
     * @return array{imported: int, folders_created: int, skipped: int, errors: string[]}
     */
    public function importFirefoxJson(array $root, int $userId, int $listId): array
    {
        $result = [
            'imported'        => 0,
            'folders_created' => 0,
            'skipped'         => 0,
            'errors'          => [],
        ];

        $pos = 0;
        $this->processFirefoxNode($root, $userId, $listId, null, $pos, $result);

        return $result;
    }

    private function processFirefoxNode(
        array $node, int $userId, int $listId,
        ?int $parentFolderId, int &$position, array &$result
    ): void {
        $type = $node['type'] ?? '';

        if ($type === 'text/x-moz-place-separator') {
            return;
        }

        if ($type === 'text/x-moz-place-container') {
            $title    = trim($node['title'] ?? '');
            $children = $node['children'] ?? [];

            if (empty($children)) {
                return;
            }

            $folderId = null;
            if ($title !== '') {
                $folderId = (new FolderRepository())->create($userId, $listId, $parentFolderId, $title);
                $result['folders_created']++;
            }

            $childPos = 0;
            foreach ($children as $child) {
                if (is_array($child)) {
                    $this->processFirefoxNode($child, $userId, $listId, $folderId ?? $parentFolderId, $childPos, $result);
                }
            }
            return;
        }

        if ($type === 'text/x-moz-place') {
            $uri = trim($node['uri'] ?? '');
            if (!preg_match('~^https?://~i', $uri)) {
                $result['skipped']++;
                return;
            }

            $title = substr(trim($node['title'] ?? '') ?: $uri, 0, 500);

            // dateAdded is in microseconds
            $dateAdded = isset($node['dateAdded']) ? (int) ($node['dateAdded'] / 1_000_000) : null;
            $createdAt = $dateAdded !== null
                ? date('Y-m-d H:i:s', $dateAdded)
                : date('Y-m-d H:i:s');

            $tags = '';
            if (!empty($node['tags'])) {
                $tags = substr(trim((string) $node['tags']), 0, 500);
            }

            // Description stored in annotations
            $description = '';
            foreach ($node['annos'] ?? [] as $anno) {
                if (is_array($anno) && ($anno['name'] ?? '') === 'bookmarkProperties/description') {
                    $description = substr(trim((string) ($anno['value'] ?? '')), 0, 2000);
                    break;
                }
            }

            (new BookmarkRepository())->create([
                'url'         => $uri,
                'host'        => (string) (parse_url($uri, PHP_URL_HOST) ?? ''),
                'title'       => $title,
                'description' => $description,
                'badge_style' => 'deepBlue',
                'badge_text'  => '',
                'tags'        => $tags,
                'visibility'  => 'private',
                'list_id'     => $listId,
                'folder_id'   => $parentFolderId,
                'user_id'     => $userId,
                'position'    => $position,
                'created_at'  => $createdAt,
            ]);

            $position++;
            $result['imported']++;
        }
    }

    /** @return array<string, int>  email → id */
    private function buildUserEmailMap(): array
    {
        $map = [];
        foreach ((new UserRepository())->findAll() as $u) {
            $map[$u['email']] = (int) $u['id'];
        }
        return $map;
    }
}

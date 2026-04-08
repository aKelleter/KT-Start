<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\BookmarkRepository;
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
                   l.name AS list_name
            FROM bookmarks b
            LEFT JOIN lists l ON l.id = b.list_id
            WHERE b.user_id = :user_id
            ORDER BY b.position ASC, b.created_at ASC
        ");
        $stmt->execute(['user_id' => $userId]);
        $bookmarks = $stmt->fetchAll();

        return [
            'version'     => 1,
            'exported_at' => date('Y-m-d H:i:s'),
            'lists'       => array_column($lists, 'name'),
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

        if ($fullRestore) {
            $this->truncateAll();
        } elseif ($version === 1) {
            // Import favoris : vider bookmarks + listes avant réinsertion
            $pdo = Database::connection();
            $pdo->prepare('DELETE FROM bookmarks WHERE user_id = :uid')->execute(['uid' => $currentUserId]);
            $pdo->exec('DELETE FROM lists');
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('bookmarks', 'lists')");
        }

        if ($version === 2) {
            $this->importSettings($data['settings'] ?? [], $result);
            $this->importUsers($data['users'] ?? [], $result);
        }

        // Pour v1 : les favoris appartiennent à l'utilisateur courant
        // Pour v2 : on résout l'utilisateur par email (user_email dans chaque favori)
        $userEmailMap = $this->buildUserEmailMap();

        $this->importLists($data['lists'] ?? [], $result);
        $this->importBookmarks($data['bookmarks'] ?? [], $result, $currentUserId, $userEmailMap, $version);

        return $result;
    }

    // ── Purge complète (restauration) ────────────────────────────────────────

    private function truncateAll(): void
    {
        $pdo = Database::connection();
        // Ordre : dépendances d'abord
        $pdo->exec('DELETE FROM bookmarks');
        $pdo->exec('DELETE FROM lists');
        $pdo->exec('DELETE FROM settings');
        $pdo->exec('DELETE FROM users');
        // Réinitialiser les séquences AUTOINCREMENT
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('bookmarks', 'lists', 'users')");
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

    private function importLists(mixed $lists, array &$result): void
    {
        if (!is_array($lists)) {
            return;
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
    }

    private function importBookmarks(
        mixed $bookmarks, array &$result,
        int $currentUserId, array $userEmailMap, int $version
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
                'folder_id'   => null,
                'user_id'     => $userId,
                'position'    => (int) ($bm['position'] ?? 0),
                'created_at'  => $createdAt,
            ]);

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

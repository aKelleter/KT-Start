<?php
declare(strict_types=1);

namespace Tests\Service;

use App\Service\ImportExportService;
use Tests\TestCase;

/**
 * Tests de ImportExportService.
 *
 * Chaque test tourne sur une base SQLite en mémoire isolée,
 * créée proprement par la classe de base avant chaque méthode.
 */
final class ImportExportServiceTest extends TestCase
{
    private ImportExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImportExportService();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Export v1
    // ══════════════════════════════════════════════════════════════════════════

    public function test_export_v1_retourne_version_1(): void
    {
        $userId = $this->createUser();
        $result = $this->service->export($userId);

        $this->assertSame(1, $result['version']);
    }

    public function test_export_v1_contient_les_favoris_de_lutilisateur(): void
    {
        $userId = $this->createUser();
        $listId = $this->createList('Dev');
        $this->createBookmark($userId, $listId, ['url' => 'https://php.net', 'title' => 'PHP']);
        $this->createBookmark($userId, $listId, ['url' => 'https://github.com', 'title' => 'GitHub']);

        $result = $this->service->export($userId);

        $this->assertCount(2, $result['bookmarks']);
        $urls = array_column($result['bookmarks'], 'url');
        $this->assertContains('https://php.net', $urls);
        $this->assertContains('https://github.com', $urls);
    }

    public function test_export_v1_nexporte_pas_les_favoris_dun_autre_utilisateur(): void
    {
        $user1  = $this->createUser('alice@example.com');
        $user2  = $this->createUser('bob@example.com');
        $listId = $this->createList('Perso');
        $this->createBookmark($user1, $listId, ['url' => 'https://alice.io']);
        $this->createBookmark($user2, $listId, ['url' => 'https://bob.io']);

        $result = $this->service->export($user1);

        $this->assertCount(1, $result['bookmarks']);
        $this->assertSame('https://alice.io', $result['bookmarks'][0]['url']);
    }

    public function test_export_v1_contient_les_noms_de_listes(): void
    {
        $userId = $this->createUser();
        $this->createList('Dev');
        $this->createList('Lecture');

        $result = $this->service->export($userId);

        $this->assertContains('Dev', $result['lists']);
        $this->assertContains('Lecture', $result['lists']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Export v2
    // ══════════════════════════════════════════════════════════════════════════

    public function test_export_v2_retourne_version_2(): void
    {
        $result = $this->service->exportFull();

        $this->assertSame(2, $result['version']);
    }

    public function test_export_v2_contient_tous_les_utilisateurs(): void
    {
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');

        $result = $this->service->exportFull();

        $emails = array_column($result['users'], 'email');
        $this->assertContains('alice@example.com', $emails);
        $this->assertContains('bob@example.com', $emails);
    }

    public function test_export_v2_contient_les_favoris_de_tous_les_utilisateurs(): void
    {
        $user1  = $this->createUser('alice@example.com');
        $user2  = $this->createUser('bob@example.com');
        $listId = $this->createList('Commun');
        $this->createBookmark($user1, $listId, ['url' => 'https://alice.io']);
        $this->createBookmark($user2, $listId, ['url' => 'https://bob.io']);

        $result = $this->service->exportFull();

        $this->assertCount(2, $result['bookmarks']);
    }

    public function test_export_v2_inclut_is_default_sur_les_listes(): void
    {
        $this->createList('Perso', isDefault: true);
        $this->createList('Dev',   isDefault: false);

        $result = $this->service->exportFull();

        $byName = array_column($result['lists'], null, 'name');
        $this->assertTrue($byName['Perso']['is_default']);
        $this->assertFalse($byName['Dev']['is_default']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Import v1
    // ══════════════════════════════════════════════════════════════════════════

    public function test_import_v1_cree_les_listes_et_favoris(): void
    {
        $userId = $this->createUser();

        $data = [
            'version'   => 1,
            'lists'     => ['Dev', 'Lecture'],
            'bookmarks' => [
                ['url' => 'https://php.net', 'title' => 'PHP', 'list_name' => 'Dev',
                 'visibility' => 'private', 'badge_style' => 'deepBlue', 'position' => 0,
                 'created_at' => '2026-01-01 00:00:00'],
            ],
        ];

        $result = $this->service->import($data, $userId);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['lists_created']);
        $this->assertEmpty($result['errors']);
    }

    public function test_import_v1_ignore_les_urls_invalides(): void
    {
        $userId = $this->createUser();

        $data = [
            'version'   => 1,
            'lists'     => [],
            'bookmarks' => [
                ['url' => 'pas-une-url', 'list_name' => null,
                 'visibility' => 'private', 'badge_style' => 'deepBlue', 'position' => 0,
                 'created_at' => '2026-01-01 00:00:00'],
            ],
        ];

        $result = $this->service->import($data, $userId);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_import_v1_remplace_les_favoris_existants_du_meme_utilisateur(): void
    {
        $userId = $this->createUser();
        $listId = $this->createList('Old');
        $this->createBookmark($userId, $listId, ['url' => 'https://old.example.com']);

        $data = [
            'version'   => 1,
            'lists'     => ['New'],
            'bookmarks' => [
                ['url' => 'https://new.example.com', 'list_name' => 'New',
                 'visibility' => 'private', 'badge_style' => 'deepBlue', 'position' => 0,
                 'created_at' => '2026-01-01 00:00:00'],
            ],
        ];

        $this->service->import($data, $userId);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM bookmarks')->fetchColumn();
        $this->assertSame(1, $count);

        $url = $this->pdo->query('SELECT url FROM bookmarks')->fetchColumn();
        $this->assertSame('https://new.example.com', $url);
    }

    public function test_import_v1_retourne_erreur_si_version_inconnue(): void
    {
        $userId = $this->createUser();

        $result = $this->service->import(['version' => 99], $userId);

        $this->assertNotEmpty($result['errors']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Import v2
    // ══════════════════════════════════════════════════════════════════════════

    public function test_import_v2_cree_les_utilisateurs(): void
    {
        $currentUser = $this->createUser('admin@example.com');

        $data = [
            'version' => 2,
            'users'   => [
                ['email' => 'alice@example.com', 'password_hash' => '$2y$10$abc',
                 'role'  => 'user', 'created_at' => '2026-01-01 00:00:00'],
            ],
            'lists'     => [],
            'bookmarks' => [],
            'settings'  => [],
        ];

        $result = $this->service->import($data, $currentUser);

        $this->assertSame(1, $result['users_created']);
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(2, $count); // admin + alice
    }

    public function test_import_v2_ignore_les_utilisateurs_existants(): void
    {
        $userId = $this->createUser('existing@example.com');

        $data = [
            'version' => 2,
            'users'   => [
                ['email' => 'existing@example.com', 'password_hash' => '$2y$10$abc',
                 'role'  => 'admin', 'created_at' => '2026-01-01 00:00:00'],
            ],
            'lists'     => [],
            'bookmarks' => [],
            'settings'  => [],
        ];

        $result = $this->service->import($data, $userId);

        $this->assertSame(0, $result['users_created']);
        $this->assertSame(1, $result['users_skipped']);
    }

    public function test_import_v2_ignore_les_utilisateurs_avec_email_invalide(): void
    {
        $userId = $this->createUser();

        $data = [
            'version' => 2,
            'users'   => [
                ['email' => 'pas-un-email', 'password_hash' => '$2y$10$abc',
                 'role'  => 'user', 'created_at' => '2026-01-01 00:00:00'],
            ],
            'lists'     => [],
            'bookmarks' => [],
            'settings'  => [],
        ];

        $result = $this->service->import($data, $userId);

        $this->assertSame(0, $result['users_created']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_import_v2_applique_is_default_sur_la_bonne_liste(): void
    {
        $userId = $this->createUser();

        $data = [
            'version'   => 2,
            'users'     => [],
            'settings'  => [],
            'lists'     => [
                ['name' => 'Perso', 'is_default' => true],
                ['name' => 'Dev',   'is_default' => false],
            ],
            'bookmarks' => [],
        ];

        $this->service->import($data, $userId);

        $defaultName = $this->pdo
            ->query("SELECT name FROM lists WHERE is_default = 1")
            ->fetchColumn();

        $this->assertSame('Perso', $defaultName);
    }

    public function test_import_v2_full_restore_purge_tout(): void
    {
        $userId = $this->createUser('old@example.com');
        $listId = $this->createList('OldList');
        $this->createBookmark($userId, $listId, ['url' => 'https://old.example.com']);

        $data = [
            'version'   => 2,
            'users'     => [
                ['email' => 'new@example.com', 'password_hash' => '$2y$10$abc',
                 'role'  => 'admin', 'created_at' => '2026-01-01 00:00:00'],
            ],
            'settings'  => [],
            'lists'     => [['name' => 'NewList', 'is_default' => false]],
            'bookmarks' => [
                ['url' => 'https://new.example.com', 'user_email' => 'new@example.com',
                 'list_name' => 'NewList', 'visibility' => 'private',
                 'badge_style' => 'deepBlue', 'position' => 0,
                 'created_at' => '2026-01-01 00:00:00'],
            ],
        ];

        $this->service->import($data, $userId, fullRestore: true);

        // L'ancien utilisateur et ses données ont disparu
        $users = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(1, $users);

        $email = $this->pdo->query('SELECT email FROM users')->fetchColumn();
        $this->assertSame('new@example.com', $email);

        $bookmarks = (int) $this->pdo->query('SELECT COUNT(*) FROM bookmarks')->fetchColumn();
        $this->assertSame(1, $bookmarks);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Import HTML (Netscape Bookmarks)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_import_html_importe_les_liens_simples(): void
    {
        $userId = $this->createUser();
        $listId = $this->createList('Imports');

        $html = <<<HTML
        <!DOCTYPE NETSCAPE-Bookmark-file-1>
        <DL><p>
            <DT><A HREF="https://php.net" ADD_DATE="1700000000">PHP</A>
            <DT><A HREF="https://github.com" ADD_DATE="1700000001">GitHub</A>
        </DL>
        HTML;

        $result = $this->service->importHtml($html, $userId, $listId);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['folders_created']);
        $this->assertEmpty($result['errors']);
    }

    public function test_import_html_cree_les_dossiers(): void
    {
        $userId = $this->createUser();
        $listId = $this->createList('Imports');

        $html = <<<HTML
        <DL><p>
            <DT><H3>Dev</H3>
            <DL><p>
                <DT><A HREF="https://php.net">PHP</A>
            </DL>
        </DL>
        HTML;

        $result = $this->service->importHtml($html, $userId, $listId);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['folders_created']);
    }

    public function test_import_html_ignore_les_liens_non_http(): void
    {
        $userId = $this->createUser();
        $listId = $this->createList('Imports');

        $html = <<<HTML
        <DL><p>
            <DT><A HREF="javascript:void(0)">JS</A>
            <DT><A HREF="place:sort=8&maxResults=10">Firefox internal</A>
            <DT><A HREF="https://php.net">PHP</A>
        </DL>
        HTML;

        $result = $this->service->importHtml($html, $userId, $listId);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['skipped']);
    }

    public function test_import_html_preserve_la_hierarchie_de_dossiers(): void
    {
        $userId = $this->createUser();
        $listId = $this->createList('Imports');

        $html = <<<HTML
        <DL><p>
            <DT><H3>Outils</H3>
            <DL><p>
                <DT><H3>PHP</H3>
                <DL><p>
                    <DT><A HREF="https://packagist.org">Packagist</A>
                </DL>
            </DL>
        </DL>
        HTML;

        $result = $this->service->importHtml($html, $userId, $listId);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['folders_created']);

        // Le favori doit être dans le dossier PHP (le plus profond)
        $bm = $this->pdo->query('SELECT folder_id FROM bookmarks')->fetch();
        $folder = $this->pdo
            ->prepare('SELECT name FROM folders WHERE id = ?')
            ->execute([$bm['folder_id']]);

        $folders = $this->pdo->query('SELECT name, parent_id FROM folders')->fetchAll();
        $this->assertCount(2, $folders);

        $names = array_column($folders, 'name');
        $this->assertContains('Outils', $names);
        $this->assertContains('PHP', $names);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Aller-retour export → import
    // ══════════════════════════════════════════════════════════════════════════

    public function test_aller_retour_export_v1_puis_import(): void
    {
        $userId = $this->createUser();
        $listId = $this->createList('Dev');
        $this->createBookmark($userId, $listId, [
            'url'   => 'https://php.net',
            'title' => 'PHP',
        ]);

        // Export
        $exported = $this->service->export($userId);

        // Supprime tout manuellement (simule un import frais)
        $this->pdo->exec('DELETE FROM bookmarks');
        $this->pdo->exec('DELETE FROM lists');

        // Re-import
        $result = $this->service->import($exported, $userId);

        $this->assertSame(1, $result['imported']);
        $url = $this->pdo->query('SELECT url FROM bookmarks')->fetchColumn();
        $this->assertSame('https://php.net', $url);
    }

    public function test_aller_retour_export_v2_puis_import(): void
    {
        $userId = $this->createUser('alice@example.com');
        $listId = $this->createList('Dev', isDefault: true);
        $this->createBookmark($userId, $listId, ['url' => 'https://php.net']);

        $exported = $this->service->exportFull();

        // Full restore
        $newUserId = $this->createUser('temp@example.com'); // juste pour avoir un user courant
        $this->service->import($exported, $newUserId, fullRestore: true);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM bookmarks')->fetchColumn();
        $this->assertSame(1, $count);

        $defaultList = $this->pdo
            ->query("SELECT name FROM lists WHERE is_default = 1")
            ->fetchColumn();
        $this->assertSame('Dev', $defaultList);
    }
}

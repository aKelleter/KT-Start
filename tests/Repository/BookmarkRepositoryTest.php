<?php
declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\BookmarkRepository;
use Tests\TestCase;

/**
 * Tests de BookmarkRepository.
 *
 * Chaque test tourne sur une base SQLite en mémoire isolée.
 * Focus sur la logique métier non triviale :
 *   - Manipulation des tags (rename, delete, deleteUsedOnce, getAllTags)
 *   - Filtrage / recherche (findFiltered, countFiltered, findByUrl)
 *   - Vérification des liens (updateCheckStatus, resetCheckStatus, countDeadLinksAll)
 *   - Opérations en lot (deleteMultiple, reorder)
 */
final class BookmarkRepositoryTest extends TestCase
{
    private BookmarkRepository $repo;
    private int $userId;
    private int $listId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo   = new BookmarkRepository();
        $this->userId = $this->createUser();
        $this->listId = $this->createList('Dev');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // getAllTags — agrégation par fréquence
    // ══════════════════════════════════════════════════════════════════════════

    public function test_getAllTags_retourne_tableau_vide_si_aucun_tag(): void
    {
        $this->createBookmark($this->userId, $this->listId);

        $this->assertSame([], $this->repo->getAllTags($this->userId));
    }

    public function test_getAllTags_compte_la_frequence_de_chaque_tag(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,dev']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,css']);

        $tags = $this->repo->getAllTags($this->userId);

        $this->assertSame(2, $tags['php']);
        $this->assertSame(1, $tags['dev']);
        $this->assertSame(1, $tags['css']);
    }

    public function test_getAllTags_trie_par_frequence_decroissante(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,css,dev']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,css']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);

        $tags = $this->repo->getAllTags($this->userId);
        $keys = array_keys($tags);

        $this->assertSame('php', $keys[0]); // 3 occurrences
        $this->assertSame('css', $keys[1]); // 2 occurrences
        $this->assertSame('dev', $keys[2]); // 1 occurrence
    }

    public function test_getAllTags_isole_par_utilisateur(): void
    {
        $other = $this->createUser('other@example.com');
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);
        $this->createBookmark($other, $this->listId, ['tags' => 'java']);

        $tags = $this->repo->getAllTags($this->userId);

        $this->assertArrayHasKey('php', $tags);
        $this->assertArrayNotHasKey('java', $tags);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // renameTag — renommage dans tous les favoris
    // ══════════════════════════════════════════════════════════════════════════

    public function test_renameTag_renomme_dans_tous_les_favoris(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,dev']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,css']);

        $count = $this->repo->renameTag('php', 'PHP');

        $this->assertSame(2, $count);

        $rows = $this->pdo->query('SELECT tags FROM bookmarks ORDER BY id')->fetchAll();
        $this->assertStringContainsString('PHP', $rows[0]['tags']);
        $this->assertStringContainsString('PHP', $rows[1]['tags']);
        $this->assertStringNotContainsString('php', $rows[0]['tags']);
    }

    public function test_renameTag_ne_confond_pas_avec_un_prefixe(): void
    {
        // "php" ne doit pas correspondre à "php-dev"
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php-dev']);

        $count = $this->repo->renameTag('php', 'PHP');

        $this->assertSame(0, $count);
        $tags = $this->pdo->query('SELECT tags FROM bookmarks')->fetchColumn();
        $this->assertSame('php-dev', $tags);
    }

    public function test_renameTag_deduplique_si_nouveau_nom_deja_present(): void
    {
        // Le favori a "php,PHP" → après rename 'php'→'PHP' : dedup → "PHP"
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,PHP']);

        $this->repo->renameTag('php', 'PHP');

        $tags = $this->pdo->query('SELECT tags FROM bookmarks')->fetchColumn();
        $this->assertSame('PHP', $tags);
    }

    public function test_renameTag_retourne_zero_si_tag_absent(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'css']);

        $count = $this->repo->renameTag('php', 'PHP');

        $this->assertSame(0, $count);
    }

    public function test_renameTag_preserve_les_autres_tags_du_favori(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,dev,css']);

        $this->repo->renameTag('php', 'PHP');

        $tags = $this->pdo->query('SELECT tags FROM bookmarks')->fetchColumn();
        $parts = explode(',', $tags);
        $this->assertContains('PHP', $parts);
        $this->assertContains('dev', $parts);
        $this->assertContains('css', $parts);
        $this->assertNotContains('php', $parts);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // deleteTag — suppression d'un tag de tous les favoris
    // ══════════════════════════════════════════════════════════════════════════

    public function test_deleteTag_supprime_le_tag_dans_tous_les_favoris(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,dev']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);

        $count = $this->repo->deleteTag('php');

        $this->assertSame(2, $count);

        $rows = $this->pdo->query('SELECT tags FROM bookmarks')->fetchAll();
        foreach ($rows as $row) {
            $this->assertStringNotContainsString('php', $row['tags']);
        }
    }

    public function test_deleteTag_ne_confond_pas_avec_un_prefixe(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php-dev']);

        $count = $this->repo->deleteTag('php');

        $this->assertSame(0, $count);
        $tags = $this->pdo->query('SELECT tags FROM bookmarks')->fetchColumn();
        $this->assertSame('php-dev', $tags);
    }

    public function test_deleteTag_preserve_les_autres_tags(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,dev,css']);

        $this->repo->deleteTag('php');

        $tags = $this->pdo->query('SELECT tags FROM bookmarks')->fetchColumn();
        $parts = explode(',', $tags);
        $this->assertContains('dev', $parts);
        $this->assertContains('css', $parts);
        $this->assertNotContains('php', $parts);
    }

    public function test_deleteTag_retourne_zero_si_tag_absent(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'css']);

        $this->assertSame(0, $this->repo->deleteTag('php'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // deleteTagsUsedOnce — nettoyage des tags uniques
    // ══════════════════════════════════════════════════════════════════════════

    public function test_deleteTagsUsedOnce_supprime_les_tags_utilises_une_seule_fois(): void
    {
        // "php" → 2 favoris (conservé) ; "unique" → 1 favori (supprimé)
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,unique']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);

        $count = $this->repo->deleteTagsUsedOnce();

        $this->assertSame(1, $count); // "unique" supprimé

        $rows = $this->pdo->query('SELECT tags FROM bookmarks ORDER BY id')->fetchAll();
        $this->assertStringNotContainsString('unique', $rows[0]['tags']);
        $this->assertStringContainsString('php', $rows[0]['tags']);
        $this->assertSame('php', $rows[1]['tags']);
    }

    public function test_deleteTagsUsedOnce_retourne_zero_si_aucun_tag_unique(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);

        $this->assertSame(0, $this->repo->deleteTagsUsedOnce());
    }

    public function test_deleteTagsUsedOnce_retourne_zero_si_aucun_favori(): void
    {
        $this->assertSame(0, $this->repo->deleteTagsUsedOnce());
    }

    public function test_deleteTagsUsedOnce_supprime_plusieurs_tags_uniques(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'solo1,commun']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'solo2,commun']);

        $count = $this->repo->deleteTagsUsedOnce();

        $this->assertSame(2, $count); // solo1 et solo2 supprimés
    }

    // ══════════════════════════════════════════════════════════════════════════
    // findFiltered / countFiltered — recherche et filtres
    // ══════════════════════════════════════════════════════════════════════════

    public function test_findFiltered_isole_par_utilisateur(): void
    {
        $other = $this->createUser('other@example.com');
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://mine.io']);
        $this->createBookmark($other, $this->listId, ['url' => 'https://theirs.io']);

        $results = $this->repo->findFiltered($this->userId, null, null, 'position');

        $this->assertCount(1, $results);
        $this->assertSame('https://mine.io', $results[0]['url']);
    }

    public function test_findFiltered_par_liste(): void
    {
        $list2 = $this->createList('Perso');
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://dev.io']);
        $this->createBookmark($this->userId, $list2, ['url' => 'https://perso.io']);

        $results = $this->repo->findFiltered($this->userId, $this->listId, null, 'position');

        $this->assertCount(1, $results);
        $this->assertSame('https://dev.io', $results[0]['url']);
    }

    public function test_findFiltered_par_tag_exact(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php,dev']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'css']);
        // "php-dev" ne doit pas être retourné pour le tag "php"
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php-dev']);

        $results = $this->repo->findFiltered($this->userId, null, 'php', 'position');

        $this->assertCount(1, $results);
        $this->assertSame('php,dev', $results[0]['tags']);
    }

    public function test_findFiltered_par_recherche_texte(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['title' => 'PHP Manuel', 'url' => 'https://php.net']);
        $this->createBookmark($this->userId, $this->listId, ['title' => 'GitHub', 'url' => 'https://github.com']);

        $results = $this->repo->findFiltered($this->userId, null, null, 'position', search: 'PHP');

        $this->assertCount(1, $results);
        $this->assertSame('PHP Manuel', $results[0]['title']);
    }

    public function test_countFiltered_correspond_au_nombre_de_resultats(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'css']);

        $count   = $this->repo->countFiltered($this->userId, null, 'php');
        $results = $this->repo->findFiltered($this->userId, null, 'php', 'position');

        $this->assertSame(2, $count);
        $this->assertCount($count, $results);
    }

    public function test_findFiltered_recherche_dans_les_tags(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'symfony,php']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'css']);

        $results = $this->repo->findFiltered($this->userId, null, null, 'position', search: 'symfony');

        $this->assertCount(1, $results);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // findByUrl — détection de doublon
    // ══════════════════════════════════════════════════════════════════════════

    public function test_findByUrl_retourne_false_si_url_absente(): void
    {
        $result = $this->repo->findByUrl($this->userId, 'https://inexistant.io');

        $this->assertFalse($result);
    }

    public function test_findByUrl_retourne_le_favori_si_url_trouvee(): void
    {
        $this->createBookmark($this->userId, $this->listId, [
            'url'   => 'https://php.net',
            'title' => 'PHP',
        ]);

        $result = $this->repo->findByUrl($this->userId, 'https://php.net');

        $this->assertIsArray($result);
        $this->assertSame('PHP', $result['title']);
    }

    public function test_findByUrl_isole_par_utilisateur(): void
    {
        $other = $this->createUser('other@example.com');
        $this->createBookmark($other, $this->listId, ['url' => 'https://php.net']);

        $result = $this->repo->findByUrl($this->userId, 'https://php.net');

        $this->assertFalse($result);
    }

    public function test_findByUrl_avec_excludeId_ignore_le_favori_en_cours_dedition(): void
    {
        $id = $this->createBookmark($this->userId, $this->listId, ['url' => 'https://php.net']);

        // En mode édition : la même URL ne doit pas se signaler comme doublon
        $result = $this->repo->findByUrl($this->userId, 'https://php.net', excludeId: $id);

        $this->assertFalse($result);
    }

    public function test_findByUrl_avec_excludeId_detecte_doublon_sur_autre_favori(): void
    {
        $id1 = $this->createBookmark($this->userId, $this->listId, ['url' => 'https://php.net', 'title' => 'PHP']);
        $id2 = $this->createBookmark($this->userId, $this->listId, ['url' => 'https://github.com']);

        // id2 est en cours d'édition, mais https://php.net existe déjà (id1)
        $result = $this->repo->findByUrl($this->userId, 'https://php.net', excludeId: $id2);

        $this->assertIsArray($result);
        $this->assertSame($id1, (int) $result['id']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // updateCheckStatus / resetCheckStatus / countDeadLinksAll
    // ══════════════════════════════════════════════════════════════════════════

    public function test_updateCheckStatus_enregistre_statut_et_code_http(): void
    {
        $id = $this->createBookmark($this->userId, $this->listId);

        $this->repo->updateCheckStatus($id, 'ok', '2026-04-14 10:00:00', httpCode: 200);

        $row = $this->pdo
            ->query("SELECT last_check_status, last_http_code FROM bookmarks")
            ->fetch();

        $this->assertSame('ok', $row['last_check_status']);
        $this->assertSame(200, (int) $row['last_http_code']);
    }

    public function test_updateCheckStatus_stocke_null_si_timeout(): void
    {
        $id = $this->createBookmark($this->userId, $this->listId);

        // httpCode = 0 (défaut) → NULL en base
        $this->repo->updateCheckStatus($id, 'timeout', '2026-04-14 10:00:00');

        $code = $this->pdo
            ->query("SELECT last_http_code FROM bookmarks")
            ->fetchColumn();

        $this->assertNull($code);
    }

    public function test_resetCheckStatus_remet_a_null_pour_le_user(): void
    {
        $id1 = $this->createBookmark($this->userId, $this->listId);
        $id2 = $this->createBookmark($this->userId, $this->listId);
        $this->repo->updateCheckStatus($id1, 'ok', '2026-04-14 10:00:00', 200);
        $this->repo->updateCheckStatus($id2, 'error', '2026-04-14 10:00:00', 404);

        $affected = $this->repo->resetCheckStatus($this->userId);

        $this->assertSame(2, $affected);

        $rows = $this->pdo->query('SELECT last_check_status FROM bookmarks')->fetchAll();
        foreach ($rows as $row) {
            $this->assertNull($row['last_check_status']);
        }
    }

    public function test_resetCheckStatus_naffecte_pas_les_autres_utilisateurs(): void
    {
        $other  = $this->createUser('other@example.com');
        $id     = $this->createBookmark($other, $this->listId);
        $this->repo->updateCheckStatus($id, 'error', '2026-04-14 10:00:00', 500);

        $this->repo->resetCheckStatus($this->userId);

        $status = $this->pdo->query('SELECT last_check_status FROM bookmarks')->fetchColumn();
        $this->assertSame('error', $status); // inchangé
    }

    public function test_countDeadLinksAll_compte_error_et_timeout(): void
    {
        $id1 = $this->createBookmark($this->userId, $this->listId);
        $id2 = $this->createBookmark($this->userId, $this->listId);
        $id3 = $this->createBookmark($this->userId, $this->listId);
        $this->repo->updateCheckStatus($id1, 'error',   '2026-04-14', 404);
        $this->repo->updateCheckStatus($id2, 'timeout', '2026-04-14');
        $this->repo->updateCheckStatus($id3, 'ok',      '2026-04-14', 200);

        $this->assertSame(2, $this->repo->countDeadLinksAll());
    }

    public function test_countDeadLinksAll_retourne_zero_si_aucun_lien_mort(): void
    {
        $id = $this->createBookmark($this->userId, $this->listId);
        $this->repo->updateCheckStatus($id, 'ok', '2026-04-14', 200);

        $this->assertSame(0, $this->repo->countDeadLinksAll());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // deleteMultiple — suppression en lot
    // ══════════════════════════════════════════════════════════════════════════

    public function test_deleteMultiple_supprime_les_ids_du_bon_utilisateur(): void
    {
        $id1 = $this->createBookmark($this->userId, $this->listId);
        $id2 = $this->createBookmark($this->userId, $this->listId);
        $id3 = $this->createBookmark($this->userId, $this->listId);

        $affected = $this->repo->deleteMultiple($this->userId, [$id1, $id2]);

        $this->assertSame(2, $affected);

        $remaining = (int) $this->pdo->query('SELECT COUNT(*) FROM bookmarks')->fetchColumn();
        $this->assertSame(1, $remaining);
    }

    public function test_deleteMultiple_naffecte_pas_les_favoris_dun_autre_user(): void
    {
        $other = $this->createUser('other@example.com');
        $id    = $this->createBookmark($other, $this->listId);

        $affected = $this->repo->deleteMultiple($this->userId, [$id]);

        $this->assertSame(0, $affected);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM bookmarks')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_deleteMultiple_retourne_zero_si_liste_vide(): void
    {
        $this->createBookmark($this->userId, $this->listId);

        $this->assertSame(0, $this->repo->deleteMultiple($this->userId, []));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // reorder — mise à jour des positions
    // ══════════════════════════════════════════════════════════════════════════

    public function test_reorder_met_a_jour_les_positions(): void
    {
        $id1 = $this->createBookmark($this->userId, $this->listId, ['position' => 0]);
        $id2 = $this->createBookmark($this->userId, $this->listId, ['position' => 1]);
        $id3 = $this->createBookmark($this->userId, $this->listId, ['position' => 2]);

        // Inversion : id3, id1, id2
        $this->repo->reorder($this->userId, [$id3, $id1, $id2]);

        $rows = $this->pdo
            ->query('SELECT id, position FROM bookmarks ORDER BY position ASC')
            ->fetchAll();

        $this->assertSame((string) $id3, (string) $rows[0]['id']);
        $this->assertSame((string) $id1, (string) $rows[1]['id']);
        $this->assertSame((string) $id2, (string) $rows[2]['id']);
    }

    public function test_reorder_naffecte_pas_les_favoris_dun_autre_user(): void
    {
        $other = $this->createUser('other@example.com');
        $idOther = $this->createBookmark($other, $this->listId, ['position' => 99]);

        $this->repo->reorder($this->userId, [$idOther]);

        $pos = $this->pdo->query('SELECT position FROM bookmarks')->fetchColumn();
        $this->assertSame(99, (int) $pos); // inchangé
    }
}

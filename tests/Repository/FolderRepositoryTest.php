<?php
declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\FolderRepository;
use Tests\TestCase;

/**
 * Tests de FolderRepository.
 *
 * Chaque test tourne sur une base SQLite en mémoire isolée.
 * Focus sur la logique métier non triviale :
 *   - Détection de cycle (wouldCreateCycle / wouldCreateCycleAdmin)
 *   - Suppression avec remontée des enfants (deleteAndLiftChildren / deleteAndLiftChildrenAdmin)
 */
final class FolderRepositoryTest extends TestCase
{
    private FolderRepository $repo;
    private int $userId;
    private int $listId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo   = new FolderRepository();
        $this->userId = $this->createUser();
        $this->listId = $this->createList('Dev');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // wouldCreateCycle — détection de cycle (avec contrainte user/list)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_pas_de_cycle_si_parent_null(): void
    {
        $a = $this->createFolder($this->userId, $this->listId, 'A');

        $this->assertFalse(
            $this->repo->wouldCreateCycle($a, null, $this->userId, $this->listId)
        );
    }

    public function test_cycle_si_dossier_est_son_propre_parent(): void
    {
        $a = $this->createFolder($this->userId, $this->listId, 'A');

        $this->assertTrue(
            $this->repo->wouldCreateCycle($a, $a, $this->userId, $this->listId)
        );
    }

    public function test_cycle_direct_enfant_devient_parent(): void
    {
        // Arbre : A → B (B est enfant de A)
        // On essaie de faire de A un enfant de B → cycle A→B→A
        $a = $this->createFolder($this->userId, $this->listId, 'A');
        $b = $this->createFolder($this->userId, $this->listId, 'B', parentId: $a);

        $this->assertTrue(
            $this->repo->wouldCreateCycle($a, $b, $this->userId, $this->listId)
        );
    }

    public function test_cycle_profond_petit_fils_devient_parent(): void
    {
        // Arbre : A → B → C
        // On essaie de faire de A un enfant de C → cycle A→B→C→A
        $a = $this->createFolder($this->userId, $this->listId, 'A');
        $b = $this->createFolder($this->userId, $this->listId, 'B', parentId: $a);
        $c = $this->createFolder($this->userId, $this->listId, 'C', parentId: $b);

        $this->assertTrue(
            $this->repo->wouldCreateCycle($a, $c, $this->userId, $this->listId)
        );
    }

    public function test_pas_de_cycle_pour_deplacer_vers_un_cousin(): void
    {
        // Arbre :
        //   A → B
        //   C → D
        // Déplacer C comme enfant de B → aucun cycle
        $a = $this->createFolder($this->userId, $this->listId, 'A');
        $b = $this->createFolder($this->userId, $this->listId, 'B', parentId: $a);
        $c = $this->createFolder($this->userId, $this->listId, 'C');
        $this->createFolder($this->userId, $this->listId, 'D', parentId: $c);

        $this->assertFalse(
            $this->repo->wouldCreateCycle($c, $b, $this->userId, $this->listId)
        );
    }

    public function test_cycle_detecte_si_user_different_dans_la_chaine(): void
    {
        // Un parent appartenant à un autre user déclenche true (sécurité)
        $otherUser = $this->createUser('other@example.com');
        $a         = $this->createFolder($this->userId, $this->listId, 'A');
        $foreign   = $this->createFolder($otherUser, $this->listId, 'Foreign', parentId: $a);

        $this->assertTrue(
            $this->repo->wouldCreateCycle($a, $foreign, $this->userId, $this->listId)
        );
    }

    public function test_cycle_detecte_si_liste_differente_dans_la_chaine(): void
    {
        $otherList = $this->createList('Autre');
        $a         = $this->createFolder($this->userId, $this->listId, 'A');
        $foreign   = $this->createFolder($this->userId, $otherList, 'Foreign', parentId: $a);

        $this->assertTrue(
            $this->repo->wouldCreateCycle($a, $foreign, $this->userId, $this->listId)
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // wouldCreateCycleAdmin — même logique sans contrainte user/list
    // ══════════════════════════════════════════════════════════════════════════

    public function test_admin_pas_de_cycle_si_parent_independant(): void
    {
        $a = $this->createFolder($this->userId, $this->listId, 'A');
        $b = $this->createFolder($this->userId, $this->listId, 'B');

        $this->assertFalse($this->repo->wouldCreateCycleAdmin($a, $b));
    }

    public function test_admin_cycle_si_meme_id(): void
    {
        $a = $this->createFolder($this->userId, $this->listId, 'A');

        $this->assertTrue($this->repo->wouldCreateCycleAdmin($a, $a));
    }

    public function test_admin_cycle_enfant_devient_parent(): void
    {
        $a = $this->createFolder($this->userId, $this->listId, 'A');
        $b = $this->createFolder($this->userId, $this->listId, 'B', parentId: $a);

        $this->assertTrue($this->repo->wouldCreateCycleAdmin($a, $b));
    }

    public function test_admin_cycle_profond(): void
    {
        $a = $this->createFolder($this->userId, $this->listId, 'A');
        $b = $this->createFolder($this->userId, $this->listId, 'B', parentId: $a);
        $c = $this->createFolder($this->userId, $this->listId, 'C', parentId: $b);

        $this->assertTrue($this->repo->wouldCreateCycleAdmin($a, $c));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // deleteAndLiftChildren — suppression avec remontée des enfants (user)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_delete_retourne_false_si_dossier_inexistant(): void
    {
        $this->assertFalse($this->repo->deleteAndLiftChildren(999, $this->userId));
    }

    public function test_delete_retourne_false_si_mauvais_user(): void
    {
        $other = $this->createUser('other@example.com');
        $a     = $this->createFolder($this->userId, $this->listId, 'A');

        $this->assertFalse($this->repo->deleteAndLiftChildren($a, $other));

        // Le dossier doit toujours exister
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM folders')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_delete_simple_sans_enfants(): void
    {
        $a = $this->createFolder($this->userId, $this->listId, 'A');

        $result = $this->repo->deleteAndLiftChildren($a, $this->userId);

        $this->assertTrue($result);
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM folders')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_delete_remonte_les_sous_dossiers_a_la_racine(): void
    {
        // Supprimer A (racine) → B et C passent à parent_id = NULL
        $a = $this->createFolder($this->userId, $this->listId, 'A');
        $b = $this->createFolder($this->userId, $this->listId, 'B', parentId: $a);
        $c = $this->createFolder($this->userId, $this->listId, 'C', parentId: $a);

        $this->repo->deleteAndLiftChildren($a, $this->userId);

        $rows = $this->pdo->query('SELECT name, parent_id FROM folders ORDER BY name')->fetchAll();
        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertNull($row['parent_id'], "Le dossier {$row['name']} devrait être à la racine");
        }
    }

    public function test_delete_remonte_les_sous_dossiers_vers_le_parent(): void
    {
        // Arbre : A → B → C
        // Supprimer B → C doit passer sous A
        $a = $this->createFolder($this->userId, $this->listId, 'A');
        $b = $this->createFolder($this->userId, $this->listId, 'B', parentId: $a);
        $c = $this->createFolder($this->userId, $this->listId, 'C', parentId: $b);

        $this->repo->deleteAndLiftChildren($b, $this->userId);

        $rows = $this->pdo->query('SELECT name, parent_id FROM folders ORDER BY name')->fetchAll();
        $this->assertCount(2, $rows);

        $byName = array_column($rows, null, 'name');
        $this->assertNull($byName['A']['parent_id']);
        $this->assertSame((string) $a, (string) $byName['C']['parent_id']);
    }

    public function test_delete_remonte_les_favoris_vers_le_dossier_parent(): void
    {
        // Arbre : A → B, favori dans B
        // Supprimer B → favori doit passer dans A
        $a  = $this->createFolder($this->userId, $this->listId, 'A');
        $b  = $this->createFolder($this->userId, $this->listId, 'B', parentId: $a);
        $bm = $this->createBookmark($this->userId, $this->listId, ['folder_id' => $b]);

        $this->repo->deleteAndLiftChildren($b, $this->userId);

        $folderId = $this->pdo
            ->query('SELECT folder_id FROM bookmarks')
            ->fetchColumn();

        $this->assertSame((string) $a, (string) $folderId);
    }

    public function test_delete_remonte_les_favoris_a_la_racine_si_pas_de_parent(): void
    {
        // Dossier A à la racine, favori dedans
        // Supprimer A → favori passe à folder_id = NULL
        $a  = $this->createFolder($this->userId, $this->listId, 'A');
        $bm = $this->createBookmark($this->userId, $this->listId, ['folder_id' => $a]);

        $this->repo->deleteAndLiftChildren($a, $this->userId);

        $folderId = $this->pdo
            ->query('SELECT folder_id FROM bookmarks')
            ->fetchColumn();

        $this->assertNull($folderId);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // deleteAndLiftChildrenAdmin — même logique sans contrainte user
    // ══════════════════════════════════════════════════════════════════════════

    public function test_admin_delete_retourne_false_si_dossier_inexistant(): void
    {
        $this->assertFalse($this->repo->deleteAndLiftChildrenAdmin(999));
    }

    public function test_admin_delete_remonte_sous_dossiers_et_favoris(): void
    {
        $otherUser = $this->createUser('other@example.com');

        // Dossier A appartenant à un autre user, avec B en enfant et un favori
        $a  = $this->createFolder($otherUser, $this->listId, 'A');
        $b  = $this->createFolder($otherUser, $this->listId, 'B', parentId: $a);
        $bm = $this->createBookmark($otherUser, $this->listId, ['folder_id' => $a]);

        $result = $this->repo->deleteAndLiftChildrenAdmin($a);

        $this->assertTrue($result);

        // B doit être remonté à la racine
        $parentId = $this->pdo->query('SELECT parent_id FROM folders')->fetchColumn();
        $this->assertNull($parentId);

        // Le favori doit être à la racine
        $folderId = $this->pdo->query('SELECT folder_id FROM bookmarks')->fetchColumn();
        $this->assertNull($folderId);
    }
}

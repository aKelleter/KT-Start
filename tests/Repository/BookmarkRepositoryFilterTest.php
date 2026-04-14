<?php
declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\BookmarkRepository;
use Tests\TestCase;

/**
 * Tests de BookmarkRepository — recherche, filtres, tris et pagination.
 *
 * Complète BookmarkRepositoryTest en couvrant :
 *   - Tris (orderBy via findFiltered / findPublic)
 *   - Pagination (limit / offset)
 *   - Visibilité publique (findPublic / countPublic)
 *   - Recherche multi-champs (host, description, badge_text, url)
 *   - Filtres combinés (liste + tag + recherche)
 */
final class BookmarkRepositoryFilterTest extends TestCase
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
    // Tris — findFiltered avec différents sort
    // ══════════════════════════════════════════════════════════════════════════

    public function test_tri_title_asc(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['title' => 'Zend',   'position' => 2]);
        $this->createBookmark($this->userId, $this->listId, ['title' => 'Alpine', 'position' => 0]);
        $this->createBookmark($this->userId, $this->listId, ['title' => 'Mango',  'position' => 1]);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'title');

        $this->assertSame('Alpine', $rows[0]['title']);
        $this->assertSame('Mango',  $rows[1]['title']);
        $this->assertSame('Zend',   $rows[2]['title']);
    }

    public function test_tri_title_desc(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['title' => 'Alpine']);
        $this->createBookmark($this->userId, $this->listId, ['title' => 'Zend']);
        $this->createBookmark($this->userId, $this->listId, ['title' => 'Mango']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'title_desc');

        $this->assertSame('Zend',   $rows[0]['title']);
        $this->assertSame('Mango',  $rows[1]['title']);
        $this->assertSame('Alpine', $rows[2]['title']);
    }

    public function test_tri_title_est_insensible_a_la_casse(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['title' => 'zebra']);
        $this->createBookmark($this->userId, $this->listId, ['title' => 'Apple']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'title');

        $this->assertSame('Apple', $rows[0]['title']);
        $this->assertSame('zebra', $rows[1]['title']);
    }

    public function test_tri_host_asc(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://z.io', 'host' => 'z.io']);
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://a.io', 'host' => 'a.io']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'host');

        $this->assertSame('a.io', $rows[0]['host']);
        $this->assertSame('z.io', $rows[1]['host']);
    }

    public function test_tri_date_asc(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://new.io', 'created_at' => '2026-03-01 00:00:00']);
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://old.io', 'created_at' => '2025-01-01 00:00:00']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'date_asc');

        $this->assertSame('https://old.io', $rows[0]['url']);
        $this->assertSame('https://new.io', $rows[1]['url']);
    }

    public function test_tri_date_desc(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://new.io', 'created_at' => '2026-03-01 00:00:00']);
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://old.io', 'created_at' => '2025-01-01 00:00:00']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'date_desc');

        $this->assertSame('https://new.io', $rows[0]['url']);
        $this->assertSame('https://old.io', $rows[1]['url']);
    }

    public function test_tri_badge_text_asc_insensible_a_la_casse(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['badge_text' => 'Zend']);
        $this->createBookmark($this->userId, $this->listId, ['badge_text' => 'alpha']);
        $this->createBookmark($this->userId, $this->listId, ['badge_text' => 'Mango']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'badge_text');

        $this->assertSame('alpha', $rows[0]['badge_text']);
        $this->assertSame('Mango', $rows[1]['badge_text']);
        $this->assertSame('Zend',  $rows[2]['badge_text']);
    }

    public function test_tri_position_par_defaut(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://b.io', 'position' => 1]);
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://a.io', 'position' => 0]);
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://c.io', 'position' => 2]);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position');

        $this->assertSame('https://a.io', $rows[0]['url']);
        $this->assertSame('https://b.io', $rows[1]['url']);
        $this->assertSame('https://c.io', $rows[2]['url']);
    }

    public function test_tri_visibility_asc_met_private_avant_public(): void
    {
        // 'private' < 'public' alphabétiquement
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://pub.io', 'visibility' => 'public',  'position' => 0]);
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://prv.io', 'visibility' => 'private', 'position' => 1]);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'visibility_asc');

        $this->assertSame('private', $rows[0]['visibility']);
        $this->assertSame('public',  $rows[1]['visibility']);
    }

    public function test_tri_list_asc_par_nom_de_liste(): void
    {
        $listA = $this->createList('Alpha');
        $listZ = $this->createList('Zeta');
        $this->createBookmark($this->userId, $listZ, ['url' => 'https://z.io']);
        $this->createBookmark($this->userId, $listA, ['url' => 'https://a.io']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'list_asc');

        $this->assertSame('Alpha', $rows[0]['list_name']);
        $this->assertSame('Zeta',  $rows[1]['list_name']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Pagination — limit / offset dans findFiltered
    // ══════════════════════════════════════════════════════════════════════════

    public function test_pagination_limit_borne_les_resultats(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createBookmark($this->userId, $this->listId, ['url' => "https://site$i.io", 'position' => $i]);
        }

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', limit: 2);

        $this->assertCount(2, $rows);
    }

    public function test_pagination_offset_saute_les_premiers_resultats(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createBookmark($this->userId, $this->listId, ['url' => "https://site$i.io", 'position' => $i]);
        }

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', limit: 2, offset: 2);

        $this->assertCount(2, $rows);
        $this->assertSame('https://site2.io', $rows[0]['url']);
        $this->assertSame('https://site3.io', $rows[1]['url']);
    }

    public function test_pagination_limit_zero_retourne_tout(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createBookmark($this->userId, $this->listId, ['url' => "https://site$i.io"]);
        }

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', limit: 0);

        $this->assertCount(5, $rows);
    }

    public function test_pagination_derniere_page_partielle(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createBookmark($this->userId, $this->listId, ['url' => "https://site$i.io", 'position' => $i]);
        }

        // Page 3 (offset=4) avec limite=2 → seulement 1 résultat
        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', limit: 2, offset: 4);

        $this->assertCount(1, $rows);
        $this->assertSame('https://site4.io', $rows[0]['url']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // findPublic / countPublic — visibilité publique
    // ══════════════════════════════════════════════════════════════════════════

    public function test_findPublic_nexpose_pas_les_favoris_prives(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'private']);
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public', 'url' => 'https://pub.io']);

        $rows = $this->repo->findPublic(null, null, 'position');

        $this->assertCount(1, $rows);
        $this->assertSame('https://pub.io', $rows[0]['url']);
    }

    public function test_findPublic_retourne_les_favoris_de_tous_les_utilisateurs(): void
    {
        $other = $this->createUser('other@example.com');
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public', 'url' => 'https://alice.io']);
        $this->createBookmark($other,        $this->listId, ['visibility' => 'public', 'url' => 'https://bob.io']);

        $rows = $this->repo->findPublic(null, null, 'position');

        $this->assertCount(2, $rows);
    }

    public function test_findPublic_filtre_par_liste(): void
    {
        $list2 = $this->createList('Perso');
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public', 'url' => 'https://dev.io']);
        $this->createBookmark($this->userId, $list2,        ['visibility' => 'public', 'url' => 'https://perso.io']);

        $rows = $this->repo->findPublic($this->listId, null, 'position');

        $this->assertCount(1, $rows);
        $this->assertSame('https://dev.io', $rows[0]['url']);
    }

    public function test_findPublic_filtre_par_tag(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public', 'tags' => 'php', 'url' => 'https://php.io']);
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public', 'tags' => 'css', 'url' => 'https://css.io']);

        $rows = $this->repo->findPublic(null, 'php', 'position');

        $this->assertCount(1, $rows);
        $this->assertSame('https://php.io', $rows[0]['url']);
    }

    public function test_findPublic_filtre_par_recherche(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public', 'title' => 'PHP Manuel', 'url' => 'https://php.net']);
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public', 'title' => 'GitHub',     'url' => 'https://github.com']);

        $rows = $this->repo->findPublic(null, null, 'position', search: 'PHP');

        $this->assertCount(1, $rows);
        $this->assertSame('PHP Manuel', $rows[0]['title']);
    }

    public function test_findPublic_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createBookmark($this->userId, $this->listId, [
                'visibility' => 'public',
                'url'        => "https://pub$i.io",
                'position'   => $i,
            ]);
        }

        $rows = $this->repo->findPublic(null, null, 'position', limit: 2, offset: 1);

        $this->assertCount(2, $rows);
        $this->assertSame('https://pub1.io', $rows[0]['url']);
    }

    public function test_countPublic_ne_compte_que_les_favoris_publics(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public']);
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public']);
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'private']);

        $this->assertSame(2, $this->repo->countPublic(null, null));
    }

    public function test_countPublic_filtre_par_liste(): void
    {
        $list2 = $this->createList('Perso');
        $this->createBookmark($this->userId, $this->listId, ['visibility' => 'public']);
        $this->createBookmark($this->userId, $list2,        ['visibility' => 'public']);

        $this->assertSame(1, $this->repo->countPublic($this->listId, null));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Recherche multi-champs
    // ══════════════════════════════════════════════════════════════════════════

    public function test_recherche_dans_le_host(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://github.com/foo', 'host' => 'github.com']);
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://gitlab.com/bar', 'host' => 'gitlab.com']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', search: 'github');

        $this->assertCount(1, $rows);
        $this->assertSame('github.com', $rows[0]['host']);
    }

    public function test_recherche_dans_la_description(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['description' => 'Framework PHP moderne']);
        $this->createBookmark($this->userId, $this->listId, ['description' => 'Gestionnaire de paquets JS']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', search: 'PHP');

        $this->assertCount(1, $rows);
        $this->assertSame('Framework PHP moderne', $rows[0]['description']);
    }

    public function test_recherche_dans_le_badge_text(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['badge_text' => 'Outils Dev']);
        $this->createBookmark($this->userId, $this->listId, ['badge_text' => 'Lecture']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', search: 'Dev');

        $this->assertCount(1, $rows);
        $this->assertSame('Outils Dev', $rows[0]['badge_text']);
    }

    public function test_recherche_dans_lurl(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://docs.symfony.com/fr/']);
        $this->createBookmark($this->userId, $this->listId, ['url' => 'https://laravel.com/docs']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', search: 'symfony');

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('symfony', $rows[0]['url']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Filtres combinés
    // ══════════════════════════════════════════════════════════════════════════

    public function test_filtre_combine_liste_et_tag(): void
    {
        $list2 = $this->createList('Perso');
        // Bonne liste, bon tag → doit apparaître
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php', 'url' => 'https://match.io']);
        // Bonne liste, mauvais tag
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'css', 'url' => 'https://wrong-tag.io']);
        // Mauvaise liste, bon tag
        $this->createBookmark($this->userId, $list2, ['tags' => 'php', 'url' => 'https://wrong-list.io']);

        $rows = $this->repo->findFiltered($this->userId, $this->listId, 'php', 'position');

        $this->assertCount(1, $rows);
        $this->assertSame('https://match.io', $rows[0]['url']);
    }

    public function test_filtre_combine_liste_tag_et_recherche(): void
    {
        $list2 = $this->createList('Perso');

        // Tous les critères correspondent
        $this->createBookmark($this->userId, $this->listId, [
            'url'   => 'https://packagist.org',
            'title' => 'Packagist',
            'tags'  => 'php,composer',
        ]);
        // Bonne liste, bon tag, mauvais texte
        $this->createBookmark($this->userId, $this->listId, [
            'url'   => 'https://php.net',
            'title' => 'PHP',
            'tags'  => 'php',
        ]);
        // Tout correspond sauf la liste
        $this->createBookmark($this->userId, $list2, [
            'url'   => 'https://packagist.io',
            'title' => 'Packagist mirror',
            'tags'  => 'php,composer',
        ]);

        $rows = $this->repo->findFiltered($this->userId, $this->listId, 'composer', 'position', search: 'Packagist');

        $this->assertCount(1, $rows);
        $this->assertSame('https://packagist.org', $rows[0]['url']);
    }

    public function test_filtre_tag_vide_ne_filtre_pas(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'php']);
        $this->createBookmark($this->userId, $this->listId, ['tags' => 'css']);

        // tag='' ne doit pas filtrer (équivalent à tag=null)
        $rows = $this->repo->findFiltered($this->userId, null, '', 'position');

        $this->assertCount(2, $rows);
    }

    public function test_filtre_recherche_vide_ne_filtre_pas(): void
    {
        $this->createBookmark($this->userId, $this->listId, ['title' => 'PHP']);
        $this->createBookmark($this->userId, $this->listId, ['title' => 'CSS']);

        $rows = $this->repo->findFiltered($this->userId, null, null, 'position', search: '');

        $this->assertCount(2, $rows);
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;
use App\Repository\BookmarkRepository;
use App\Repository\ListRepository;
use App\Service\UrlMetaService;

final class BookmarkController
{
    public function home(): void
    {
        if (Auth::check()) {
            Response::redirect('?action=bookmarks');
        }

        $listId = isset($_GET['list']) && $_GET['list'] !== '' ? (int) $_GET['list'] : null;
        $tag    = $_GET['tag'] ?? '';
        $sort   = $_GET['sort'] ?? 'position';
        $view   = in_array($_GET['view'] ?? '', ['badges', 'table', 'list'], true)
                    ? $_GET['view']
                    : 'badges';

        $listRepo     = new ListRepository();
        $bookmarkRepo = new BookmarkRepository();

        View::render('bookmarks/index', [
            'lists'     => $listRepo->findAll(),
            'bookmarks' => $bookmarkRepo->findPublic($listId, $tag ?: null, $sort),
            'allTags'   => [],
            'listId'    => $listId,
            'tag'       => $tag,
            'sort'      => $sort,
            'view'      => $view,
            'csrf'      => '',
            'flash'     => null,
            'readOnly'  => true,
        ]);
    }

    public function index(): void
    {
        $userId = (int) Auth::id();
        $listId = isset($_GET['list']) && $_GET['list'] !== '' ? (int) $_GET['list'] : null;
        $tag    = $_GET['tag'] ?? '';
        $sort   = $_GET['sort'] ?? 'position';
        $view   = in_array($_GET['view'] ?? '', ['badges', 'table', 'list'], true)
                    ? $_GET['view']
                    : 'badges';

        $listRepo     = new ListRepository();
        $bookmarkRepo = new BookmarkRepository();

        View::render('bookmarks/index', [
            'lists'     => $listRepo->findAll(),
            'bookmarks' => $bookmarkRepo->findFiltered($userId, $listId, $tag ?: null, $sort),
            'allTags'   => $bookmarkRepo->getAllTags($userId),
            'listId'    => $listId,
            'tag'       => $tag,
            'sort'      => $sort,
            'view'      => $view,
            'csrf'      => Csrf::token(),
            'flash'     => Flash::get(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=bookmarks');
        }

        $url = trim($_POST['url'] ?? '');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Flash::set('danger', 'URL invalide.');
            Response::redirect('?action=bookmarks');
        }

        $listId = $this->resolveListId();

        $repo = new BookmarkRepository();
        $repo->create([
            'url'         => $url,
            'host'        => trim($_POST['host'] ?? (string) parse_url($url, PHP_URL_HOST)),
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'badge_style' => $_POST['badge_style'] ?? 'deepBlue',
            'badge_text'  => trim($_POST['badge_text'] ?? ''),
            'tags'        => $this->normalizeTags($_POST['tags'] ?? ''),
            'visibility'  => ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private',
            'list_id'     => $listId,
            'user_id'     => (int) Auth::id(),
            'position'    => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        Flash::set('success', 'Favori ajouté.');
        Response::redirect($this->buildRedirectUrl());
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=bookmarks');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $repo = new BookmarkRepository();
        $bm   = $repo->findById($id);

        if (!$bm || (int) $bm['user_id'] !== (int) Auth::id()) {
            Flash::set('danger', 'Favori introuvable.');
            Response::redirect('?action=bookmarks');
        }

        $listId = $this->resolveListId();
        $url    = trim($_POST['url'] ?? $bm['url']);

        $repo->update($id, [
            'url'         => $url,
            'host'        => trim($_POST['host'] ?? (string) parse_url($url, PHP_URL_HOST)),
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'badge_style' => $_POST['badge_style'] ?? 'deepBlue',
            'badge_text'  => trim($_POST['badge_text'] ?? ''),
            'tags'        => $this->normalizeTags($_POST['tags'] ?? ''),
            'visibility'  => ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private',
            'list_id'     => $listId,
        ]);

        Flash::set('success', 'Favori mis à jour.');
        Response::redirect($this->buildRedirectUrl());
    }

    public function delete(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=bookmarks');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $repo = new BookmarkRepository();
        $bm   = $repo->findById($id);

        if (!$bm || (int) $bm['user_id'] !== (int) Auth::id()) {
            Flash::set('danger', 'Favori introuvable.');
            Response::redirect('?action=bookmarks');
        }

        $repo->delete($id);

        Flash::set('success', 'Favori supprimé.');
        Response::redirect($this->buildRedirectUrl());
    }

    public function fetchMeta(): void
    {
        header('Content-Type: application/json');

        $url = trim($_GET['url'] ?? '');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['error' => 'URL invalide']);
            return;
        }

        echo json_encode(UrlMetaService::fetch($url));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveListId(): ?int
    {
        // "new_list" prend le dessus sur list_id
        $newList = trim($_POST['new_list'] ?? '');
        if ($newList !== '') {
            $listRepo = new ListRepository();
            $existing = $listRepo->findByName($newList);
            return $existing ? (int) $existing['id'] : $listRepo->create($newList);
        }

        $listId = $_POST['list_id'] ?? '';
        return $listId !== '' ? (int) $listId : null;
    }

    private function normalizeTags(string $raw): string
    {
        $tags = array_filter(
            array_map('trim', explode(',', $raw)),
            fn($t) => $t !== ''
        );
        return implode(',', array_values($tags));
    }

    private function buildRedirectUrl(): string
    {
        $params = ['action' => 'bookmarks'];

        if (!empty($_POST['_list_id'])) {
            $params['list'] = (int) $_POST['_list_id'];
        }
        if (!empty($_POST['_view'])) {
            $params['view'] = $_POST['_view'];
        }

        return '?' . http_build_query($params);
    }
}

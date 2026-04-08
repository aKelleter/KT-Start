<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;
use App\Repository\BookmarkRepository;
use App\Repository\FolderRepository;
use App\Repository\ListRepository;
use App\Repository\SettingsRepository;
use App\Service\UrlCheckService;
use App\Service\UrlMetaService;

final class BookmarkController
{
    private static function perPage(): int
    {
        // Priorité : DB → .env/.env.local → défaut 24
        $fromDb  = (new SettingsRepository())->get('bookmarks_per_page');
        $fromEnv = (string) ($_ENV['BOOKMARKS_PER_PAGE'] ?? '');
        $value   = $fromDb !== '' ? $fromDb : ($fromEnv !== '' ? $fromEnv : '24');
        return max(1, (int) $value);
    }

    public function home(): void
    {
        if (Auth::check()) {
            Response::redirect('?action=bookmarks');
        }

        $listRepo      = new ListRepository();
        $defaultListId = $listRepo->findDefault();

        $listRaw = isset($_GET['list']) && $_GET['list'] !== '' ? (int) $_GET['list'] : null;
        if ($listRaw !== null) {
            $listId = $listRaw === 0 ? null : $listRaw;
        } else {
            $listId  = $defaultListId;
            $listRaw = $defaultListId;
        }
        $tag    = $_GET['tag'] ?? '';
        $sort   = $_GET['sort'] ?? 'position';
        $search = trim($_GET['q'] ?? '');
        $view   = in_array($_GET['view'] ?? '', ['badges', 'table', 'list'], true)
                    ? $_GET['view'] : 'badges';

        $bookmarkRepo = new BookmarkRepository();
        $folderRepo   = new FolderRepository();
        $total        = $bookmarkRepo->countPublic($listId, $tag ?: null, $search ?: null);
        [$page, $totalPages, $offset] = $this->paginate($total);

        $folders = [];
        $foldersByParent = [];
        $bookmarksByFolder = [];
        if ($listId !== null) {
            $folders = $folderRepo->findAllByListId($listId);
            $foldersByParent = $folderRepo->groupByParent($folders);
            foreach ($bookmarkRepo->findPublicByList($listId) as $bookmark) {
                $folderKey = $bookmark['folder_id'] === null ? 0 : (int) $bookmark['folder_id'];
                $bookmarksByFolder[$folderKey][] = $bookmark;
            }
        }

        View::render('bookmarks/index', [
            'lists'      => $listRepo->findAll(),
            'bookmarks'  => $bookmarkRepo->findPublic($listId, $tag ?: null, $sort, $search ?: null, self::showAll() ? PHP_INT_MAX : self::perPage(), $offset),
            'allTags'    => [],
            'folders'    => $folders,
            'foldersByParent' => $foldersByParent,
            'bookmarksByFolder' => $bookmarksByFolder,
            'listId'     => $listId,
            'listRaw'    => $listRaw,
            'showAll'    => self::showAll(),
            'tag'        => $tag,
            'sort'       => $sort,
            'search'     => $search,
            'view'       => $view,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'csrf'       => '',
            'flash'      => null,
            'readOnly'   => true,
        ]);
    }

    public function index(): void
    {
        $userId     = (int) Auth::id();
        $listRepo      = new ListRepository();
        $defaultListId = $listRepo->findDefault();

        // list=0 → "Toutes" explicitement choisi (pas de filtre)
        // list absente → restaurer depuis la session, sinon liste par défaut
        $listRaw = isset($_GET['list']) && $_GET['list'] !== '' ? (int) $_GET['list'] : null;
        if ($listRaw !== null) {
            $_SESSION['ktstart_list'] = $listRaw; // mémoriser le choix
            $listId = $listRaw === 0 ? null : $listRaw;
        } elseif (array_key_exists('ktstart_list', $_SESSION)) {
            $listRaw = (int) $_SESSION['ktstart_list'];
            $listId  = $listRaw === 0 ? null : $listRaw;
        } else {
            $listId  = $defaultListId;
            $listRaw = $defaultListId; // pour construire les URLs de pagination
        }
        $tag    = $_GET['tag'] ?? '';
        $sort   = $_GET['sort'] ?? 'position';
        $search = trim($_GET['q'] ?? '');
        $view   = in_array($_GET['view'] ?? '', ['badges', 'table', 'list', 'explorer'], true)
                    ? $_GET['view'] : 'badges';

        if ($view === 'explorer') {
            $tag = '';
            $search = '';
            $sort = 'position';
        }

        $bookmarkRepo = new BookmarkRepository();
        $folderRepo   = new FolderRepository();
        $total        = $bookmarkRepo->countFiltered($userId, $listId, $tag ?: null, $search ?: null);
        [$page, $totalPages, $offset] = $this->paginate($total);

        $folders = [];
        $foldersByParent = [];
        $bookmarksByFolder = [];
        if ($listId !== null) {
            $folders = $folderRepo->findAllByUserInList($userId, $listId);
            $foldersByParent = $folderRepo->groupByParent($folders);
            foreach ($bookmarkRepo->findByUserAndListWithFolder($userId, $listId) as $bookmark) {
                $folderKey = $bookmark['folder_id'] === null ? 0 : (int) $bookmark['folder_id'];
                $bookmarksByFolder[$folderKey][] = $bookmark;
            }
        }

        View::render('bookmarks/index', [
            'lists'      => $listRepo->findAll(),
            'bookmarks'  => $bookmarkRepo->findFiltered($userId, $listId, $tag ?: null, $sort, $search ?: null, self::showAll() ? PHP_INT_MAX : self::perPage(), $offset),
            'allTags'    => $bookmarkRepo->getAllTags($userId),
            'folders'    => $folders,
            'foldersByParent' => $foldersByParent,
            'bookmarksByFolder' => $bookmarksByFolder,
            'listId'     => $listId,
            'listRaw'    => $listRaw,
            'showAll'    => self::showAll(),
            'tag'        => $tag,
            'sort'       => $sort,
            'search'     => $search,
            'view'       => $view,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'csrf'       => Csrf::token(),
            'flash'      => Flash::get(),
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
        $folderId = $this->resolveFolderId($listId, (int) Auth::id());
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
            'folder_id'   => $folderId,
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
        $folderId = $this->resolveFolderId($listId, (int) Auth::id());
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
            'folder_id'   => $folderId,
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

    public function reorder(): void
    {
        header('Content-Type: application/json');

        $body = (array) json_decode(file_get_contents('php://input'), true);

        if (!Csrf::validate($body['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide']);
            return;
        }

        $ids = $body['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'ids manquants']);
            return;
        }

        $repo = new BookmarkRepository();
        $repo->reorder((int) Auth::id(), $ids);

        echo json_encode(['ok' => true]);
    }

    public function checkDuplicate(): void
    {
        header('Content-Type: application/json');

        $url       = trim($_GET['url'] ?? '');
        $excludeId = isset($_GET['exclude_id']) && $_GET['exclude_id'] !== ''
            ? (int) $_GET['exclude_id'] : null;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['duplicate' => false]);
            return;
        }

        $bm = (new BookmarkRepository())->findByUrl((int) Auth::id(), $url, $excludeId);

        if (!$bm) {
            echo json_encode(['duplicate' => false]);
            return;
        }

        echo json_encode([
            'duplicate' => true,
            'title'     => $bm['title'] ?: $bm['host'],
            'list_name' => $bm['list_name'] ?? null,
        ]);
    }

    public function linksReport(): void
    {
        $userId = (int) Auth::id();
        $repo   = new BookmarkRepository();

        View::render('bookmarks/links', [
            'bookmarks' => $repo->findAllByUser($userId),
            'csrf'      => Csrf::token(),
            'flash'     => Flash::get(),
        ]);
    }

    public function checkSingleLink(): void
    {
        header('Content-Type: application/json');

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide']);
            return;
        }

        $id     = (int) ($_POST['id'] ?? 0);
        $userId = (int) Auth::id();
        $repo   = new BookmarkRepository();
        $bm     = $repo->findById($id);

        if (!$bm || (int) $bm['user_id'] !== $userId) {
            http_response_code(404);
            echo json_encode(['error' => 'Favori introuvable']);
            return;
        }

        $check = UrlCheckService::check($bm['url']);
        $now   = date('Y-m-d H:i:s');
        $repo->updateCheckStatus($id, $check['status'], $now);

        echo json_encode([
            'ok'         => true,
            'id'         => $id,
            'status'     => $check['status'],
            'http_code'  => $check['http_code'],
            'checked_at' => $now,
        ]);
    }

    public function resetLinkStatus(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=bookmark_links_report');
        }

        $count = (new BookmarkRepository())->resetCheckStatus((int) Auth::id());

        Flash::set('success', "Statut de vérification réinitialisé ($count favori(s)).");
        Response::redirect('?action=bookmark_links_report');
    }

    public function followRedirect(): void
    {
        header('Content-Type: application/json');

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide']);
            return;
        }

        $id     = (int) ($_POST['id'] ?? 0);
        $userId = (int) Auth::id();
        $repo   = new BookmarkRepository();
        $bm     = $repo->findById($id);

        if (!$bm || (int) $bm['user_id'] !== $userId) {
            http_response_code(404);
            echo json_encode(['error' => 'Favori introuvable']);
            return;
        }

        $finalUrl = UrlCheckService::getFinalUrl($bm['url']);

        if ($finalUrl === null) {
            echo json_encode(['ok' => false, 'error' => 'Impossible de suivre la redirection']);
            return;
        }

        $host = (string) parse_url($finalUrl, PHP_URL_HOST);
        $repo->updateUrl($id, $userId, $finalUrl, $host);

        echo json_encode([
            'ok'       => true,
            'id'       => $id,
            'new_url'  => $finalUrl,
            'new_host' => $host,
        ]);
    }

    public function deleteDeadLinks(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=bookmark_links_report');
        }

        $ids    = array_map('intval', (array) ($_POST['ids'] ?? []));
        $userId = (int) Auth::id();

        if (empty($ids)) {
            Flash::set('warning', 'Aucun favori sélectionné.');
            Response::redirect('?action=bookmark_links_report');
        }

        $count = (new BookmarkRepository())->deleteMultiple($userId, $ids);

        Flash::set('success', "$count favori(s) supprimé(s).");
        Response::redirect('?action=bookmark_links_report');
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

    public function folderStore(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=bookmarks');
        }

        $userId = (int) Auth::id();
        $listId = (int) ($_POST['list_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;

        if ($listId <= 0 || $name === '') {
            Flash::set('warning', 'Liste et nom de dossier requis.');
            Response::redirect($this->buildRedirectUrl());
        }

        $folders = new FolderRepository();
        if ($parentId !== null && !$folders->existsForUserInList($parentId, $userId, $listId)) {
            Flash::set('warning', 'Dossier parent invalide.');
            Response::redirect($this->buildRedirectUrl());
        }

        $folders->create($userId, $listId, $parentId, $name);

        Flash::set('success', 'Dossier créé.');
        Response::redirect($this->buildRedirectUrl());
    }

    public function folderRename(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=bookmarks');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($id <= 0 || $name === '') {
            Flash::set('warning', 'Dossier invalide.');
            Response::redirect($this->buildRedirectUrl());
        }

        $ok = (new FolderRepository())->rename($id, (int) Auth::id(), $name);

        Flash::set($ok ? 'success' : 'warning', $ok ? 'Dossier renommé.' : 'Dossier introuvable.');
        Response::redirect($this->buildRedirectUrl());
    }

    public function folderDelete(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=bookmarks');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('warning', 'Dossier invalide.');
            Response::redirect($this->buildRedirectUrl());
        }

        $ok = (new FolderRepository())->deleteAndLiftChildren($id, (int) Auth::id());

        Flash::set($ok ? 'success' : 'warning', $ok ? 'Dossier supprimé.' : 'Dossier introuvable.');
        Response::redirect($this->buildRedirectUrl());
    }

    public function explorerReorder(): void
    {
        header('Content-Type: application/json');

        $body = (array) json_decode(file_get_contents('php://input'), true);

        if (!Csrf::validate($body['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide']);
            return;
        }

        $userId   = (int) Auth::id();
        $listId   = (int) ($body['list_id'] ?? 0);
        $parentId = isset($body['parent_id']) && $body['parent_id'] !== null && $body['parent_id'] !== ''
            ? (int) $body['parent_id'] : null;
        $items = $body['items'] ?? [];

        if ($listId <= 0 || !is_array($items)) {
            http_response_code(400);
            echo json_encode(['error' => 'Payload invalide']);
            return;
        }

        $folderRepo = new FolderRepository();
        if ($parentId !== null && !$folderRepo->existsForUserInList($parentId, $userId, $listId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dossier parent invalide']);
            return;
        }

        $bookmarkRepo = new BookmarkRepository();

        foreach ($items as $pos => $item) {
            $type = (string) ($item['type'] ?? '');
            $id   = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            if ($type === 'folder') {
                if ($parentId !== null && $parentId === $id) {
                    continue;
                }
                if ($folderRepo->wouldCreateCycle($id, $parentId, $userId, $listId)) {
                    continue;
                }
                $folderRepo->setParentAndPosition($id, $userId, $listId, $parentId, (int) $pos);
                continue;
            }

            if ($type === 'bookmark') {
                $bookmarkRepo->setFolderAndPosition($id, $userId, $listId, $parentId, (int) $pos);
            }
        }

        echo json_encode(['ok' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function showAll(): bool
    {
        return ($_GET['perpage'] ?? '') === 'all';
    }

    /** @return array{int, int, int} [page, totalPages, offset] */
    private function paginate(int $total): array
    {
        if (self::showAll()) {
            return [1, 1, 0];
        }
        $totalPages = max(1, (int) ceil($total / self::perPage()));
        $page       = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
        $offset     = ($page - 1) * self::perPage();
        return [$page, $totalPages, $offset];
    }

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

    private function resolveFolderId(?int $listId, int $userId): ?int
    {
        if ($listId === null) {
            return null;
        }

        $folderId = $_POST['folder_id'] ?? '';
        if ($folderId === '') {
            return null;
        }

        $id = (int) $folderId;
        if ($id <= 0) {
            return null;
        }

        return (new FolderRepository())->existsForUserInList($id, $userId, $listId) ? $id : null;
    }

    private function normalizeTags(string $raw): string
    {
        $tags = array_filter(
            array_map('trim', explode(',', $raw)),
            fn($t) => $t !== ''
        );
        return implode(',', array_values($tags));
    }

    public function bookmarklet(): void
    {
        if (!Auth::check()) {
            View::renderRaw('bookmarks/bookmarklet', ['notLogged' => true, 'saved' => false]);
            return;
        }

        $url   = trim($_GET['url'] ?? '');
        $title = trim($_GET['title'] ?? '');
        $host  = (string) parse_url($url, PHP_URL_HOST);

        View::renderRaw('bookmarks/bookmarklet', [
            'url'         => $url,
            'title'       => $title,
            'host'        => $host,
            'lists'       => (new ListRepository())->findAll(),
            'badgeStyles' => \App\Config\BadgeStyles::all(),
            'csrf'        => Csrf::token(),
            'notLogged'   => false,
            'saved'       => false,
            'error'       => null,
        ]);
    }

    public function bookmarkletStore(): void
    {
        if (!Auth::check()) {
            View::renderRaw('bookmarks/bookmarklet', ['notLogged' => true, 'saved' => false]);
            return;
        }

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            View::renderRaw('bookmarks/bookmarklet', [
                'notLogged' => false, 'saved' => false, 'error' => 'Jeton CSRF invalide.',
                'url' => '', 'title' => '', 'host' => '',
                'lists' => (new ListRepository())->findAll(),
                'badgeStyles' => \App\Config\BadgeStyles::all(),
                'csrf' => Csrf::token(),
            ]);
            return;
        }

        $url = trim($_POST['url'] ?? '');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            View::renderRaw('bookmarks/bookmarklet', [
                'notLogged' => false, 'saved' => false, 'error' => 'URL invalide.',
                'url'   => $url,
                'title' => trim($_POST['title'] ?? ''),
                'host'  => (string) parse_url($url, PHP_URL_HOST),
                'lists' => (new ListRepository())->findAll(),
                'badgeStyles' => \App\Config\BadgeStyles::all(),
                'csrf'  => Csrf::token(),
            ]);
            return;
        }

        $listId = isset($_POST['list_id']) && $_POST['list_id'] !== '' ? (int) $_POST['list_id'] : null;

        (new BookmarkRepository())->create([
            'url'         => $url,
            'host'        => trim($_POST['host'] ?? (string) parse_url($url, PHP_URL_HOST)),
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'badge_style' => $_POST['badge_style'] ?? 'deepBlue',
            'badge_text'  => trim($_POST['badge_text'] ?? ''),
            'tags'        => $this->normalizeTags($_POST['tags'] ?? ''),
            'visibility'  => ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private',
            'list_id'     => $listId,
            'folder_id'   => null,
            'user_id'     => (int) Auth::id(),
            'position'    => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        View::renderRaw('bookmarks/bookmarklet', [
            'notLogged'  => false,
            'saved'      => true,
            'savedTitle' => trim($_POST['title'] ?? $url),
        ]);
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
        if (!empty($_POST['_tag'])) {
            $params['tag'] = $_POST['_tag'];
        }
        if (!empty($_POST['_sort']) && $_POST['_sort'] !== 'position') {
            $params['sort'] = $_POST['_sort'];
        }
        if (!empty($_POST['_search'])) {
            $params['q'] = $_POST['_search'];
        }

        return '?' . http_build_query($params);
    }
}

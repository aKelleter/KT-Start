<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class StatsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** Chiffres globaux. */
    public function overview(): array
    {
        $total = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM bookmarks')
            ->fetchColumn();

        $public = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM bookmarks WHERE visibility = 'public'")
            ->fetchColumn();

        // last_check_status peut être absent si la migration n'a pas encore été lancée
        try {
            $checked = (int) $this->pdo
                ->query('SELECT COUNT(*) FROM bookmarks WHERE last_check_status IS NOT NULL')
                ->fetchColumn();
        } catch (\PDOException) {
            $checked = 0;
        }

        return [
            'total'    => $total,
            'public'   => $public,
            'private'  => $total - $public,
            'checked'  => $checked,
            'unchecked'=> $total - $checked,
        ];
    }

    /** Nombre de favoris par utilisateur. */
    public function perUser(): array
    {
        $rows = $this->pdo->query(
            'SELECT u.email, COUNT(b.id) AS cnt
             FROM users u
             LEFT JOIN bookmarks b ON b.user_id = u.id
             GROUP BY u.id, u.email
             ORDER BY cnt DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    /** Nombre de favoris par liste (y compris sans liste). */
    public function perList(): array
    {
        $rows = $this->pdo->query(
            "SELECT COALESCE(l.name, '— Sans liste') AS name,
                    COUNT(b.id) AS cnt
             FROM bookmarks b
             LEFT JOIN lists l ON l.id = b.list_id
             GROUP BY b.list_id
             ORDER BY cnt DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    /** Répartition par statut de vérification de lien.
     *  Retourne tous les statuts à 0 si la colonne n'existe pas encore. */
    public function perLinkStatus(): array
    {
        $order = ['ok', 'redirect', 'error', 'timeout', 'unchecked'];

        try {
            $rows = $this->pdo->query(
                "SELECT COALESCE(last_check_status, 'unchecked') AS status,
                        COUNT(*) AS cnt
                 FROM bookmarks
                 GROUP BY last_check_status
                 ORDER BY cnt DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $map = array_column($rows, 'cnt', 'status');
        } catch (\PDOException) {
            $map = [];
        }

        $result = [];
        foreach ($order as $s) {
            $result[] = ['status' => $s, 'cnt' => (int) ($map[$s] ?? 0)];
        }
        return $result;
    }

    /** Top N tags (tous utilisateurs). */
    public function topTags(int $limit = 15): array
    {
        $freq = [];
        $stmt = $this->pdo->query('SELECT tags FROM bookmarks WHERE tags IS NOT NULL AND tags != \'\'');
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $tags) {
            foreach (array_map('trim', explode(',', $tags)) as $tag) {
                if ($tag !== '') {
                    $freq[$tag] = ($freq[$tag] ?? 0) + 1;
                }
            }
        }
        arsort($freq);

        $result = [];
        $i = 0;
        foreach ($freq as $tag => $cnt) {
            if ($i++ >= $limit) break;
            $result[] = ['tag' => $tag, 'cnt' => $cnt];
        }
        return $result;
    }

    /** Favoris ajoutés par mois sur les 12 derniers mois. */
    public function perMonth(): array
    {
        // Génère les 12 derniers mois (YYYY-MM) dans l'ordre chronologique
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime("-{$i} months"));
        }

        $rows = $this->pdo->query(
            "SELECT strftime('%Y-%m', created_at) AS month, COUNT(*) AS cnt
             FROM bookmarks
             WHERE created_at >= date('now', '-12 months')
             GROUP BY month
             ORDER BY month ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $map    = array_column($rows, 'cnt', 'month');
        $result = [];
        foreach ($months as $m) {
            $result[] = ['month' => $m, 'cnt' => (int) ($map[$m] ?? 0)];
        }
        return $result;
    }

    /** Répartition par style de badge. */
    public function perBadgeStyle(): array
    {
        $rows = $this->pdo->query(
            'SELECT badge_style, COUNT(*) AS cnt
             FROM bookmarks
             GROUP BY badge_style
             ORDER BY cnt DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }
}

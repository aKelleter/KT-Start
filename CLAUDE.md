# KT-Start — Contexte projet pour Claude Code

## Présentation
Gestionnaire de favoris web auto-hébergé. Refonte complète de l'ancienne version (~10 ans, fichiers `.ini`) avec la même stack que KT-Drop.

## Stack technique
- PHP 8.3+ (`declare(strict_types=1)` partout)
- SQLite 3 via PDO
- Bootstrap 5.3 + Bootstrap Icons 1.11
- `vlucas/phpdotenv` ^5.6
- SortableJS (CDN) pour le drag & drop
- Architecture MVC, front controller unique (`public/index.php`), router maison
- Aucun framework PHP externe

## Architecture
```
src/
├── Config/
│   ├── BadgeStyles.php     # 12 styles de badge avec gradient() — deepBlue, turquoise, etc.
│   └── Config.php          # Accès à $_ENV
├── Controller/
│   ├── AdminController.php # index, userStore/Update/Delete, listStore/Rename/Delete/SetDefault, settingUpdate, runMigration
│   ├── AuthController.php  # login, logout → redirige vers ?action=home
│   └── BookmarkController.php  # home(), index(), store(), update(), delete(), reorder(), fetchMeta()
├── Core/
│   ├── Auth.php            # Session : ktstart_session (≠ ktdrop_session), isAdmin()
│   ├── Csrf.php
│   ├── Database.php        # Singleton PDO SQLite
│   ├── Flash.php
│   ├── Response.php
│   ├── Router.php          # Routes par ?action=xxx, non-auth → ?action=home
│   └── View.php            # render(), e(), asset() → 'public/assets/...'
├── Repository/
│   ├── BookmarkRepository.php  # findPublic(), findFiltered(), CRUD, reorder(), getAllTags()
│   ├── ListRepository.php      # findAll(), findByName(), create(), rename(), findAllWithCount(), findDefault(), setDefault(), clearDefault()
│   ├── SettingsRepository.php  # get(), set(), all() — table settings clé/valeur
│   └── UserRepository.php      # CRUD complet, emailExists()
└── Service/
    ├── MigrationService.php    # Migrations idempotentes : PRAGMA + ALTER TABLE ADD COLUMN
    └── UrlMetaService.php      # curl + DOMDocument → title/host/description
```

## Schéma SQLite
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    created_at TEXT NOT NULL
);

CREATE TABLE lists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    is_default INTEGER NOT NULL DEFAULT 0,  -- 1 seule liste par défaut max
    created_at TEXT NOT NULL
);

CREATE TABLE bookmarks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL,
    host TEXT,
    title TEXT,
    description TEXT,
    badge_style TEXT NOT NULL DEFAULT 'deepBlue',
    badge_text TEXT NOT NULL DEFAULT '',
    tags TEXT,           -- virgule-séparé ex: "php,dev"
    visibility TEXT NOT NULL DEFAULT 'private',  -- 'public' | 'private'
    list_id INTEGER REFERENCES lists(id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    position INTEGER DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
    -- ex: bookmarks_per_page = '24'
);
```

## Routing
Toutes les routes passent par `?action=xxx` :
- `home` (public) → favoris publics, non connecté
- `login` / `login_submit` (public)
- `logout`
- `bookmarks` → liste complète (auth requise)
- `bookmark_store` / `bookmark_update` / `bookmark_delete` (POST, auth)
- `bookmark_reorder` (POST JSON, auth) → drag & drop SortableJS
- `bookmark_fetch_meta` (GET, auth) → JSON {title, host, description}
- `admin` (GET, admin) → page administration
- `admin_user_store` / `admin_user_update` / `admin_user_delete` (POST, admin)
- `admin_list_store` / `admin_list_rename` / `admin_list_delete` (POST, admin)
- `admin_list_set_default` (POST, admin) → toggle liste par défaut
- `admin_setting_update` (POST, admin) → mise à jour table settings
- `admin_run_migration` (POST, admin) → MigrationService, résultat en session flash

## Environnement
- `.env` — config de base, commité
- `.env.local` — surcharges locales (non commité)
- `.env.local.example` — template pour `.env.local`
- Session PHP : `ktstart_session` (isolée de KT-Drop)
- `BOOKMARKS_PER_PAGE` — nombre de favoris par page, priorité : DB (`settings`) → `.env` → défaut 24

## Installation sur une nouvelle machine
```bash
composer install
cp .env.local.example .env.local
# Éditer .env.local selon l'environnement
php scripts/init-db.php    # crée les tables + user admin@example.com / changeme
```

## Apache (local)
Accès via subdirectoire : `http://localhost:8080/KT-Start/`
- `.htaccess` racine → redirige vers `public/index.php`
- `.htaccess` dans `public/` → rewrite vers `index.php`
- DocumentRoot Apache : `/Users/.../Sites` (commun à tous les projets)
- Pas de VirtualHost dédié

## Templates
- `templates/layout.php` — navbar fixe glassmorphism, footer fixe, back-to-top, lien Admin (admin only, d-none d-md-inline-flex)
- `templates/auth/login.php`
- `templates/bookmarks/index.php` — 3 vues (badges/table/liste), modal add/edit `.ks-modal`, drag & drop, contrôle taille badges, recherche, pagination + bouton ∞ "tout afficher", `$readOnly` pour vue publique
- `templates/admin/index.php` — gestion utilisateurs (table + modal), listes (table + modal + bouton ⭐ défaut), paramètres (bookmarks_per_page), maintenance (migration + log)

## Conventions CSS
Fichier : `public/assets/css/app.css`
- Variables : `--app-blue: #0288D1`, `--app-blue-soft`, `--app-blue-hover`
- Boutons : `--bs-btn-border-radius: 10px` global, overrides Bootstrap variables sur `.btn-primary`
- Classes badge : `.ks-badge`, `.ks-badge-thumb` (3 couches Liquid Glass : `::before` reflet, `::after` overlay, `inset box-shadow`), `.ks-badge-footer`
- Taille badge : propriété CSS custom `--ks-badge-width` sur `.ks-badges-grid`, 6 paliers XS (80px) → XXL (240px), défaut L (160px), persisté en `localStorage`
- Classes liste compacte : `.ks-compact-item`
- Dropdown liste : `.ks-list-dropdown-items` (max-height + scroll) — remplace les anciens pills `.ks-list-tab`
- Modaux unifiés : `.ks-modal` (style Apple/Tesla, fond flouté, coins arrondis, séparateurs subtils)
- Drag & drop : `.ks-drag-handle` (visible uniquement en sort=position)
- Admin : `.ks-admin-card`, `.ks-admin-icon`, `.ks-migration-log`
- Pagination : `.ks-pagination`
- Recherche : `.ks-search-input`
- Même structure visuelle que KT-Drop (dominante bleue au lieu d'orange)

## Points techniques importants
- **LIMIT/OFFSET** : interpolés directement dans la requête SQL (pas de `bindValue`) car SQLite rejette les strings pour LIMIT — les valeurs viennent du calcul interne, pas de l'utilisateur
- **getAllTags()** : `$tags = array_keys($tags); sort($tags);` sur deux lignes — `sort()` attend une référence, impossible en expression compacte
- **Migration log** : `$_SESSION['_migration_log']` utilisé pour passer le résultat de migration entre redirect et affichage
- **Drag & drop** : SortableJS uniquement actif en mode `sort=position`, handles `.ks-drag-handle` masqués sinon
- **Liquid Glass** : `.ks-badge-thumb::before` (radial-gradient spéculaire) + `::after` (linear-gradient directionnel) + `inset box-shadow` — ne pas utiliser `overflow: hidden` sur `.modal-content` (blur des textes)
- **Liste par défaut** : `is_default` sur `lists`, une seule liste à 1 max — `findDefault()` retourne null si colonne absente (migration pas encore lancée) via try/catch
- **Sentinel list=0** : dans l'URL, `list=0` signifie "Toutes" explicitement choisi (pas de filtre). Absence du paramètre `list` → appliquer la liste par défaut. `$listRaw` est transmis au template pour construire les URLs de pagination/recherche sans perdre ce choix
- **Tout afficher** : `perpage=all` dans l'URL désactive la pagination (PHP_INT_MAX en LIMIT). `$showAll` transmis au template — préservé dans le formulaire de recherche et les liens de pagination
- **Compteur** : affiche `X / Y favoris` quand X (page courante) < Y (total filtré), sinon juste `Y favoris`
- **Priorité bookmarks_per_page** : DB (`settings.bookmarks_per_page`) → `$_ENV['BOOKMARKS_PER_PAGE']` → 24
- **SettingsRepository** : utilise `INSERT ... ON CONFLICT(key) DO UPDATE` (upsert SQLite) — `get()` retourne `''` si table absente (try/catch)

## Ce qui reste à faire (non implémenté)
- Partage public par lien direct avec token (comme KT-Drop)
- Page de statistiques (répartition par liste, tag, visibilité)
- Import/export CSV ou JSON des favoris
- Vérification automatique des favoris inaccessibles (lien mort)
- Rôle `editor` (multi-utilisateurs sans accès admin)

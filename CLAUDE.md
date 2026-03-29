# KT-Start — Contexte projet pour Claude Code

## Présentation
Gestionnaire de favoris web auto-hébergé. Refonte complète de l'ancienne version (~10 ans, fichiers `.ini`) avec la même stack que KT-Drop.

## Stack technique
- PHP 8.3+ (`declare(strict_types=1)` partout)
- SQLite 3 via PDO
- Bootstrap 5.3 + Bootstrap Icons 1.11
- `vlucas/phpdotenv` ^5.6
- Architecture MVC, front controller unique (`public/index.php`), router maison
- Aucun framework PHP externe

## Architecture
```
src/
├── Config/
│   ├── BadgeStyles.php     # 12 couleurs de badge (deepBlue, turquoise, etc.)
│   └── Config.php          # Accès à $_ENV
├── Controller/
│   ├── AuthController.php
│   └── BookmarkController.php  # home(), index(), store(), update(), delete(), fetchMeta()
├── Core/
│   ├── Auth.php            # Session : ktstart_session (≠ ktdrop_session)
│   ├── Csrf.php
│   ├── Database.php        # Singleton PDO SQLite
│   ├── Flash.php
│   ├── Response.php
│   ├── Router.php          # Routes par ?action=xxx
│   └── View.php            # render(), e(), asset() → 'public/assets/...'
├── Repository/
│   ├── BookmarkRepository.php  # findPublic(), findFiltered(), CRUD
│   ├── ListRepository.php
│   └── UserRepository.php
└── Service/
    └── UrlMetaService.php  # curl + DOMDocument → title/host/description
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
```

## Routing
Toutes les routes passent par `?action=xxx` :
- `home` (public) → favoris publics, non connecté
- `login` / `login_submit` (public)
- `logout`
- `bookmarks` → liste complète (auth requise)
- `bookmark_store` / `bookmark_update` / `bookmark_delete` (POST, auth)
- `bookmark_fetch_meta` (GET, auth) → JSON {title, host, description}

## Environnement
- `.env` — config de base, commité
- `.env.local` — surcharges locales (non commité)
- `.env.local.example` — template pour `.env.local`
- Session PHP : `ktstart_session` (isolée de KT-Drop)

## Installation sur une nouvelle machine
```bash
composer install
cp .env.local.example .env.local
# Éditer .env.local selon l'environnement
php scripts/init-db.py    # crée les tables + user admin@example.com / changeme
```

## Apache (local)
Accès via subdirectoire : `http://localhost:8080/KT-Start/`
- `.htaccess` racine → redirige vers `public/index.php`
- `.htaccess` dans `public/` → rewrite vers `index.php`
- DocumentRoot Apache : `/Users/.../Sites` (commun à tous les projets)
- Pas de VirtualHost dédié

## Templates
- `templates/layout.php` — navbar fixe glassmorphism, footer fixe, back-to-top
- `templates/auth/login.php`
- `templates/bookmarks/index.php` — 3 vues (badges/table/liste), modal add/edit, `$readOnly` pour la vue publique

## Conventions CSS
Fichier : `public/assets/css/app.css`
- Variables : `--app-blue: #2563eb`, `--app-blue-soft`, `--app-blue-hover`
- Classes badge : `.ks-badge`, `.ks-badge-thumb`, `.ks-badge-footer`
- Classes liste compacte : `.ks-compact-item`
- Classes onglets : `.ks-list-tab`, `.ks-list-tabs`
- Même structure visuelle que KT-Drop (dominante bleue au lieu d'orange)

## Ce qui reste à faire (non implémenté)
- Migration des données de l'ancienne version `.ini`
- Page d'administration (gestion users, listes)
- Tri par drag & drop (champ `position`)
- Partage public par lien direct
</thinking>

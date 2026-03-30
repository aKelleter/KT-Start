# Gestionnaire de favoris – KT-Start

Application web de gestion de favoris auto-hébergée, développée en PHP natif. Elle permet aux utilisateurs authentifiés d'organiser, rechercher et partager leurs liens favoris, avec une interface d'administration complète.

---

## Stack technique

| Composant | Choix |
|---|---|
| Langage | PHP 8.3+ (strict types) |
| Base de données | SQLite 3 (PDO) |
| Frontend | Bootstrap 5.3 + Bootstrap Icons 1.11 |
| Dépendances | `vlucas/phpdotenv` uniquement |
| Architecture | MVC, front controller unique, router maison |

---

## Fonctionnalités

### Gestion des favoris
- Ajout d'un favori avec récupération automatique du titre, de l'hôte et de la description (via `UrlMetaService`)
- Modification et suppression, toutes les actions protégées par CSRF
- Trois vues disponibles : **Badges**, **Tableau**, **Liste compacte**
- Contrôle de la taille d'affichage des badges : 6 paliers XS → XXL, mémorisés dans `localStorage`
- **Tri par glisser-déposer** (SortableJS) en mode vue Badges, tri par position
- Tri par colonne : position, titre, hôte, date (croissant/décroissant)
- **Recherche full-text** sur titre, hôte, URL, description, tags et texte de badge
- **Pagination configurable** via `BOOKMARKS_PER_PAGE` dans `.env`
- **Bouton "remonter en haut"** flottant (apparaît après défilement)

### Listes
- Organisation des favoris en listes personnalisées
- Création d'une nouvelle liste directement depuis le formulaire d'ajout
- **Liste par défaut** configurable — affichée automatiquement à l'ouverture (connecté ou non)
- Filtrage par liste via un **dropdown avec recherche live** dans la barre d'outils
- Sélection de liste dans les modaux ajout/édition via un dropdown searchable

### Tags
- Tags multiples séparés par virgule sur chaque favori
- Filtrage par tag via un clic sur n'importe quelle étiquette
- Autocomplétion des tags existants dans le formulaire

### Badges (style visuel)
- 12 styles de couleur : `deepBlue`, `deepPurple`, `lightViolet`, `lightBlue`, `turquoise`, `lightGreen`, `lightOrange`, `deepOrange`, `red`, `pink`, `brown`, `grey`
- Dégradé de couleur et **effet Liquid Glass** (inspiré iOS) : reflet spéculaire, overlay directionnel, inset shadow
- Texte de badge personnalisable (affiché sur la carte)
- Effet hover avec intensification du glass et translation verticale

### Visibilité
- Chaque favori peut être `public` ou `private`
- Page d'accueil publique affichant les favoris marqués `public` (sans connexion)
- Vue filtrée complète accessible après connexion

### Administration
- **Gestion des utilisateurs** : création, édition, suppression — protection contre l'auto-suppression et la suppression du dernier admin
- **Gestion des listes** : création, renommage, suppression, **définition de la liste par défaut** (⭐)
- Tableau des listes avec **recherche live** et scroll interne
- **Maintenance** : migration de base de données idempotente depuis l'interface (sans accès SSH), journal de résultat affiché
- Toutes les actions admin protégées CSRF et réservées au rôle `admin`

### Migration depuis l'ancienne version
- Script `scripts/migrate_ini.php` — importe les favoris depuis les fichiers `.ini` de l'ancienne version
- Gestion de l'encodage (UTF-8/Latin-1), décodage des entités HTML
- Mapping automatique des anciens styles de badge vers les nouveaux
- Idempotent : peut être relancé sans créer de doublons
- Script `scripts/reset_bookmarks.php` — vide les tables `bookmarks` et `lists` (réinitialise les auto-increments)

---

## Rôles

| Rôle | Accès favoris | Accès administration |
|---|---|---|
| `admin` | Complet | Oui — tous les modules |

---

## Prérequis

- PHP ≥ 8.3 avec extensions `pdo_sqlite`, `mbstring`, `curl`
- Composer
- Apache avec `mod_rewrite` activé

---

## Installation

```bash
# 1. Installer les dépendances
composer install

# 2. Configurer l'environnement
cp .env.local.example .env.local

# 3. Initialiser la base de données
php scripts/init-db.php
```

Pointer le document root d'Apache sur le dossier `public/`.

> **Important :** Modifier immédiatement les identifiants admin par défaut après l'installation (voir ci-dessous).

### Migration depuis une ancienne version (fichiers `.ini`)

```bash
php scripts/migrate_ini.php /chemin/vers/dossier/datas
```

---

## Configuration

### Fonctionnement des fichiers d'environnement

La configuration repose sur deux fichiers complémentaires :

| Fichier | Versionné | Rôle |
|---|---|---|
| `.env` | Oui | Valeurs par défaut et variables mises à jour à chaque version (`APP_VERSION`, etc.) |
| `.env.local` | **Non** | Surcharges propres à l'environnement (prod, staging…) — créé une seule fois sur le serveur, jamais écrasé |

Au démarrage, `.env` est chargé en premier, puis `.env.local` (s'il existe) **écrase** les variables qu'il redéfinit. Cela permet de mettre à jour `.env` librement sans jamais toucher aux paramètres de production.

### Mise en place sur le serveur de production

```bash
# À faire une seule fois, juste après le premier déploiement
cp .env.local.example .env.local
# Éditer .env.local avec les valeurs réelles
```

Contenu type de `.env.local` en production :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com/

BOOKMARKS_PER_PAGE=24
```

> `.env.local` est ignoré par git (`.gitignore`). Il ne sera jamais écrasé lors des mises à jour.

### Variables disponibles dans `.env`

```ini
# Application
APP_NAME="KT-Start"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com/

# Base de données
DB_DATABASE=database/app.sqlite

# Pagination
BOOKMARKS_PER_PAGE=24
```

---

## Compte admin par défaut

```
Email        : admin@example.com
Mot de passe : changeme
```

> **À modifier impérativement avant toute mise en production.**
> Changer le mot de passe via Administration → Utilisateurs.

---

## Sécurité

| Mécanisme | Détail |
|---|---|
| CSRF | Token `random_bytes(32)`, comparaison en temps constant (`hash_equals`) |
| Régénération de session | `session_regenerate_id()` à chaque connexion |
| Mots de passe | `password_hash()` / `password_verify()` (algorithme agile) |
| Contrôle de rôle | `Auth::isAdmin()` vérifié avant chaque action d'administration |
| Requêtes SQL | Requêtes préparées systématiques (PDO bound params) |
| XSS | `htmlspecialchars()` sur toutes les sorties via `View::e()` |
| Isolation des sessions | Clé `ktstart_session` (isolée des autres applications KT) |

---

## Structure du projet

```text
KT-Start/
├── database/
│   └── app.sqlite                      # Base de données (ignorée par git)
├── public/
│   ├── index.php                       # Front controller & routing
│   └── assets/
│       └── css/app.css
├── scripts/
│   ├── init-db.php                     # Schéma complet, migrations idempotentes, compte admin
│   ├── migrate_ini.php                 # Import des favoris depuis les fichiers .ini
│   └── reset_bookmarks.php             # Vide les tables bookmarks et lists
├── src/
│   ├── Config/
│   │   ├── BadgeStyles.php             # 12 styles de badge (couleurs + dégradés)
│   │   └── Config.php                  # Accès aux variables d'environnement
│   ├── Controller/
│   │   ├── AdminController.php         # Utilisateurs, listes (+ liste par défaut), maintenance
│   │   ├── AuthController.php          # Connexion / déconnexion
│   │   └── BookmarkController.php      # home, index, store, update, delete, reorder, fetchMeta
│   ├── Core/
│   │   ├── Auth.php                    # Session ktstart_session, rôles
│   │   ├── Csrf.php
│   │   ├── Database.php                # Singleton PDO SQLite
│   │   ├── Flash.php
│   │   ├── Response.php
│   │   ├── Router.php                  # Routes par ?action=xxx
│   │   └── View.php                    # render(), e(), asset()
│   ├── Repository/
│   │   ├── BookmarkRepository.php      # findPublic, findFiltered, CRUD, reorder, getAllTags
│   │   ├── ListRepository.php          # CRUD + findDefault, setDefault, clearDefault
│   │   └── UserRepository.php          # CRUD utilisateurs
│   └── Service/
│       ├── MigrationService.php        # Migrations idempotentes (PRAGMA + ALTER TABLE)
│       └── UrlMetaService.php          # curl + DOMDocument → title/host/description
├── templates/
│   ├── layout.php                      # Navbar glassmorphism, footer, back-to-top
│   ├── admin/
│   │   └── index.php                   # Gestion utilisateurs, listes, maintenance
│   ├── auth/
│   │   └── login.php
│   └── bookmarks/
│       └── index.php                   # 3 vues (badges/table/liste), drag & drop, pagination
├── .env                                # Variables par défaut (versionné)
├── .env.local                          # Surcharges locales/prod (ignoré par git)
├── .env.local.example                  # Modèle pour créer .env.local (versionné)
├── composer.json
└── .htaccess
```

---

## Schéma de base de données

| Table | Rôle |
|---|---|
| `users` | Comptes utilisateurs (email, hash, rôle) |
| `lists` | Listes pour organiser les favoris (`is_default` pour la liste affichée par défaut) |
| `bookmarks` | Favoris (URL, titre, tags, badge, visibilité, position) |

---

## Routes

Toutes les routes passent par `?action=xxx` :

| Action | Méthode | Accès | Description |
|---|---|---|---|
| `home` | GET | Public | Favoris publics (liste par défaut si définie) |
| `login` | GET | Public | Formulaire de connexion |
| `login_submit` | POST | Public | Traitement de la connexion |
| `logout` | POST | Auth | Déconnexion |
| `bookmarks` | GET | Auth | Liste complète des favoris |
| `bookmark_store` | POST | Auth | Ajouter un favori |
| `bookmark_update` | POST | Auth | Modifier un favori |
| `bookmark_delete` | POST | Auth | Supprimer un favori |
| `bookmark_reorder` | POST | Auth | Réordonner (drag & drop, JSON) |
| `bookmark_fetch_meta` | GET | Auth | Récupérer les métadonnées d'une URL (JSON) |
| `admin` | GET | Admin | Dashboard administration |
| `admin_user_store` | POST | Admin | Créer un utilisateur |
| `admin_user_update` | POST | Admin | Modifier un utilisateur |
| `admin_user_delete` | POST | Admin | Supprimer un utilisateur |
| `admin_list_store` | POST | Admin | Créer une liste |
| `admin_list_rename` | POST | Admin | Renommer une liste |
| `admin_list_set_default` | POST | Admin | Définir/retirer la liste par défaut |
| `admin_list_delete` | POST | Admin | Supprimer une liste |
| `admin_run_migration` | POST | Admin | Lancer la migration de BDD |

---

## Pistes d'évolution

- Partage public par lien direct avec token
- Page de statistiques (répartition par liste, tag, visibilité)
- Import/export CSV ou JSON des favoris
- Notifications de favoris expirés ou inaccessibles
- Rôle `editor` (multi-utilisateurs sans accès admin)

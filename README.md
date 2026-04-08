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
- **Bookmarklet** — bouton à glisser dans la barre du navigateur pour ajouter la page courante en un clic, sans quitter l'onglet (popup autonome 480×580)
- **Détection de doublons d'URL** en temps réel lors de l'ajout ou de la modification
- Modification et suppression, toutes les actions protégées par CSRF
- **Suppression rapide** directement sur chaque favori (icône poubelle sur les 3 vues) avec confirmation en modal Bootstrap
- Quatre vues disponibles : **Badges**, **Tableau**, **Liste compacte**, **Explorateur**
- Contrôle de la taille d'affichage des badges : 6 paliers XS → XXL, mémorisés dans `localStorage`
- **Tri par glisser-déposer** (SortableJS) en mode vue Badges, tri par position
- Tri par colonne : position, titre, hôte, **texte de badge**, date (croissant/décroissant)
- **Recherche full-text** sur titre, hôte, URL, description, tags et texte de badge
- **Pagination configurable** — nombre de favoris par page éditable depuis l'administration
- **Bouton "tout afficher"** (∞) pour désactiver la pagination à la volée
- Compteur `X / Y favoris` — affiche le nombre sur la page et le total filtré
- **Bouton "remonter en haut"** flottant (apparaît après défilement)

### Listes
- Organisation des favoris en listes personnalisées
- Création d'une nouvelle liste directement depuis le formulaire d'ajout
- **Liste par défaut** configurable — affichée automatiquement à l'ouverture (connecté ou non)
- Filtrage par liste via un **dropdown avec recherche live** dans la barre d'outils
- Sélection de liste dans les modaux ajout/édition via un dropdown searchable
- "— Toutes" accessible explicitement, préservé lors de la pagination et des recherches

### Dossiers
- Création de dossiers dans une liste pour regrouper les favoris
- Hiérarchie parent/enfant (dossiers imbriqués) sur N niveaux
- **Vue Explorateur** — navigation par dossiers avec glisser-déposer pour réorganiser favoris et dossiers
- **Vues Badges, Tableau, Liste** — hiérarchie respectée avec indentation visuelle (bordure gauche bleue)
- Création de sous-dossiers directement depuis la vue Badges (bouton `📁+` dans le footer de chaque badge dossier)
- Suppression d'un dossier non destructive : les sous-dossiers et favoris sont remontés au niveau parent
- Bouton "+Dossier" dans la barre d'outils (toutes les vues)

### Tags
- Tags multiples séparés par virgule sur chaque favori
- Filtrage par tag via un clic sur n'importe quelle étiquette
- Autocomplétion des tags existants dans le formulaire
- **Nuage de tags** — collapse dans la barre d'outils, trié par fréquence, taille proportionnelle à l'usage

### Badges (style visuel)
- 12 styles de couleur : `deepBlue`, `deepPurple`, `lightViolet`, `lightBlue`, `turquoise`, `lightGreen`, `lightOrange`, `deepOrange`, `red`, `pink`, `brown`, `grey`
- Dégradé de couleur et **effet Liquid Glass** : reflet spéculaire, overlay directionnel, inset shadow
- **Ombre colorée au survol** — la diffusion s'adapte automatiquement à la couleur de chaque badge via `color-mix()`
- Texte de badge personnalisable (affiché sur la carte)

### Mode sombre
- Bouton 🌙 / ☀️ dans la navbar pour basculer entre mode clair et mode sombre
- Préférence persistée dans `localStorage` — appliquée avant le rendu pour éviter tout flash
- Palette bleu-grisée soignée, fond non-uniforme supprimé en sombre (évite les artefacts au scroll)
- Compatible Bootstrap dark mode (`data-bs-theme`)

### Visibilité
- Chaque favori peut être `public` ou `private`
- Page d'accueil publique affichant les favoris marqués `public` (sans connexion)
- Vue filtrée complète accessible après connexion

### Administration
L'interface d'administration est organisée en 9 sous-pages indépendantes accessibles depuis un dashboard central.

- **Utilisateurs** : création, édition, suppression — confirmation du mot de passe dans la modale, protection contre l'auto-suppression et la suppression du dernier admin
- **Listes** : création, renommage, suppression, **liste par défaut** (⭐), recherche live + scroll interne
- **Dossiers** : gestion centralisée par liste — arbre hiérarchique avec **drag & drop** (SortableJS) pour réorganiser et nicher les dossiers, création de sous-dossiers, renommage, suppression, badge email du propriétaire, sauvegarde automatique (debounce 600ms), protection anti-cycle
- **Paramètres** : nombre de favoris par page (priorité DB → `.env` → 24) + proxy HTTP pour la vérification des liens avec case à cocher d'activation (priorité DB → `.env` → vide) + **code bookmarklet** généré avec l'URL de l'instance (à glisser dans la barre du navigateur)
- **Tags** : vue de tous les tags (tous utilisateurs), triés par fréquence, renommage, suppression, **nettoyage en un clic des tags utilisés une seule fois**
- **Statistiques** : cartes résumé (total, publics, privés, utilisateurs, listes, tags), graphique barres mensuel sur 12 mois (CSS pur), répartitions par liste / statut des liens / top 15 tags / utilisateur / style de badge
- **Vérification des liens** : raccourci vers le rapport d'accessibilité — badge rouge sur la carte indiquant le nombre de liens morts (tous utilisateurs)
- **Maintenance** : migration de base de données idempotente depuis l'interface, journal de résultat affiché
- **Sauvegarde** : export/import JSON avec trois scénarios (voir ci-dessous)
- Toutes les actions admin protégées CSRF et réservées au rôle `admin`

### Sauvegarde et restauration

| Action | Fichier produit | Contenu |
|---|---|---|
| **Backup complet** | `ktstart-backup-YYYYMMDD-HHmmss.json` | users + settings + lists (avec liste par défaut) + bookmarks (tous utilisateurs) |
| **Export favoris** | `ktstart-bookmarks-YYYYMMDD-HHmmss.json` | lists + bookmarks (utilisateur courant) — portable entre instances |

**Import** (détection automatique du format v1/v2) :

- **Export favoris (v1)** : supprime les bookmarks et listes existants, réinitialise les séquences, réinsère les listes puis les bookmarks
- **Backup complet (v2) — import normal** : crée les users/listes manquants (skip si existant), upsert les settings, ajoute les bookmarks
- **Backup complet (v2) — Restauration complète** : purge toutes les tables, réinitialise les séquences, réinsère tout — session détruite, reconnexion requise

### Vérification des liens

Accessible depuis la vue favoris (icône lien dans la barre d'outils) :

- Vérifie chaque URL une par une via cURL (HEAD, fallback GET si 405)
- Statuts : **OK** (2xx), **Redirigé** (301), **Inaccessible** (4xx/5xx), **Timeout** (erreur réseau)
- Indicateurs visuels colorés sur les 4 vues (badges, tableau, liste, explorateur) — point en bas à droite du badge coloré
- Page rapport groupée par statut avec barre de progression en temps réel
- **Mise à jour des URLs redirigées** : suit le 301 jusqu'à l'URL finale et met à jour le favori en base
- Suppression en lot des liens morts (inaccessibles et timeouts)
- Barres d'action fixes (rouge pour les morts, jaune pour les redirigés) — flottent au-dessus du footer avec fond opaque
- Bouton **Continuer** (reprend les liens non vérifiés) ou **Revérifier tout** — bouton **Stop** pour interrompre
- Réinitialisation des statuts en un clic
- Support proxy HTTP configurable depuis **Admin → Paramètres** (priorité DB → `.env` → vide)

---

### Migration depuis l'ancienne version
- Script `scripts/migrate_ini.php` — importe les favoris depuis les fichiers `.ini`
- Gestion de l'encodage (UTF-8/Latin-1), décodage des entités HTML, mapping des styles de badge
- Idempotent : peut être relancé sans créer de doublons
- Script `scripts/reset_bookmarks.php` — vide les tables `bookmarks` et `lists`

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

> **Important :** Modifier immédiatement les identifiants admin par défaut après l'installation.

### Migration depuis une ancienne version (fichiers `.ini`)

```bash
php scripts/migrate_ini.php /chemin/vers/dossier/datas
```

---

## Configuration

### Fonctionnement des fichiers d'environnement

| Fichier | Versionné | Rôle |
|---|---|---|
| `.env` | Oui | Valeurs par défaut |
| `.env.local` | **Non** | Surcharges locales/prod — jamais écrasé |

### Variables disponibles dans `.env`

```ini
APP_NAME="KT-Start"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com/

DB_DATABASE=database/app.sqlite

BOOKMARKS_PER_PAGE=24

# Proxy pour la vérification des liens (optionnel)
# CHECK_PROXY=http://proxy.example.com:3128
```

### Priorité de `BOOKMARKS_PER_PAGE`

1. **Base de données** (`settings.bookmarks_per_page`) — modifiable depuis Admin → Paramètres
2. **`.env` / `.env.local`**
3. **Défaut** : 24

### Priorité de `CHECK_PROXY`

1. **Base de données** (`settings.check_proxy`) — modifiable depuis Admin → Paramètres
2. **`.env` / `.env.local`**
3. **Défaut** : vide (pas de proxy)

---

## Compte admin par défaut

```
Email        : admin@example.com
Mot de passe : changeme
```

> **À modifier impérativement avant toute mise en production.**

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
│   ├── init-db.php                     # Schéma complet, migrations, compte admin
│   ├── migrate_ini.php                 # Import favoris depuis fichiers .ini
│   └── reset_bookmarks.php             # Vide tables bookmarks et lists
├── src/
│   ├── Config/
│   │   ├── BadgeStyles.php             # 12 styles de badge
│   │   └── Config.php                  # Accès aux variables d'environnement
│   ├── Controller/
│   │   ├── AdminController.php         # Utilisateurs, listes, paramètres, maintenance
│   │   ├── AuthController.php          # Connexion / déconnexion
│   │   └── BookmarkController.php      # home, index, store, update, delete, reorder, fetchMeta, bookmarklet, bookmarkletStore
│   ├── Core/
│   │   ├── Auth.php                    # Session ktstart_session, rôles
│   │   ├── Csrf.php
│   │   ├── Database.php                # Singleton PDO SQLite
│   │   ├── Flash.php
│   │   ├── Response.php
│   │   ├── Router.php                  # Routes par ?action=xxx
│   │   └── View.php                    # render(), renderRaw(), e(), asset()
│   ├── Repository/
│   │   ├── BookmarkRepository.php      # findPublic, findFiltered, CRUD, reorder, setFolderAndPosition, getAllTags
│   │   ├── FolderRepository.php        # CRUD dossiers, hiérarchie parent/enfant, wouldCreateCycle, deleteAndLiftChildren
│   │   ├── ListRepository.php          # CRUD + findDefault, setDefault, clearDefault
│   │   ├── SettingsRepository.php      # get, set, all — table settings clé/valeur
│   │   ├── StatsRepository.php         # overview, perUser, perList, perLinkStatus, topTags, perMonth, perBadgeStyle
│   │   └── UserRepository.php          # CRUD utilisateurs
│   └── Service/
│       ├── ImportExportService.php     # Export v1/v2, import avec détection auto, restauration complète
│       ├── MigrationService.php        # Migrations idempotentes
│       ├── UrlCheckService.php         # Vérification accessibilité URL (HEAD/GET, proxy)
│       └── UrlMetaService.php          # curl + DOMDocument → title/host/description
├── templates/
│   ├── layout.php
│   ├── admin/
│   │   ├── index.php                   # Dashboard 9 cartes de navigation
│   │   ├── users.php                   # Gestion utilisateurs
│   │   ├── lists.php                   # Gestion listes
│   │   ├── folders.php                 # Gestion dossiers — arbre hiérarchique + drag & drop
│   │   ├── settings.php                # Paramètres applicatifs
│   │   ├── tags.php                    # Gestion tags (tous utilisateurs)
│   │   ├── stats.php                   # Statistiques globales
│   │   ├── backup.php                  # Sauvegarde export/import
│   │   └── maintenance.php             # Migration + journal
│   ├── auth/login.php
│   └── bookmarks/
│       ├── index.php                   # 4 vues (badges/table/liste/explorer), dossiers, drag & drop, pagination, nuage de tags, tout afficher
│       ├── links.php                   # Rapport de vérification des liens
│       └── bookmarklet.php             # Popup autonome d'ajout rapide (sans layout)
├── .env
├── .env.local
├── .env.local.example
└── composer.json
```

---

## Schéma de base de données

| Table | Rôle |
|---|---|
| `users` | Comptes utilisateurs (email, hash, rôle) |
| `lists` | Listes (`is_default` pour liste affichée par défaut) |
| `bookmarks` | Favoris (URL, titre, tags, badge, visibilité, position, dossier) |
| `folders` | Dossiers organisés en hiérarchie parent/enfant par liste |
| `settings` | Paramètres applicatifs clé/valeur (ex: `bookmarks_per_page`) |

---

## Routes

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
| `bookmark_fetch_meta` | GET | Auth | Métadonnées d'une URL (JSON) |
| `bookmark_check_duplicate` | GET | Auth | Vérifier doublon d'URL (JSON) |
| `bookmark_links_report` | GET | Auth | Page rapport des liens |
| `bookmark_check_single` | POST | Auth | Vérifier une URL (JSON) |
| `bookmark_reset_status` | POST | Auth | Réinitialiser tous les statuts |
| `bookmark_delete_dead` | POST | Auth | Supprimer les favoris morts sélectionnés |
| `bookmark_follow_redirect` | POST | Auth | Mettre à jour l'URL finale d'un favori redirigé (JSON) |
| `bookmark_folder_store` | POST | Auth | Créer un dossier dans une liste |
| `bookmark_folder_rename` | POST | Auth | Renommer un dossier |
| `bookmark_folder_delete` | POST | Auth | Supprimer un dossier (remonte les enfants) |
| `bookmark_explorer_reorder` | POST | Auth | Réorganiser favoris et dossiers (vue Explorateur) |
| `bookmarklet` | GET | Public | Popup d'ajout rapide (URL + titre pré-remplis depuis le navigateur) |
| `bookmarklet_store` | POST | Public | Enregistrer le favori depuis la popup bookmarklet |
| `admin` | GET | Admin | Dashboard d'administration |
| `admin_users` | GET | Admin | Page gestion utilisateurs |
| `admin_lists` | GET | Admin | Page gestion listes |
| `admin_folders` | GET | Admin | Page gestion dossiers (arbre hiérarchique par liste) |
| `admin_settings` | GET | Admin | Page paramètres |
| `admin_backup` | GET | Admin | Page sauvegarde |
| `admin_maintenance` | GET | Admin | Page maintenance |
| `admin_tags` | GET | Admin | Page gestion tags |
| `admin_stats` | GET | Admin | Page statistiques globales |
| `admin_user_store` | POST | Admin | Créer un utilisateur |
| `admin_user_update` | POST | Admin | Modifier un utilisateur |
| `admin_user_delete` | POST | Admin | Supprimer un utilisateur |
| `admin_list_store` | POST | Admin | Créer une liste |
| `admin_list_rename` | POST | Admin | Renommer une liste |
| `admin_list_set_default` | POST | Admin | Définir/retirer la liste par défaut |
| `admin_list_delete` | POST | Admin | Supprimer une liste |
| `admin_folder_store` | POST | Admin | Créer un dossier (admin) |
| `admin_folder_rename` | POST | Admin | Renommer un dossier (admin) |
| `admin_folder_delete` | POST | Admin | Supprimer un dossier (admin, remonte les enfants) |
| `admin_folder_reorder` | POST | Admin | Réorganiser/nicher les dossiers (JSON, debounce) |
| `admin_setting_update` | POST | Admin | Mettre à jour les paramètres (DB) |
| `admin_run_migration` | POST | Admin | Lancer la migration de BDD |
| `admin_tag_rename` | POST | Admin | Renommer un tag (tous favoris) |
| `admin_tag_delete` | POST | Admin | Supprimer un tag (tous favoris) |
| `admin_tags_cleanup` | POST | Admin | Supprimer les tags utilisés une seule fois |
| `admin_export` | GET | Admin | Télécharger l'export favoris (v1) |
| `admin_export_full` | GET | Admin | Télécharger le backup complet (v2) |
| `admin_import` | POST | Admin | Importer un fichier JSON (v1 ou v2) |

---

## Pistes d'évolution

- Notifications de favoris expirés ou inaccessibles
- Rôle `editor` (multi-utilisateurs sans accès admin)
- Import de favoris au format HTML (Netscape bookmarks)

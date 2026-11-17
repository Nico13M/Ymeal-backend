# Aide pour dev le back en symfony

> J’explique ici les différents fichiers et leur utilité pour le développement. Pour toute autre question, me contacter en MP  (Nicolas Martinez).
> 

# .env / .env.local

Pour les variables d’environement il faut faire un .env et un .env.local 

> *le local est pour le dev, l’autre pour la prod, on va principalement faire du local et on mettra tout sur l’autre au moment du deploy*
> 

Pour l’instant on a juste ces variables : 

- DATABASE_URL
- DB_CONNECTION
- DEFAULT_URI
- APP_ENV
- APP_SECRET
- APP_SHARE_DIR

exemple : 

```php
# PostgreSQL
databaseUrl=postgres://username:password@localhost:5432/nom_de_la_db

# URL par défaut de l'application (nom possible: defaultURL ou DEFAULT_URL)
defaultURL=http://localhost:3000
DEFAULT_URL=https://mon-app.example.com
```

Plus tard, il faudra également ajouter une variable **MAILER_DSN** afin de gérer l’envoi d’e-mails pour les comptes créés.

> Évitez de committer les credentials ; utilisez des variables d'environnement, pour les utiliser, il faut faire (env: 'envTest:test') dans un paramètre de méthode
> 

exemple : 

```php
// Dans le .env : 
RECETTE_API=blablablatestcléapi

// Dans le controller : 
    public function __construct(
        #[Autowire(env: 'RecetteAPi:RECETTE_API')] private array $recetteApi,
    ) {
        return $recetteApi
        }
```

# Les controllers

Les controllers permettent de traiter des logiques métier et de renvoyer une réponse (définie avec l’équipe front) afin qu’elle puisse être exploitée.

L’utilité principale de nos controllers est d’utiliser un service, de traiter la donnée reçue puis de la renvoyer au front.

> Ils servent également à interagir avec la base de données créée par l’équipe Data.
> 

# Les services

Les services permettent d’alléger le **controller**. Ils constituent le **Model** dans le schéma MVC. Par exemple, ils peuvent être utilisés pour récupérer des données via un endpoint fourni par l’équipe Data.

> Un service peut être intégré dans un controller en l’injectant en paramètre d’une méthode.
>

# Sécurité et mise en route

Le détail de l'audit et des corrections est dans [SECURITY_AUDIT.md](SECURITY_AUDIT.md).

## Variables d'environnement à ajouter

- **APP_SECRET** : 32 caractères minimum (sinon l'API refuse de signer les jetons). Générer avec `php -r "echo bin2hex(random_bytes(32));"`. À mettre dans `.env.local`, jamais dans un fichier commité.
- **EXTERNAL_API_TOKEN** : jeton du service externe de l'export de données (sans lui, l'export échoue volontairement).
- **PYTHON_API_URL** : URL du service Python de génération de recettes.
- En production : `APP_ENV=prod` et `APP_DEBUG=0`.

## Authentification

Il n'y a plus de `X-User-Id`. La connexion (`POST /admin/auth/login`) et l'inscription (`POST /admin/auth/register`) renvoient `data.token`. Le front l'envoie ensuite dans chaque requête :

```
Authorization: Bearer <token>
```

- Le jeton expire au bout de 7 jours et devient invalide si le mot de passe change : le front doit alors renvoyer vers la connexion (réponse 401).
- Mot de passe : 12 caractères minimum. Changer son mot de passe ou son e-mail demande `current_password` dans la requête.
- Sans jeton valide, toute route répond 401, sauf `/`, `/admin/auth/login`, `/admin/auth/register` et `/admin/security/csrf-token`.

## Administrateurs

Seuls les comptes `ROLE_ADMIN` peuvent créer, modifier ou supprimer les ingrédients, unités, régimes et abonnements, et voir la liste de tous les utilisateurs. Un utilisateur normal reçoit 403.

```bash
php bin/console app:user:promote email@exemple.fr            # donner le rôle admin
php bin/console app:user:promote email@exemple.fr --revoke   # le retirer
```

## Base de données

Après avoir récupéré la branche, appliquer la migration (elle ajoute les rôles et rend l'e-mail unique, en minuscules). Elle échoue s'il existe deux comptes avec le même e-mail : corriger les doublons puis relancer. Faire une sauvegarde avant.

```bash
php bin/console doctrine:migrations:migrate
```

### Base de développement locale (SQLite)

Pour développer sans serveur PostgreSQL, mettre dans `.env.local` :

```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"
```

puis créer les tables (pas de migration, elles sont écrites pour PostgreSQL) :

```bash
php bin/console doctrine:schema:create
```

Limite : la reconnaissance automatique des ingrédients dans un texte de recette générée par l'IA utilise une fonction PostgreSQL et ne marche pas sous SQLite. La production utilise PostgreSQL avec les migrations.

## Tests de sécurité

```bash
php tests/security/harness.php              # 150 contrôles, SQLite en mémoire, sans toucher à votre base
HENV=prod php tests/security/harness.php    # avec la configuration de production
```

Résultats et historique : [tests/security/RESULTATS.md](tests/security/RESULTATS.md). À relancer après toute modification touchant l'authentification, les droits ou les routes.

## Dépendances

```bash
composer audit    # doit afficher « No security vulnerability advisories found »
```

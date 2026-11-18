# Commandes du projet Ymeal (back)

À lancer dans `C:\Ymeal-backend`, sauf indication contraire. Les commandes sont en PowerShell.

## 1. Installation

```powershell
composer install                      # installe les dépendances
composer audit                        # avis de sécurité sur les dépendances (à lancer avant chaque déploiement)
composer outdated                     # paquets à mettre à jour
composer update "symfony/*" --with-all-dependencies   # mise à jour de Symfony (correctifs)
```

Fichier `.env.local` (ignoré par Git), sans espace autour du `=` :

```
APP_SECRET=<au moins 32 caractères>
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"
```

Générer un secret :

```powershell
php -r "echo bin2hex(random_bytes(32));"
```

Extensions PHP pour SQLite : dans `C:\php\php.ini`, retirer le `;` devant `extension=pdo_sqlite` et `extension=sqlite3`.
Vérifier : `php -m | findstr /i sqlite`

## 2. Lancer le back

```powershell
php -S 127.0.0.1:8000 -t public                      # mode dev (ouvrir http://127.0.0.1:8000)
php bin/console cache:clear                          # vider le cache
```

Mode production en local :

```powershell
$env:APP_ENV="prod"; $env:APP_DEBUG="0"
php -d variables_order=EGPCS -S 127.0.0.1:8000 -t public
```

`-d variables_order=EGPCS` est nécessaire : sans lui, le serveur ignore les variables d'environnement.

## 3. Base de données

```powershell
php bin/console doctrine:database:create             # crée la base (PostgreSQL)
php bin/console doctrine:schema:create               # crée toutes les tables (base SQLite de dev)
php bin/console doctrine:schema:validate             # compare entités et base
php bin/console doctrine:schema:update --dump-sql    # affiche le SQL de différence (sans l'exécuter)
php bin/console doctrine:schema:drop --force         # SUPPRIME toutes les tables
php bin/console doctrine:database:drop --force       # SUPPRIME la base
```

Migrations (écrites pour PostgreSQL : ne pas les lancer sur la base SQLite de dev) :

```powershell
php bin/console doctrine:migrations:status           # état des migrations
php bin/console doctrine:migrations:list             # liste des migrations
php bin/console doctrine:migrations:migrate          # applique les migrations (sauvegarder la base avant)
php bin/console doctrine:migrations:diff             # génère une migration depuis les entités
php bin/console doctrine:migrations:up-to-date       # le schéma est-il à jour ?
```

Réinitialiser la base de dev SQLite :

```powershell
Remove-Item var\data_dev.db
php bin/console doctrine:schema:create
```

## 4. Comptes et administrateurs

```powershell
php bin/console app:user:promote email@exemple.fr            # donne ROLE_ADMIN
php bin/console app:user:promote email@exemple.fr --revoke   # retire ROLE_ADMIN
```

## 5. Inspection du projet

```powershell
php bin/console debug:router                         # toutes les routes
php bin/console debug:router --format=json           # idem en JSON
php bin/console debug:router admin_recipe_show       # détail d'une route
php bin/console about                                # infos sur l'environnement
php bin/console doctrine:mapping:info                # entités reconnues
php bin/console list                                 # toutes les commandes disponibles
```

## 6. Vérifications de qualité

```powershell
php bin/console lint:container                       # injections de dépendances valides
php bin/console lint:yaml config                     # fichiers YAML de config valides
php -l src\Controller\Admin\AdminUserController.php  # syntaxe d'un fichier PHP
composer audit
```

## 7. Tests de sécurité

Banc de test interne (SQLite en mémoire, ne touche pas à votre base) :

```powershell
php tests/security/harness.php                       # environnement de test
$env:HENV="prod"; php tests/security/harness.php     # configuration de production
$env:HDEBUG="1"; php tests/security/harness.php      # détail des erreurs 500
```

Collection Postman (importer le fichier dans Postman, puis Collection Runner), ou en ligne de commande :

```powershell
npx newman run tests/security/postman/ymeal-securite.postman_collection.json
npx newman run tests/security/postman/ymeal-securite.postman_collection.json --env-var "baseUrl=http://127.0.0.1:8000" --env-var "adminEmail=admin@ymeal.test" --env-var "adminPassword=<mot de passe>"
```

Test d'intrusion actif (cible 127.0.0.1:8000, **base jetable** : il crée et promeut des comptes de test, ne jamais le lancer sur une vraie base) :

```powershell
$env:DATABASE_URL="sqlite:///C:/temp/pentest.db"; $env:APP_ENV="prod"; $env:APP_DEBUG="0"
php bin/console doctrine:schema:create --no-interaction
Start-Process php -ArgumentList "-d","variables_order=EGPCS","-S","127.0.0.1:8000","-t","public"
php -d extension_dir=C:/php/ext tests/security/pentest.php
```

Le script affiche `[ OK ]`, `[FAIL/gravité]` ou `[INFO]` pour chaque vérification, puis un résumé. Supprimer `C:/temp/pentest.db` ensuite.

À faire avant chaque passage sur une base SQLite : repartir d'une base vide (voir section 3), et lancer le serveur comme décrit en section 2.

Limitation des tentatives : après des essais répétés, la connexion et l'inscription renvoient 429 pendant 15 minutes (connexion) ou une heure (inscription). Pour repartir de zéro en développement :

```powershell
php bin/console cache:pool:clear cache.rate_limiter
```

## 8. Documentation de l'API (Swagger)

```powershell
php -S 127.0.0.1:9000 -t docs                        # puis ouvrir http://127.0.0.1:9000/swagger.html
```

Spécification : [docs/openapi.yaml](docs/openapi.yaml), importable dans Postman.

## 9. Appels directs à l'API (curl)

```powershell
curl http://127.0.0.1:8000/admin/security/csrf-token

curl -X POST http://127.0.0.1:8000/admin/auth/register -H "Content-Type: application/json" -d '{\"firstname\":\"Alice\",\"lastname\":\"Test\",\"email\":\"alice@ymeal.test\",\"password\":\"MotDePasseTest-12345\"}'

curl -X POST http://127.0.0.1:8000/admin/auth/login -H "Content-Type: application/json" -d '{\"email\":\"alice@ymeal.test\",\"password\":\"MotDePasseTest-12345\"}'

curl http://127.0.0.1:8000/admin/recipes/index -H "Authorization: Bearer <token>"
```

Sous PowerShell, utilisez `curl.exe` à la place de `curl` (qui est un alias de `Invoke-WebRequest`).

## 10. Git

```powershell
git status                                           # état du dépôt
git diff                                             # modifications non commitées
git add docs tests/security COMMANDES.md FRONT_CHANGES.md   # ajouter des fichiers précis
git commit -m "message"
git push
git rm --cached .env .env.dev                        # ne plus suivre ces fichiers (ils restent sur le disque)
git rm --cached .DS_Store src/.DS_Store              # déjà fait le 2026-11-18
git checkout -- config/reference.php                 # annuler un fichier régénéré par Symfony
```

## 11. Déploiement (variables d'environnement de production)

```
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<au moins 32 caractères, différent du local>
DATABASE_URL=postgresql://...
EXTERNAL_API_TOKEN=<jeton du service d'export>
PYTHON_API_URL=<url du service IA, non joignable depuis Internet>
TRUSTED_PROXIES=REMOTE_ADDR
```

`php.ini` de production : `register_argc_argv=Off` et `expose_php=Off`.
Derrière Scalingo, définir aussi `TRUSTED_PROXIES` (par exemple `REMOTE_ADDR`) pour que la limitation des tentatives voie l'IP réelle des clients.
Après déploiement : `php bin/console doctrine:migrations:migrate`, puis `php bin/console app:user:promote votre@email.fr`.

## 12. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| `Missing = in the environment variable declaration` | Mauvais format dans `.env.local` | Écrire `NOM=valeur`, sans espace ni `:` |
| 500 « APP_SECRET doit faire au moins 32 caractères » | Secret absent ou trop court | Renseigner `APP_SECRET` dans `.env.local` |
| `could not find driver` | `pdo_sqlite` non activé | Décommenter les extensions dans `php.ini` |
| Les variables d'environnement sont ignorées par `php -S` | `variables_order` sans `E` | Ajouter `-d variables_order=EGPCS` |
| 500 sur `/admin/recipes/index` après des tests | Recettes orphelines en SQLite (clés étrangères non appliquées) | Recréer la base (section 3) |
| 401 partout | Jeton absent, expiré ou mot de passe changé | Se reconnecter via `/admin/auth/login` |
| 200 sur une route admin pour un non-admin avec `//` dans le chemin | Règle `access_control` trop étroite (corrigée le 2026-11-18) | Vérifier `security.yaml` : `^/admin/user-subscribe/*$` |
| 429 sur la connexion ou l'inscription | Limitation des tentatives (5 échecs par IP et e-mail / 15 min, 10 inscriptions / heure) | Attendre `Retry-After`, ou en dev : `php bin/console cache:pool:clear cache.rate_limiter` |
| 403 sur une écriture | Jeton CSRF absent, ou rôle insuffisant | Envoyer `X-CSRF-TOKEN` ; vérifier le rôle ADMIN |

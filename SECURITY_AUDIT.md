# Audit de sécurité : suivi des corrections

Branche : `Fix/securite` — audit du 2026-11-17, corrections appliquées dans la foulée, état mis à jour le 2026-11-18 (test d'intrusion et correction du contournement par double slash).
**Rien n'est commité ni poussé** : les changements sont dans le répertoire de travail.

## 0. État au 2026-11-18

**Code : failles critiques et force brute corrigées.** Le banc de test passe à 160 sur 160 (aussi en configuration de production), `composer audit` ne signale rien. Il reste à traiter, côté code, des constats de gravité moyenne ou basse du test d'intrusion (plafonds de taille, en-têtes de sécurité), puis des actions d'infrastructure (base de production, variables d'environnement dont `TRUSTED_PROXIES`, front).

### Test d'intrusion du 2026-11-18 (local, base jetable)

Un test d'intrusion actif (`php tests/security/pentest.php`) et la collection Postman (`tests/security/postman/`, 143 vérifications) ont été passés sur une instance locale en configuration de production.

- **Faille critique trouvée et corrigée : contournement du contrôle d'accès par double slash.** `GET /admin/user-subscribe//` (ou `///`, `/%2f`) renvoyait à un utilisateur normal la liste complète des abonnements de tous les comptes, parce que la règle `^/admin/user-subscribe/?$` de `security.yaml` ne couvrait pas ces variantes. Correction : règle `^/admin/user-subscribe/*$` et contrôle `isAdmin()` dans `AdminUserSubscribeController::index` (défense en profondeur). Vérifié : plus aucun contournement par chemin, banc de test toujours à 150 sur 150.
- **Résistent au test** : jetons falsifiés (`alg: none`, signature supprimée, charge utile modifiée, expiration repoussée), énumération d'identifiants (IDOR), escalade de rôle par champs en trop, environ 200 injections (SQL, modèles, traversée de chemin), fichiers sensibles non servis, CORS, concurrence à l'inscription, ancien jeton refusé après changement de mot de passe ou suppression du compte.
- **Constats restants (non corrigés)** :
  - ~~Haute : aucune limitation des tentatives de connexion~~ **Corrigé le 2026-11-18** : `symfony/rate-limiter`, voir « Limitation des tentatives » ci-dessous.
  - Moyenne : aucun plafond de taille sur les recettes (description de 2 Mo et 20 000 étapes acceptées). La limitation des inscriptions est corrigée (10 par heure et par IP).
  - Basse : aucun en-tête de sécurité HTTP ; `X-Powered-By: PHP/x.y.z` visible (`expose_php=Off` en production).
  - À savoir : le jeton CSRF est lié à une session (cookie `PHPSESSID`) ; les URL d'image internes (`http://169.254.169.254/...`) sont stockées sans être téléchargées par le back, donc le front et le service Python ne doivent jamais les récupérer côté serveur ; bcrypt ne lit que les 72 premiers octets d'un mot de passe.

Dernières décisions et changements (2026-11-18) :
- **Base de développement** : l'ancienne base Aiven n'existe plus (le nom d'hôte n'est plus résolu par le DNS). `DATABASE_URL` de `.env.local` pointe désormais vers une base **SQLite locale** `var/data_dev.db`, créée avec `php bin/console doctrine:schema:create` (23 tables, ignorée par Git). Elle est vide et **sans suivi de migrations** : ne pas y lancer `doctrine:migrations:migrate`, les migrations sont écrites pour PostgreSQL. Limite : la reconnaissance automatique des ingrédients dans un texte de recette IA utilise `similarity()` de PostgreSQL et ne marche pas sous SQLite.
- **Compte administrateur de développement** créé dans cette base : `admin@ymeal.test`, rôle `ROLE_ADMIN`. Le mot de passe (aléatoire) n'est écrit dans aucun fichier : en cas d'oubli, changer le mot de passe via `PATCH /admin/user/1/edit`, ou supprimer `var/data_dev.db`, recréer le schéma et se réinscrire.
- **Purge de l'historique Git** abandonnée (Supabase n'existe plus). `.env` et `.env.dev` sont toujours suivis par Git mais ne contiennent plus de secret.
- **Fuite corrigée en dernier** : les notes d'une recette affichaient l'e-mail de chaque auteur de note (`AdminRatingController`) ; elles affichent maintenant le pseudo. Le banc de test vérifie ce point.
- `APP_SECRET` local remplacé (64 caractères). Sauvegarde et miroir de la répétition de purge supprimés.

### Récapitulatif de ce qui reste

| Priorité | Action | Qui |
|---|---|---|
| Avant la mise en ligne | Créer une base PostgreSQL hébergée, mettre son URL dans `DATABASE_URL` de la plateforme, lancer `doctrine:migrations:migrate` (sauvegarde avant), puis `app:user:promote` | Vous (je peux le faire avec l'URL dans `.env.local`) |
| Avant la mise en ligne | Production : nouvel `APP_SECRET`, `APP_ENV=prod`, `APP_DEBUG=0`, `EXTERNAL_API_TOKEN`, `PYTHON_API_URL`, `register_argc_argv=Off` | Vous |
| Avant la mise en ligne | Vérifier sur PostgreSQL et avec le front réel : migration, `schema:validate`, IA, export, parcours navigateur et CORS (liste détaillée en section 1) | Vous / équipe |
| Avant la mise en ligne | Adapter le front : `Authorization: Bearer <token>` à la place de `X-User-Id`, gérer le 401, mot de passe de 12 caractères, `current_password` | Équipe front ; liste détaillée dans [FRONT_CHANGES.md](FRONT_CHANGES.md) (cookies, CSRF, 429, `Retry-After`) | Équipe front |
| Avant la mise en ligne | **Définir `TRUSTED_PROXIES`** sur la plateforme (sinon la limitation par IP voit l'IP du routeur et vise tous les clients ensemble), ajouter les en-têtes de sécurité, `expose_php=Off` | Vous / moi |
| Optionnel | `git rm --cached .env .env.dev` (`.DS_Store` n'est plus suivi et `.gitignore` est complété) | Vous |
| Plus tard | Limitation des tentatives de connexion, suppression d'un compte avec recettes, consentement avant l'export RGPD, liste blanche vers le service Python, validations sur les autres entités, tests PHPUnit, test d'intrusion sur un environnement de test | À planifier |

### Test total du 2026-11-18 (avant commit)

Contrôles statiques : `php -l` sur tous les fichiers PHP modifiés, `lint:container`, `lint:yaml config`, `composer validate`, `composer audit` : tous OK. Banc interne : **160 sur 160** en environnement de test et en production. Collection Postman : 142 sur 143 (seul écart : version de PHP exposée). Test d'intrusion sur serveur réel en production : **140 réussites, 10 écarts, aucun critique ni haut** (en-têtes de sécurité, version de PHP, plafonds de taille des recettes, et `TRACK`/`FOO` qui viennent du serveur `php -S`). Détail dans [tests/security/RESULTATS.md](tests/security/RESULTATS.md). Changements attendus côté front : [FRONT_CHANGES.md](FRONT_CHANGES.md).

### Limitation des tentatives (2026-11-18)

Ajout de `symfony/rate-limiter` (seule dépendance ajoutée ; `composer audit` : aucun avis). Configuration dans `config/packages/framework.yaml`, appliquée dans `AdminAuthController` :

| Limiteur | Clé | Limite | Effet |
|---|---|---|---|
| `login_account` | IP + e-mail | 5 tentatives / 15 min (fenêtre glissante) | 429 ; remis à zéro après une connexion réussie. Le bon mot de passe est aussi refusé pendant le blocage. |
| `login_ip` | IP | 30 tentatives / 15 min | 429 (anti-pulvérisation d'un mot de passe sur beaucoup de comptes) |
| `register_ip` | IP | 10 inscriptions / heure | 429 |

La réponse est `429 {"error":"Too many attempts, try again later"}` avec l'en-tête `Retry-After` (secondes). En environnement `test`, les limiteurs sont désactivés (`no_limit`). Le banc de test (`harness`) leur donne une IP différente à chaque appel et les vérifie dans la section 10.7c.

Points d'attention :
- **`TRUSTED_PROXIES`** : derrière Scalingo, l'IP du client n'est lue dans `X-Forwarded-For` que si cette variable d'environnement est définie (par exemple `REMOTE_ADDR`, ou la liste des IP du routeur). Sans elle, tous les clients partagent l'IP du routeur : 10 inscriptions par heure pour toute la plateforme, et 30 connexions échouées par 15 minutes pour tout le monde. **À définir avant la mise en ligne.**
- Le compteur est stocké dans le cache de l'application (fichiers). Avec plusieurs conteneurs, chacun a son propre compteur : la limite effective est multipliée par leur nombre. Pour un compteur commun, configurer un cache partagé (Redis, ou la base) pour le pool `cache.rate_limiter`.
- Un attaquant peut bloquer la connexion d'une victime depuis sa propre IP uniquement (la clé contient l'IP) : le compte n'est pas verrouillé globalement.
- Pour vider les compteurs en développement : `php bin/console cache:pool:clear cache.rate_limiter`.

## 1. Reste à faire (par priorité)

### Immédiat : sans ça, l'application ne démarre pas ou reste exposée

- [x] **Ancien projet Supabase** : abandonné, confirmé par le propriétaire (plus de Supabase). Son ancien mot de passe reste lisible dans l'historique Git (commits `fe292ac`, `e3bc959`, `c677219`) mais ne donne plus accès à rien. Il ne reste que la purge de l'historique, par hygiène (voir plus bas).
- [x] **Base Aiven** : le nom d'hôte configuré dans `.env.local` n'existe plus (DNS : « Non-existent domain », vérifié le 2026-11-18 alors qu'Internet fonctionne), donc le service Aiven semble supprimé. `.env.local` pointe sur une base qui n'existe plus : la migration et la création de l'admin n'ont **pas pu être lancées**, voir plus bas.
- [x] **`APP_SECRET` local** : remplacé par une valeur de 64 caractères dans `.env.local` (2026-11-18). **Reste à faire : le même changement en production** (variable d'environnement de la plateforme) ; les sessions et jetons existants seront invalidés.
- [x] **Purge de l'historique Git : abandonnée sur décision du propriétaire (2026-11-18).** Le projet Supabase n'existe plus, donc l'ancien mot de passe encore lisible dans l'historique (commits `fe292ac`, `e3bc959`, `c677219`, `0ce9866`) ne donne plus accès à rien. La copie miroir et la sauvegarde créées pour la répétition ont été supprimées. Si vous changez d'avis : `git clone --mirror`, `git filter-repo --path .env --path .env.dev --invert-paths`, puis `git push --force` des branches (les pull requests `refs/pull/*` ne peuvent pas être réécrites par un push).
- [ ] **Ne plus suivre `.env` et `.env.dev` dans Git** (le `.gitignore` les ignore déjà, mais ils sont encore suivis) : `git rm --cached .env .env.dev`. Je n'ai pas touché à l'index. `.env` contient des valeurs par défaut sans secret depuis mon nettoyage ; `.env.dev` ne contient plus que des commentaires.

### Avant la mise en production

- [ ] **Lancer la migration** (sur la base PostgreSQL de production, pas sur la base SQLite de développement) `Version20261117100000` : `php bin/console doctrine:migrations:migrate`. Elle ajoute `user.roles`, met les e-mails en minuscules et crée l'index unique sur `user.email`. **Elle échoue s'il existe deux comptes avec le même e-mail** (à la casse près) : fusionner ou renommer les doublons, puis relancer. Sauvegarder la base avant.
- [ ] **Créer au moins un administrateur** : `php bin/console app:user:promote votre@email.fr`. Sans admin, personne ne peut gérer les ingrédients, unités, régimes et offres d'abonnement.
- [ ] **Adapter le front** :
  - envoyer `Authorization: Bearer <data.token>` (renvoyé par `/admin/auth/login` et `/admin/auth/register`) au lieu de `X-User-Id` ;
  - gérer le 401 (jeton expiré après 7 jours ou mot de passe changé) en renvoyant vers la connexion ;
  - mot de passe de 12 caractères minimum à l'inscription et au changement ;
  - envoyer `current_password` pour changer son mot de passe ou son e-mail ;
  - ne plus attendre `author.email` dans les recettes ;
  - les écritures sur ingrédients, unités, régimes et offres renvoient 403 pour un non-admin.
- [ ] **Variables d'environnement de production** (Scalingo) : `APP_ENV=prod`, `APP_DEBUG=0`, `APP_SECRET` fort, `DATABASE_URL` avec le nouveau mot de passe et un rôle applicatif qui n'est pas superutilisateur, `EXTERNAL_API_TOKEN` (sinon l'export de données échoue volontairement), `PYTHON_API_URL` non joignable depuis Internet.
- [ ] **PHP** : `register_argc_argv=Off` dans le `php.ini` de production (voir avis CVE-2026-47767 sur `symfony/runtime`).
- [ ] **Restreindre l'accès réseau à la base** (liste d'IP autorisées, SSL obligatoire).
- [ ] **Relancer `composer audit`** avant chaque déploiement.
- [ ] **Vérifications à faire une fois la base PostgreSQL et le front en place** (non couvertes par le banc de test, qui tourne sur SQLite) :
  - la migration `Version20261117100000` s'exécute sans erreur sur PostgreSQL (d'abord sur une copie ou un environnement de test) ; en cas de doublons d'e-mail, elle s'arrête et la base reste intacte ;
  - `doctrine:schema:validate` ne signale plus d'écart entre les entités et la base ;
  - la reconnaissance des ingrédients dans une recette générée par l'IA (extension `pg_trgm`, fonction `similarity()`) ;
  - `/admin/recipes/generate`, `/admin/recipes/ai/save` et `/admin/recipes/user/data/send` avec les vrais services (IA et export) ;
  - le parcours complet dans un navigateur : inscription, connexion, jeton `Bearer`, requêtes CORS depuis `https://frontmeal.osc-fr1.scalingo.io`, expiration du jeton (401) ;
  - sur la production : page d'erreur générique (pas de trace) en provoquant une erreur, et `APP_DEBUG=0` effectif.

### Plus tard

- [x] ~~Limitation des tentatives de connexion~~ : faite le 2026-11-18 (`symfony/rate-limiter`, voir la section « Limitation des tentatives »). Reste à envisager : stockage partagé si plusieurs instances (voir plus bas).
- [ ] Supprimer proprement un compte qui a des recettes (aujourd'hui la clé étrangère fait échouer la suppression, ce qui bloque le droit à l'effacement RGPD).
- [ ] Consentement explicite avant l'export `POST /admin/recipes/user/data/send`.
- [ ] Filtrer les champs transmis au service Python dans `/admin/recipes/generate` (liste blanche).
- [ ] Contraintes de validation (`Assert\*`) sur les autres entités (longueurs des champs) et plafonds de taille sur les recettes (description, nombre d'étapes, nombre d'ingrédients).
- [ ] En-têtes de sécurité HTTP (`X-Content-Type-Options: nosniff`, `X-Frame-Options`, `Strict-Transport-Security`, `Content-Security-Policy`, `Referrer-Policy`), au niveau de la plateforme ou d'un écouteur Symfony.
- [x] Limitation des inscriptions en rafale : faite (10 par heure et par IP).
- [ ] Révocation de jeton hors changement de mot de passe (liste de jetons révoqués), si nécessaire.
- [ ] Retirer le CSRF une fois le front passé aux jetons Bearer (il n'apporte plus rien avec ce mode d'authentification), ou le lier à la session.
- [ ] Brancher le banc de test (`php tests/security/harness.php`) dans une intégration continue, et éventuellement le convertir en tests PHPUnit.
- [ ] Test d'intrusion sur un environnement de staging que vous possédez.
- [ ] Retirer `.DS_Store` du suivi Git (`git rm --cached .DS_Store src/.DS_Store`) et passer `minimum-stability` à `stable` si possible.

## 2. Résultats des vérifications

| Vérification | Avant | Après |
|---|---|---|
| Banc de test (SQLite en mémoire) | 25 OK, 42 échecs (67 contrôles) | **150 OK, 0 échec** (détail : `tests/security/RESULTATS.md`) |
| `composer audit` | 32 avis, 12 paquets | **aucun avis** |
| `php -l` sur tous les fichiers modifiés | — | OK |
| `bin/console lint:container` | — | OK |
| Commande `app:user:promote` | — | OK (ajout, retrait, e-mail inconnu) |

Limites : les tests tournent sur SQLite, pas sur PostgreSQL. La migration n'a pas été exécutée sur une vraie base (le nom d'index `UNIQ_8D93D649E7927C74` a été vérifié par calcul). Aucun test n'a été fait sur un serveur en ligne.

### Couverture des tests (2026-11-18)

Le banc de test (150 contrôles, `tests/security/`) couvre trois familles :
- **Attaques bloquées** : usurpation par `X-User-Id`, jeton falsifié, expiré ou révoqué, accès aux comptes, abonnements, recettes privées et données de référence par un utilisateur normal, énumération de comptes, CORS, fuites d'informations.
- **Robustesse** : chacune des 81 routes sans jeton (401), variantes de chemin et de méthode, injection de champs `roles`/`password_hash`, injections SQL, entrées JSON malformées (aucune ne doit finir en 500), CSRF, absence de hash ou de secret dans toutes les réponses.
- **Usages normaux toujours fonctionnels** (non-régression) : référentiels, préférences, frigo, favoris, notes, recherche, tendances, modification et suppression de ses propres recettes, suppression de son compte, et les opérations d'administration (ingrédients, régimes, unités, offres, abonnements, comptes).

**Non testé** (à vérifier à la main ou plus tard) :
- PostgreSQL et la migration `Version20261117100000` (les tests tournent sur SQLite).
- Les routes qui appellent un service externe : génération IA (`/admin/recipes/generate`, `/admin/recipes/ai/save` avec un vrai texte), export `/admin/recipes/user/data/send`.
- Le comportement du front avec les jetons Bearer et les requêtes CORS depuis un navigateur.
- La configuration de production (`APP_ENV=prod`, variables d'environnement, `php.ini`).
- La reconnaissance automatique des ingrédients dans un texte IA (fonction PostgreSQL `similarity()`).

Deux défauts **déjà présents avant l'audit** ont été trouvés par ces tests et corrigés dans `RecipeSearchService` :
1. `GET /admin/recipes/search` répondait 500 dès que l'utilisateur avait un régime (nom d'association inexistant `dietsHasRecipe`).
2. Le filtre de la liste noire ne retirait que des lignes de la jointure : une recette contenant un ingrédient interdit **et** d'autres ingrédients restait dans les résultats (risque pour les allergies). Il utilise maintenant `NOT EXISTS`.

## 3. Ce qui a été fait

### Authentification et accès
| Fichier | Changement |
|---|---|
| `src/Security/ApiTokenService.php` (nouveau) | Jeton `base64url(payload).base64url(HMAC-SHA256)` : id, expiration (7 jours), empreinte du hash du mot de passe. Refuse un `APP_SECRET` de moins de 32 caractères. |
| `src/Security/ApiTokenAuthenticator.php` | Lit `Authorization: Bearer`. Réponse 401 JSON générique. Plus de `X-User-Id`. |
| `src/Security/JsonAccessDeniedHandler.php` (nouveau) | 403 en JSON. |
| `config/packages/security.yaml` | `access_control` : public = accueil, login, register, jeton CSRF ; écriture sur ingrédients, unités, régimes, offres et abonnements = `ROLE_ADMIN` ; reste = `ROLE_USER`. `role_hierarchy`. |
| `src/Entity/User.php` | Colonne `roles`, `setRoles()`, e-mail unique, `UniqueEntity`, `Assert\Email`. |
| `migrations/Version20261117100000.php` (nouveau) | `roles`, e-mails en minuscules, index unique. |
| `src/Command/PromoteUserCommand.php` (nouveau) | `app:user:promote <email> [--revoke]`. |
| `src/Service/UserManager.php` | `ensureAuthenticated` vérifie l'utilisateur connecté (plus de `API_TOKEN`). Helpers `isAdmin`, `canAccessUserId`, `forbidden`. |
| `src/Service/AuthManager.php` | E-mail normalisé et validé, mot de passe de 12 caractères minimum, erreur de connexion identique que le compte existe ou non, gestion de l'inscription simultanée. Plus de connexion programmatique inutile. |
| `src/Controller/Admin/AdminAuthController.php` | Renvoie `data.token` (login et register). |

### Contrôle des ressources
| Fichier | Changement |
|---|---|
| `AdminUserController` | Liste = admin. Lecture, modification et suppression = propriétaire ou admin. `current_password` obligatoire pour changer mot de passe ou e-mail. |
| `AdminUserSubscribeController` | Lecture = propriétaire ou admin. Route de suppression réparée. Plus de fuite d'exception. |
| `AdminRecipeIngredientController` | Contrôle de l'auteur. Unité en texte (`unitLabel`). |
| `AdminDietController` | Régimes d'un utilisateur = propriétaire ou admin. Messages génériques. |
| `AdminRecipeController` + `RecipeRepository` | Visibilité (publique ou auteur) sur liste, détail, compteur, favoris. Slug toujours généré par le serveur. URL d'image http(s) uniquement. Validation du nom. |
| `AdminRatingController` | Notes limitées aux recettes visibles. Commentaire de 2000 caractères maximum. Route `/debug` supprimée. Plus d'écho des données reçues. Pseudo à la place de l'e-mail des auteurs de notes. |
| `AdminFrigoController` | `X-User-Id` ignoré. `/count` réparé (dépendance non injectée). |
| `RecipeService` | `author.email` retiré. |
| `src/EventListener/ApiExceptionListener.php` (nouveau) | Sur `/admin/*` : donnée mal typée (tableau à la place d'un texte) -> 400 JSON ; suppression d'un élément encore utilisé ou doublon -> 409 JSON ; plus de 500. Aucun détail technique renvoyé, exception complète dans les logs. |
| `RecipeSearchService` | Recherche réparée (500 avec un régime) et liste noire fiable (`NOT EXISTS`). Défauts d'origine. |

### Configuration et fuites
| Fichier | Changement |
|---|---|
| `.gitignore` (2026-11-18) | Ajout de `.DS_Store`, `*.db`, `*.sqlite*`, `*.bak` et des fichiers temporaires des tests d'intrusion ; `.DS_Store` retiré du suivi Git. |
| `FRONT_CHANGES.md`, `COMMANDES.md`, `docs/` (2026-11-18) | Documentation : changements attendus côté front, commandes du projet, spécification OpenAPI (Swagger). |
| `config/packages/framework.yaml` (2026-11-18) | `rate_limiter` (`login_account`, `login_ip`, `register_ip`), `trusted_proxies` via `TRUSTED_PROXIES`. |
| `AdminAuthController` (2026-11-18) | Limitation des tentatives de connexion et d'inscription (429 + `Retry-After`). |
| `composer.json` / `composer.lock` (2026-11-18) | Ajout de `symfony/rate-limiter` 7.4. |
| `config/packages/security.yaml` (2026-11-18) | Règle `^/admin/user-subscribe/*$` à la place de `/?$` : corrige le contournement par double slash. |
| `AdminUserSubscribeController` (2026-11-18) | `index()` vérifie `isAdmin()` (défense en profondeur). |
| `config/packages/nelmio_cors.yaml` | Regex ancrée, `localhost` seulement en dev, `X-User-Id` retiré. `Retry-After` exposé aux navigateurs (`expose_headers`) pour les réponses 429. |
| `.env`, `.env.dev` | Mot de passe et `APP_SECRET` retirés du contenu (toujours suivis par Git, voir « Reste à faire »). |
| `DataService`, `AiRecipeService`, `AdminAiRecipeController`, `AppController` | Pas de jeton par défaut, messages d'erreur génériques, chemin interne retiré de l'accueil. |
| `src/Form/UserEditType.php` | Longueur maximale alignée sur la colonne (45). |

### Dépendances
`composer update "symfony/*" --with-all-dependencies` : Symfony 7.4.4 à 7.4.7 passé à 7.4.23 (patch). `composer.json` : `symfony/flex ^2.12`, `symfony/maker-bundle ^1.68` (remontés automatiquement). `config/reference.php` a été régénéré par Symfony (bruit sans importance).

## 4. Banc de test (dans le dépôt)

Fichiers : `tests/security/pentest.php` (test d'intrusion actif, voir `COMMANDES.md`), `tests/security/postman/` (collection Postman), `docs/openapi.yaml` (documentation Swagger), `tests/security/harness.php` (le banc, 160 contrôles dont la limitation des tentatives), [tests/security/RESULTATS.md](tests/security/RESULTATS.md) (résultats détaillés et historique), `tests/security/dernier-resultat.txt` (trace brute de la dernière exécution).

```bash
php tests/security/harness.php              # environnement de test
HENV=prod php tests/security/harness.php    # configuration de production, debug désactivé
HDEBUG=1 php tests/security/harness.php     # détail des erreurs 500 en cas d'échec
```

Il démarre le noyau Symfony dans un environnement isolé, sur SQLite en mémoire (aucune connexion à votre base), crée ses propres comptes par les vraies routes de l'API et renvoie le code de sortie 1 en cas d'échec. Il vérifie : authentification et jetons, inscription et connexion, accès aux comptes, abonnements, données de référence, visibilité des recettes, usages normaux, fuites d'informations, CORS, **toutes les routes de l'application** (81) sans jeton, variantes de chemin et de méthode, escalade de rôle par champs en trop, injections SQL, entrées malformées, CSRF, et absence de secret dans les réponses.

À relancer après chaque modification de sécurité. Il peut être branché dans une intégration continue (une ligne : `php tests/security/harness.php`).

## 5. Constats initiaux (référence)

| # | Sévérité | Faille | État |
|---|---|---|---|
| 1 | Critique | Usurpation de compte via `X-User-Id` | Corrigé |
| 2 | Critique | Aucun contrôle d'accès | Corrigé |
| 3 | Moyenne (ancien projet Supabase abandonné) | Secrets dans Git (dont un ancien mot de passe d'une base abandonnée) | Traité : projet Supabase supprimé, `APP_SECRET` local changé (reste la production). Purge de l'historique abandonnée. |
| 4 | Haute | Prise de contrôle de compte | Corrigé |
| 5 | Haute | Abonnements auto-attribuables | Corrigé |
| 6 | Haute | Ingrédients de recette modifiables par tous | Corrigé |
| 7 | Haute | Recettes privées lisibles, e-mail de l'auteur exposé | Corrigé |
| 8 | Haute | `ensureAuthenticated` ouvert par défaut | Corrigé |
| 9 | Haute | Export de données avec jeton par défaut | Corrigé (variable à définir) |
| 10 | Moyenne | CORS contournable (`localhost.evil.com`) | Corrigé |
| 11 | Moyenne | CSRF sans valeur réelle | Non traité (voir « Plus tard ») |
| 12 | Moyenne | Fuites d'informations | Corrigé |
| 13 | Moyenne | Données de référence modifiables par tous | Corrigé |
| 14 | Moyenne | Mots de passe et e-mails faibles, e-mail non unique | Corrigé (migration à lancer) |
| 15 | Moyenne | `APP_ENV=dev` par défaut | **À faire** en production |
| 16 | Basse | Divers | Partiel |
| B | — | 32 avis de dépendances | Corrigé |
| D1 | Critique | Contournement du contrôle d'accès par double slash (`/admin/user-subscribe//`) : liste de tous les abonnements lisible par un utilisateur normal | **Corrigé** (2026-11-18) |
| D2 | Haute | Aucune limitation des tentatives de connexion | **Corrigé** (2026-11-18) : 5 échecs par IP et e-mail, 30 par IP, sur 15 minutes |
| D3 | Moyenne | Aucune limitation des inscriptions ni plafond de taille sur les recettes | Inscriptions **corrigées** (10 par heure et par IP) ; plafond de taille non traité |
| D4 | Basse | Aucun en-tête de sécurité HTTP, version de PHP exposée | Non traité (plateforme ou écouteur) |
| C4 | Basse | Suppression de compte bloquée par les clés étrangères | Non traité |

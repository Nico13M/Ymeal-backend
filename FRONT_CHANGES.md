# Modifications possibles côté front

Document destiné à l'équipe front. Il liste ce que les corrections de sécurité du back (branche `Fix/securite`) changent pour le front, de ce qui est **obligatoire** à ce qui est **recommandé**. La référence complète des routes est dans [docs/openapi.yaml](docs/openapi.yaml) (à ouvrir avec `docs/swagger.html`, voir [COMMANDES.md](COMMANDES.md) section 8).

Origine autorisée en production : `https://frontmeal.osc-fr1.scalingo.io` (en développement, `http://localhost` et `http://127.0.0.1` avec un port optionnel). Toute autre origine est refusée par CORS.

## 1. Obligatoire : sans cela, le front ne fonctionne plus

### 1.1 Authentification : jeton `Bearer` à la place de `X-User-Id`

| Avant | Maintenant |
|---|---|
| `X-User-Id: <id>` sur chaque requête | `Authorization: Bearer <token>` |

- Le jeton est renvoyé par `POST /admin/auth/login` et `POST /admin/auth/register` dans `data.token` (le login renvoie aussi `data.token_type` = `Bearer` et `data.expires_in` = 604800 secondes, soit 7 jours).
- L'en-tête `X-User-Id` est **ignoré** par le back et n'est plus autorisé par CORS : une requête qui l'envoie encore échoue au préflight.
- Une route protégée sans jeton ou avec un jeton invalide renvoie `401 {"error":"Unauthorized"}`.
- Stocker le jeton côté client (mémoire ou stockage du navigateur, au choix de l'équipe). Ne jamais l'écrire dans une URL.

### 1.2 Gérer le 401 : retour à la connexion

Un jeton devient invalide dans trois cas : expiration (7 jours), **changement du mot de passe du compte**, suppression du compte. Dans tous les cas le back répond `401`.

À faire : sur tout `401` d'une route protégée, effacer le jeton et rediriger vers la page de connexion. Il n'y a pas de jeton de rafraîchissement : il faut se reconnecter.

### 1.3 Gérer le 403

Nouveau code. `403 {"error":"Forbidden"}` signifie « connecté, mais pas le droit ». Il apparaît quand :
- on lit, modifie ou supprime le compte, les abonnements ou les régimes d'un autre utilisateur ;
- un utilisateur normal tente d'écrire des **ingrédients, unités, régimes, offres d'abonnement ou abonnements d'utilisateurs** (réservé aux administrateurs) ;
- on modifie ou supprime la recette d'un autre ;
- le jeton CSRF manque ou est invalide (voir 1.4).

Pour une recette privée d'autrui, le back peut répondre `403` ou `404`. Afficher le même message neutre (« recette introuvable ») dans les deux cas.

### 1.4 Jeton CSRF : envoyer le cookie de session

Les écritures (POST, PUT, PATCH, DELETE) exigent l'en-tête `X-CSRF-TOKEN`, sauf `POST /admin/auth/login`, `POST /admin/auth/register` et `POST /admin/recipes/create`.

1. `GET /admin/security/csrf-token` renvoie `{ "csrfToken": "..." }`.
2. Envoyer cette valeur dans `X-CSRF-TOKEN` sur les écritures.

**Important** : le jeton CSRF est lié à une session. Le back pose un cookie `PHPSESSID` (`Secure`, `SameSite=None`) en réponse à `/admin/security/csrf-token`. Le front doit donc envoyer et recevoir les cookies sur **toutes** les requêtes vers le back : `fetch(url, { credentials: 'include' })`, ou `withCredentials: true` avec axios. Sans cela, chaque écriture reçoit `403 Invalid CSRF token`.

Conseil : récupérer un nouveau jeton CSRF au démarrage de l'application et après chaque `403 Invalid CSRF token` (la session peut avoir expiré), puis rejouer la requête une fois.

### 1.5 Inscription et mot de passe

- **12 caractères minimum** (au lieu d'une règle plus faible). Maximum raisonnable : le back refuse les mots de passe démesurés (`400`). Afficher la règle dans le formulaire et la valider avant l'envoi.
- E-mail : validé et **normalisé en minuscules** par le back ; deux comptes ne peuvent plus avoir le même e-mail, y compris avec une casse différente (`400 {"error":"Email already used"}`). Prénom et nom : 45 caractères maximum.
- Le champ `roles` (ou `role`, `is_admin`, `id`) envoyé à l'inscription ou à la modification est ignoré.

### 1.6 Changer son mot de passe ou son e-mail : `current_password`

`PATCH /admin/user/{id}/edit` exige désormais `current_password` (le mot de passe actuel) dès que le corps contient `password` ou un nouvel `email`. Sans lui : refus (`400`, `401` ou `403`). Ajouter un champ « mot de passe actuel » aux formulaires de changement de mot de passe et d'e-mail.

Après un changement de mot de passe réussi, **l'ancien jeton est invalide** : se reconnecter automatiquement avec le nouveau mot de passe, ou renvoyer vers la connexion.

### 1.7 Auteur des recettes et des notes : plus d'e-mail

- `author.email` n'existe plus dans les recettes : le front ne doit plus l'afficher ni s'appuyer dessus. Utiliser `author.name` (et `author.id`).
- Les notes d'une recette (`GET /admin/ratings/recipes/{recipeId}`) identifient l'auteur par son **pseudo**, plus par son e-mail.

### 1.8 Limitation des tentatives : gérer le 429

`POST /admin/auth/login` et `POST /admin/auth/register` renvoient `429 {"error":"Too many attempts, try again later"}` avec l'en-tête `Retry-After` (en secondes) :
- connexion : 5 échecs par 15 minutes pour un même couple adresse IP + e-mail, 30 par adresse IP ;
- inscription : 10 par heure et par adresse IP.

À faire : afficher « Trop de tentatives, réessayez dans N minutes » (lire `Retry-After`, exposé en CORS) et désactiver le bouton pendant ce délai. Pendant un blocage, même un bon mot de passe est refusé. Ne pas relancer automatiquement la requête en boucle.

## 2. Recommandé : changements de comportement à connaître

### 2.1 Visibilité des recettes

- Les listes (`/admin/recipes/index`, `/search`, `/favorites`, `/trending`, `/count`) ne contiennent que les recettes **publiques** et celles de l'utilisateur connecté.
- Le détail d'une recette privée d'un autre utilisateur n'est plus accessible, ni ses notes, ni la mise en favori.
- `GET /admin/recipes/index` ne prend aucun filtre. Les filtres `is_public`, `difficulty`, `dish_type` et `mine=true` n'existent que sur `GET /admin/recipes/count` (nombre de recettes) ; pour chercher, utiliser `/admin/recipes/search`.

### 2.2 Création et modification de recette

- `name` : texte de 255 caractères maximum, obligatoire (un tableau ou un texte vide renvoie `400`).
- `image` : URL en `http://` ou `https://` uniquement (`javascript:`, `data:`, `file:` sont refusés). Vérifier côté front avant l'envoi.
- `slug` et `user_id` envoyés par le client sont ignorés : le slug est généré par le serveur et l'auteur est toujours l'utilisateur connecté.
- Ajouter un ingrédient à une recette (`POST /admin/recipe-ingredients/`) n'est possible que pour l'**auteur** de la recette. L'unité peut être donnée en texte (`unitLabel`) ou par `unit_id`.
- Le texte des recettes est stocké **tel quel** (aucun échappement côté back). Le front doit toujours afficher ces champs comme du texte (jamais en HTML brut, ex. `innerHTML` ou `dangerouslySetInnerHTML`), sinon une recette contenant `<script>` ou `<img onerror=...>` s'exécute dans le navigateur des lecteurs.
- Les URL d'image ne sont ni vérifiées ni téléchargées par le back : le front ne doit pas faire confiance à une URL d'image pour des actions sensibles.

### 2.3 Notes

- `rating` : entier de 1 à 5 (sinon `400`). `comment` : 2000 caractères maximum. Noter est possible uniquement sur une recette visible.
- La route `/admin/ratings/debug` a été supprimée.

### 2.4 Codes d'erreur à prévoir

| Code | Signification | Que faire |
|---|---|---|
| 400 | Donnée invalide ou mal typée (message dans `error`) | Afficher le message ou une erreur de formulaire |
| 401 | Jeton absent, invalide ou expiré | Retour à la connexion (1.2) |
| 403 | Interdit, ou CSRF invalide | Message d'accès refusé, ou nouveau jeton CSRF (1.4) |
| 404 | Introuvable (ou recette privée d'autrui) | Page « introuvable » |
| 409 | Doublon, ou élément encore utilisé | Message explicatif (ex. « cet ingrédient est utilisé par des recettes ») |
| 429 | Trop de tentatives | Attendre `Retry-After` (1.8) |
| 502 | Service IA ou export indisponible | Message « service momentanément indisponible » |

Toutes les erreurs sont du JSON `{ "error": "message" }`. Les messages sont volontairement génériques : ne pas chercher à les analyser, se fier au code HTTP.

- Supprimer un ingrédient, une unité ou un régime encore utilisé renvoie **409** (et non plus une erreur serveur).
- Supprimer son compte renvoie **409** s'il reste des recettes liées (limitation connue du back : prévoir de supprimer ou de transférer ses recettes d'abord, ou d'afficher un message d'aide).
- Plus aucune route ne renvoie de trace technique : ne plus compter dessus pour déboguer en production.

### 2.5 Interface d'administration

Les écritures sur ingrédients, unités, régimes, offres d'abonnement et abonnements d'utilisateurs renvoient `403` pour un compte non administrateur. Si le front affiche ces écrans :
- les masquer pour les non-administrateurs ;
- ajouter un moyen de savoir si le compte est administrateur (le back ne renvoie pas encore ses rôles dans `login`, voir 3.1).

Pour donner le rôle à un compte : `php bin/console app:user:promote email@exemple.fr`.

## 3. À discuter avec le back (non fait)

### 3.1 Indiquer le rôle dans la réponse de connexion

Aujourd'hui `data.user` ne contient pas les rôles ; le front ne peut pas savoir s'il doit afficher les écrans d'administration sans tenter un appel. Ajouter `roles` (ou `isAdmin`) à la réponse de `/admin/auth/login` est une petite modification du back si l'équipe le souhaite.

### 3.2 Retirer le CSRF une fois les jetons Bearer en place

Avec l'authentification par en-tête `Authorization`, le CSRF n'apporte presque plus rien et impose le cookie de session `SameSite=None` décrit en 1.4. L'équipe peut décider de le retirer ensuite : le front n'aurait plus à appeler `/admin/security/csrf-token` ni à envoyer `X-CSRF-TOKEN`.

### 3.3 Limites de taille

Le back n'impose pas encore de plafond sur la taille d'une recette (description, nombre d'étapes, nombre d'ingrédients). Le front peut déjà borner ses formulaires (par exemple description de quelques milliers de caractères, quelques dizaines d'étapes) ; le back ajoutera ensuite les mêmes limites.

## 4. Liste de vérification pour le front

- [ ] Toutes les requêtes envoient `Authorization: Bearer <token>`, plus aucune n'envoie `X-User-Id`.
- [ ] Toutes les requêtes vers le back envoient les cookies (`credentials: 'include'`).
- [ ] Les écritures envoient `X-CSRF-TOKEN` (jeton récupéré via `/admin/security/csrf-token`) ; reprise sur `403 Invalid CSRF token`.
- [ ] `401` : jeton effacé, retour à la connexion.
- [ ] `403`, `404`, `409`, `429` (avec `Retry-After`) : messages adaptés.
- [ ] Formulaire d'inscription : mot de passe de 12 caractères minimum ; prénom et nom de 45 caractères maximum.
- [ ] Formulaire de changement de mot de passe ou d'e-mail : champ « mot de passe actuel » envoyé en `current_password` ; reconnexion après un changement de mot de passe.
- [ ] Plus aucun usage de `author.email` ; pseudo affiché pour les auteurs de notes.
- [ ] Texte des recettes affiché comme du texte, jamais comme du HTML.
- [ ] URL d'image saisies limitées à `http://` et `https://`.
- [ ] Écrans d'administration masqués aux non-administrateurs.
- [ ] Test complet dans un navigateur depuis `https://frontmeal.osc-fr1.scalingo.io` : inscription, connexion, jeton, écriture avec CSRF, expiration du jeton (401), blocage 429.

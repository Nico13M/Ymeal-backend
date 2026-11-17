# Résultats des tests de sécurité

Généré le 2026-11-18 par `php tests/security/harness.php` (branche `Fix/securite`).

**Résultat : 150 contrôles réussis, 0 échec(s).** Même résultat avec `HENV=prod` (configuration de production, debug désactivé).

Trace brute de cette exécution : [dernier-resultat.txt](dernier-resultat.txt).

## Historique des exécutions

| Date | Code testé | Résultat |
|---|---|---|
| 2026-11-17 | Code d'origine, avant corrections (67 contrôles) | 25 OK, **42 échecs** |
| 2026-11-17 | Après les corrections de l'audit | 69 OK, 0 échec |
| 2026-11-18 | + e-mail des auteurs de notes masqué | 70 OK, 0 échec |
| 2026-11-18 | + usages normaux (non-régression) et recherche réparée | 122 OK, 0 échec |
| 2026-11-18 | + toutes les routes, contournements, entrées hostiles, écouteur d'erreurs | **150 OK, 0 échec** |

Les 42 échecs de la première ligne correspondent aux failles de l'audit (usurpation par `X-User-Id`, absence de contrôle d'accès, recettes privées lisibles, etc.).

## Détail de la dernière exécution

### 1. Authentification (11/11)

- ✅ anonyme
- ✅ X-User-Id seul n'authentifie plus
- ✅ jeton bidon
- ✅ jeton falsifié
- ✅ jeton valide
- ✅ HEAD anonyme
- ✅ endpoint CSRF reste public
- ✅ page d'accueil sans chemin interne
- ✅ jeton expiré refusé par le service
- ✅ jeton frais accepté par le service
- ✅ secret trop court refusé

### 2. Inscription / connexion (6/6)

- ✅ mot de passe < 12 refusé
- ✅ e-mail invalide refusé
- ✅ doublon d'e-mail (casse différente) refusé
- ✅ échec de connexion identique (pas d'énumération)
- ✅ connexion OK (e-mail insensible à la casse) + jeton
- ✅ connexion : pas de hash de mot de passe dans la réponse

### 3. Comptes utilisateurs (IDOR / rôles) (13/13)

- ✅ liste des utilisateurs interdite à un simple user
- ✅ liste des utilisateurs autorisée à l'admin
- ✅ lire le compte d'un autre
- ✅ lire son propre compte
- ✅ modifier le compte d'un autre
- ✅ supprimer le compte d'un autre
- ✅ changer son mot de passe sans l'ancien
- ✅ modifier son prénom
- ✅ prendre l'e-mail d'un autre
- ✅ nouveau mot de passe trop court
- ✅ changer son mot de passe avec l'ancien
- ✅ ancien jeton révoqué après changement de mot de passe
- ✅ reconnexion avec le nouveau mot de passe

### 4. Abonnements (8/8)

- ✅ auto-attribution d'un abonnement
- ✅ liste de tous les abonnements
- ✅ l'admin peut attribuer un abonnement
- ✅ lire les abonnements d'un autre
- ✅ lire ses propres abonnements
- ✅ créer une offre
- ✅ modifier le prix d'une offre
- ✅ suppression d'abonnement (route réparée)

### 5. Données de référence (8/8)

- ✅ créer un ingrédient
- ✅ supprimer un ingrédient
- ✅ créer une unité
- ✅ créer un régime
- ✅ régimes d'un autre utilisateur
- ✅ imposer un régime à un autre
- ✅ lecture des ingrédients reste permise
- ✅ l'admin peut créer une unité

### 6. Recettes (18/18)

- ✅ ajouter un ingrédient à la recette d'un autre
- ✅ l'auteur peut ajouter un ingrédient (unité texte)
- ✅ retirer un ingrédient de la recette d'un autre
- ✅ recette privée d'un autre
- ✅ sa propre recette privée
- ✅ recette publique
- ✅ e-mail de l'auteur non exposé
- ✅ liste : pas de recette privée d'un autre
- ✅ compteur ne compte pas les privées d'autrui
- ✅ compteur de l'auteur inclut les siennes
- ✅ mettre en favori une recette privée d'autrui
- ✅ noter une recette privée d'autrui
- ✅ commentaire démesuré refusé
- ✅ noter une recette publique
- ✅ les notes n'exposent pas l'e-mail des auteurs
- ✅ modifier la recette d'un autre
- ✅ image avec schéma javascript: refusée
- ✅ slug imposé par le client ignoré

### 7. Fuites d'information (3/3)

- ✅ route /ratings/debug supprimée
- ✅ /admin/frigo/count fonctionne (bug corrigé)
- ✅ le frigo ignore X-User-Id (reste celui de bob)

### 8. CORS (3/3)

- ✅ origine localhost.evil.com refusée
- ✅ origine du front autorisée
- ✅ X-User-Id n'est plus un en-tête CORS autorisé

### 9. Usages normaux (non-régression) (52/52)

- ✅ GET /admin/reference/diets
- ✅ GET /admin/reference/allergies
- ✅ GET /admin/reference/favorite-cuisines
- ✅ GET /admin/reference/ingredients/search
- ✅ GET /admin/reference/budgets
- ✅ GET /admin/units/index
- ✅ GET /admin/diet/
- ✅ GET /admin/diet/{id}/show
- ✅ GET /admin/subscribe/
- ✅ GET /admin/subscribe/{id}/show
- ✅ GET /admin/ingredients/{id}
- ✅ POST préférences : régimes
- ✅ GET préférences : régimes
- ✅ POST préférences : allergies
- ✅ POST préférences : blacklist
- ✅ POST préférences : cuisines
- ✅ POST préférences : nombre de personnes
- ✅ GET préférences : nombre de personnes
- ✅ GET préférences : budget
- ✅ POST frigo : ajouter un ingrédient
- ✅ GET frigo : lister
- ✅ GET frigo : compter
- ✅ GET frigo : recherche d'ingrédient
- ✅ GET frigo : ingrédients
- ✅ DELETE frigo : retirer
- ✅ GET recettes : favoris (liste)
- ✅ POST favori sur une recette publique
- ✅ GET statut du favori
- ✅ DELETE favori
- ✅ GET recettes : recherche
- ✅ GET recettes : tendances
- ✅ GET recettes : blacklist
- ✅ GET ma note
- ✅ DELETE ma note
- ✅ PATCH sa propre recette
- ✅ DELETE sa propre recette privée
- ✅ admin : PATCH ingrédient
- ✅ admin : PATCH régime
- ✅ admin : associer un régime à un utilisateur
- ✅ admin : retirer ce régime
- ✅ admin : PUT unité
- ✅ admin : PATCH offre
- ✅ admin : attribuer un abonnement
- ✅ admin : PATCH abonnement
- ✅ admin : lire un abonnement par id
- ✅ admin : lire n'importe quel compte
- ✅ admin : modifier un compte sans current_password
- ✅ admin : DELETE ingrédient inutilisé
- ✅ recherche sans liste noire : la recette à 2 ingrédients est trouvée
- ✅ liste noire : la recette « Duo » (ingrédient interdit + un autre) est exclue
- ✅ DELETE son propre compte
- ✅ un compte supprimé ne peut plus utiliser son jeton

### 10. Toutes les routes, contournements et entrées hostiles (28/28)

- ✅ toute route non publique répond 401 sans jeton (81 routes parcourues)
- ✅ aucune route ne répond 500 avec des entrées malformées
- ✅ aucune réponse ne divulgue de trace, de chemin ou de SQL
- ✅ variantes de chemin (casse, //, ../, encodage, point-virgule) : aucune ne contourne l'authentification
- ✅ surcharge de méthode ?_method=DELETE non acceptée
- ✅ le compte admin existe toujours après ces tentatives
- ✅ « bearer » en minuscules accepté
- ✅ Authorization refusé : jeton suivi de texte
- ✅ Authorization refusé : schéma Basic
- ✅ Authorization refusé : jeton vide
- ✅ Authorization refusé : partie en trop
- ✅ Authorization refusé : signature vide
- ✅ Authorization refusé : payload vide
- ✅ Authorization refusé : jeton sans schéma
- ✅ payload remplacé (uid admin) avec l'ancienne signature
- ✅ inscription avec champs en trop (#0) : pas de rôle admin
- ✅ inscription avec champs en trop (#1) : pas de rôle admin
- ✅ inscription avec champs en trop (#2) : pas de rôle admin
- ✅ édition de son compte avec roles/password_hash/id : ignorés
- ✅ alice n'est toujours pas admin
- ✅ injections SQL dans les paramètres : pas d'erreur 500, aucune ligne en trop
- ✅ la table user existe toujours après les tentatives de DROP
- ✅ écriture sans en-tête CSRF
- ✅ écriture avec jeton CSRF bidon
- ✅ donnée mal typée (tableau à la place d'un texte)
- ✅ supprimer un ingrédient encore utilisé
- ✅ la réponse 409 ne contient aucun détail SQL
- ✅ aucune des 1847 réponses ne contient de hash de mot de passe ni de secret

> La section 10 parcourt automatiquement les 81 routes déclarées de l'application.

## Ce que ces tests ne couvrent pas

- PostgreSQL et la migration `Version20261117100000` (les tests tournent sur SQLite en mémoire).
- Les appels réels aux services externes : génération IA, export de données.
- Le front dans un navigateur (jetons `Bearer`, CORS réel).
- La configuration de la plateforme de production (variables d'environnement, `php.ini`, accès réseau à la base).
- La reconnaissance automatique des ingrédients dans un texte IA (fonction PostgreSQL `similarity()`).

## Relancer

```bash
php tests/security/harness.php              # environnement de test
HENV=prod php tests/security/harness.php    # configuration de production
HDEBUG=1 php tests/security/harness.php     # détail des erreurs 500 en cas d'échec
```

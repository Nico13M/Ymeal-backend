<?php
/**
 * Banc de test de sécurité et de non-régression de l'API Ymeal.
 *
 * - Démarre le noyau Symfony dans un environnement isolé, sur SQLite EN MÉMOIRE :
 *   aucune connexion à votre vraie base, rien n'est écrit sur disque (hors cache var/cache/<env>).
 * - Crée ses propres comptes (alice, bob, root) en passant par les vraies routes de l'API.
 *
 * Usage (depuis la racine du projet) :
 *     php tests/security/harness.php                  # environnement « harness » (debug désactivé)
 *     HENV=prod php tests/security/harness.php        # mêmes tests avec la config de production
 *     HDEBUG=1 php tests/security/harness.php         # affiche le détail des erreurs 500 pour déboguer
 *
 * Code de sortie : 0 si tout passe, 1 sinon. Derniers résultats : tests/security/RESULTATS.md
 */

use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Request;
ob_start();

$root = dirname(__DIR__, 2);
$secret = str_repeat('s3cr3t-harness-', 5); // > 32 octets
foreach ([
    'APP_SECRET' => $secret,
    'DATABASE_URL' => 'sqlite:///:memory:',
    'DEFAULT_URI' => 'http://localhost',
    'APP_ENV' => getenv('HENV') ?: 'harness',
    'APP_DEBUG' => '0',
    'APP_RUNTIME_MODE' => 'web=1', // comme un vrai serveur web : pages d'erreur génériques en prod
] as $k => $v) {
    putenv("$k=$v"); $_ENV[$k] = $v; $_SERVER[$k] = $v;
}
require $root . '/vendor/autoload.php';
chdir($root);
(new Symfony\Component\Filesystem\Filesystem())->remove($root . '/var/cache/' . (getenv('HENV') ?: 'harness'));

$kernel = new Kernel(getenv('HENV') ?: 'harness', (bool) getenv('HDEBUG'));
$kernel->boot();
$doctrine = $kernel->getContainer()->get('doctrine');
$em = $doctrine->getManager();
(new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
$em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');

$pass = 0; $fail = 0; $failed = [];
function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail, $failed;
    if ($ok) { $pass++; echo "  [OK]   $name\n"; }
    else { $fail++; $failed[] = $name; echo "  [FAIL] $name" . ($detail ? "  -> $detail" : '') . "\n"; }
}

function call(string $method, string $uri, array $o = []): array {
    global $kernel;
    $GLOBALS['ipn'] = ($GLOBALS['ipn'] ?? 0) + 1; // IP distincte par appel : les limites par IP ne s'accumulent pas entre tests
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTPS' => 'on',
        'REMOTE_ADDR' => $o['ip'] ?? ('10.' . (intdiv($GLOBALS['ipn'], 65000) % 250) . '.' . (intdiv($GLOBALS['ipn'], 250) % 250) . '.' . ($GLOBALS['ipn'] % 250 + 1))];
    if (!empty($o['token'])) $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $o['token'];
    foreach (($o['headers'] ?? []) as $h => $v) $server['HTTP_' . strtoupper(str_replace('-', '_', $h))] = $v;
    $body = isset($o['raw']) ? $o['raw'] : (array_key_exists('body', $o) ? json_encode($o['body']) : null);
    $req = Request::create($uri, $method, [], $o['cookies'] ?? [], [], $server, $body);
    $resp = $kernel->handle($req);
    $kernel->terminate($req, $resp);
    $GLOBALS['bodies'][] = $method . ' ' . $uri . ' => ' . $resp->getContent();
    $json = json_decode($resp->getContent(), true); if (getenv('HDEBUG') && $resp->getStatusCode() >= 500) echo '    !! ' . $method . ' ' . $uri . ' -> ' . substr((string) ($json['detail'] ?? $resp->getContent()), 0, 220) . "
";
    return [$resp->getStatusCode(), is_array($json) ? $json : [], $resp];
}

/** Appel authentifié en tant qu'acteur, avec jeton CSRF (cookie de session conservé). */
function as_(array &$actor, string $method, string $uri, $body = null, array $headers = []): array {
    if (!isset($actor['csrf'])) {
        [, $j, $r] = call('GET', '/admin/security/csrf-token', ['cookies' => $actor['cookies'] ?? []]);
        foreach ($r->headers->getCookies() as $c) $actor['cookies'][$c->getName()] = $c->getValue();
        $actor['csrf'] = $j['csrfToken'] ?? '';
    }
    $o = ['token' => $actor['token'] ?? null, 'cookies' => $actor['cookies'] ?? [],
          'headers' => $headers + ['X-CSRF-TOKEN' => $actor['csrf']]];
    if ($body !== null) $o['body'] = $body;
    return call($method, $uri, $o);
}

$PW = 'CorrectHorse-Battery-1';
function register(string $email, string $pw, string $first = 'T'): array {
    [$st, $j] = call('POST', '/admin/auth/register', ['body' => ['firstname' => $first, 'lastname' => 'User', 'email' => $email, 'password' => $pw]]);
    return [$st, $j];
}

echo "== Préparation ==\n";
$actors = [];
foreach (['alice', 'bob', 'root'] as $n) {
    [$st, $j] = register("$n@example.test", $PW, ucfirst($n));
    $actors[$n] = ['id' => $j['data']['user']['id'] ?? null, 'token' => $j['data']['token'] ?? null, 'cookies' => []];
    echo "  register $n -> $st id=" . ($actors[$n]['id'] ?? '?') . ' token=' . (empty($actors[$n]['token']) ? 'non' : 'oui') . "\n";
}
['alice' => &$alice, 'bob' => &$bob, 'root' => &$root_] = $actors;
try {
    $doctrine->getConnection()->executeStatement('UPDATE "user" SET roles = :r WHERE email = :e', ['r' => '["ROLE_ADMIN"]', 'e' => 'root@example.test']);
} catch (\Throwable $e) { echo "  (roles indisponible: colonne absente)\n"; }

// Gedmo (slug/dates) n'est pas actif hors prod : on insère l'ingrédient de test directement.
$doctrine->getConnection()->insert('ingredient', ['name' => 'Tomate', 'slug' => 'tomate', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
$ingId = (int) $doctrine->getConnection()->lastInsertId();
[$s, $j] = as_($root_, 'POST', '/admin/diet/create', ['name' => 'Vegan']);
$dietId = $j['data']['id'] ?? null;
[$s, $j] = as_($root_, 'POST', '/admin/subscribe/create', ['name' => 'Premium', 'price' => 10, 'duration_months' => 1]);
$subId = $j['id'] ?? null;
echo "  seed admin: ingredient=" . var_export($ingId, true) . " diet=" . var_export($dietId, true) . " sub=" . var_export($subId, true) . "\n";

$mk = fn(bool $pub, string $name) => ['name' => $name, 'description' => 'desc', 'servings' => 2, 'is_public' => $pub,
    'ingredients' => $ingId ? [['ingredient_id' => $ingId, 'quantity' => 2]] : []];
[, $j] = as_($alice, 'POST', '/admin/recipes/create', $mk(true, 'Publique'));
$pubId = $j['recipe']['id'] ?? null;
[, $j] = as_($alice, 'POST', '/admin/recipes/create', $mk(false, 'Privee'));
$privId = $j['recipe']['id'] ?? null;
echo "  seed recettes: publique=" . var_export($pubId, true) . " privée=" . var_export($privId, true) . "\n";

echo "\n== 1. Authentification ==\n";
[$s] = call('GET', '/admin/recipes/index');
check('anonyme -> 401', $s === 401, "status=$s");
[$s] = call('GET', '/admin/recipes/index', ['headers' => ['X-User-Id' => (string) $alice['id']]]);
check('X-User-Id seul n\'authentifie plus -> 401', $s === 401, "status=$s");
[$s] = call('GET', '/admin/recipes/index', ['token' => 'abc.def']);
check('jeton bidon -> 401', $s === 401, "status=$s");
$tampered = $alice['token'] ? (substr($alice['token'], 0, -3) . (substr($alice['token'], -3) === 'AAA' ? 'BBB' : 'AAA')) : 'x';
[$s] = call('GET', '/admin/recipes/index', ['token' => $tampered]);
check('jeton falsifié -> 401', $s === 401, "status=$s");
[$s] = call('GET', '/admin/recipes/index', ['token' => $alice['token']]);
check('jeton valide -> 200', $s === 200, "status=$s");
[$s] = call('HEAD', '/admin/recipes/index');
check('HEAD anonyme -> 401 (pas de contournement)', $s === 401, "status=$s");
[$s] = call('GET', '/admin/security/csrf-token');
check('endpoint CSRF reste public', $s === 200, "status=$s");
[$s, $j] = call('GET', '/');
check('page d\'accueil sans chemin interne', $s === 200 && !isset($j['path']), "status=$s");

if (class_exists(\App\Security\ApiTokenService::class)) {
    $svc = new \App\Security\ApiTokenService($secret);
    $u = $em->getRepository(\App\Entity\User::class)->find($alice['id']);
    $old = $svc->issue($u, time() - 20 * 86400);
    check('jeton expiré refusé par le service', $svc->verify($old) === null);
    check('jeton frais accepté par le service', $svc->verify($svc->issue($u)) !== null);
    try { new \App\Security\ApiTokenService('court'); check('secret trop court refusé', false); }
    catch (\Throwable $e) { check('secret trop court refusé', true); }
} else { check('ApiTokenService présent', false, 'classe absente'); }

echo "\n== 2. Inscription / connexion ==\n";
[$s] = register('weak@example.test', 'court');
check('mot de passe < 12 refusé', $s === 400, "status=$s");
[$s] = register('pas-un-email', $PW);
check('e-mail invalide refusé', $s === 400, "status=$s");
[$s] = register('ALICE@example.test', $PW);
check('doublon d\'e-mail (casse différente) refusé', $s === 400, "status=$s");
[$s1, $j1] = call('POST', '/admin/auth/login', ['body' => ['email' => 'alice@example.test', 'password' => 'mauvais']]);
[$s2, $j2] = call('POST', '/admin/auth/login', ['body' => ['email' => 'inconnu@example.test', 'password' => 'mauvais']]);
check('échec de connexion identique (pas d\'énumération)', $s1 === 401 && $s2 === 401 && ($j1['error'] ?? 1) === ($j2['error'] ?? 2), json_encode([$j1, $j2]));
[$s, $j] = call('POST', '/admin/auth/login', ['body' => ['email' => 'Alice@Example.test', 'password' => $PW]]);
check('connexion OK (e-mail insensible à la casse) + jeton', $s === 200 && !empty($j['data']['token']), "status=$s");
check('connexion : pas de hash de mot de passe dans la réponse', !str_contains(json_encode($j), 'password'));

echo "\n== 3. Comptes utilisateurs (IDOR / rôles) ==\n";
[$s] = as_($bob, 'GET', '/admin/user/');
check('liste des utilisateurs interdite à un simple user', $s === 403, "status=$s");
[$s] = as_($root_, 'GET', '/admin/user/');
check('liste des utilisateurs autorisée à l\'admin', $s === 200, "status=$s");
[$s] = as_($bob, 'GET', "/admin/user/{$alice['id']}");
check('lire le compte d\'un autre -> 403', $s === 403, "status=$s");
[$s] = as_($alice, 'GET', "/admin/user/{$alice['id']}");
check('lire son propre compte -> 200', $s === 200, "status=$s");
[$s] = as_($bob, 'PATCH', "/admin/user/{$alice['id']}/edit", ['email' => 'pirate@example.test', 'password' => 'PirateTakeover-123']);
check('modifier le compte d\'un autre -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'DELETE', "/admin/user/{$alice['id']}/delete");
check('supprimer le compte d\'un autre -> 403', $s === 403, "status=$s");
[$s] = as_($alice, 'PATCH', "/admin/user/{$alice['id']}/edit", ['password' => 'NouveauMotDePasse-99']);
check('changer son mot de passe sans l\'ancien -> refusé', in_array($s, [400, 403], true), "status=$s");
[$s] = as_($alice, 'PATCH', "/admin/user/{$alice['id']}/edit", ['firstname' => 'Alicia']);
check('modifier son prénom -> 200', $s === 200, "status=$s");
[$s] = as_($alice, 'PATCH', "/admin/user/{$alice['id']}/edit", ['email' => 'bob@example.test', 'current_password' => $PW]);
check('prendre l\'e-mail d\'un autre -> refusé (unicité)', in_array($s, [400, 422], true), "status=$s");
[$s] = as_($alice, 'PATCH', "/admin/user/{$alice['id']}/edit", ['password' => 'court', 'current_password' => $PW]);
check('nouveau mot de passe trop court -> refusé', in_array($s, [400, 422], true), "status=$s");
[$s] = as_($alice, 'PATCH', "/admin/user/{$alice['id']}/edit", ['password' => 'NouveauMotDePasse-99', 'current_password' => $PW]);
check('changer son mot de passe avec l\'ancien -> 200', $s === 200, "status=$s");
[$s] = call('GET', '/admin/recipes/index', ['token' => $alice['token']]);
check('ancien jeton révoqué après changement de mot de passe', $s === 401, "status=$s");
[$s, $j] = call('POST', '/admin/auth/login', ['body' => ['email' => 'alice@example.test', 'password' => 'NouveauMotDePasse-99']]);
$alice['token'] = $j['data']['token'] ?? null; $alice['csrf'] = null; $alice['cookies'] = [];
check('reconnexion avec le nouveau mot de passe', $s === 200 && $alice['token'], "status=$s");

echo "\n== 4. Abonnements ==\n";
[$s] = as_($bob, 'POST', "/admin/user-subscribe/{$bob['id']}/$subId/create", ['start_date' => '2026-01-01', 'end_date' => '2099-01-01']);
check('auto-attribution d\'un abonnement -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'GET', '/admin/user-subscribe/');
check('liste de tous les abonnements -> 403', $s === 403, "status=$s");
[$s] = as_($root_, 'POST', "/admin/user-subscribe/{$bob['id']}/$subId/create", ['start_date' => '2026-01-01', 'end_date' => '2027-01-01']);
check('l\'admin peut attribuer un abonnement', $s === 201, "status=$s");
[$s] = as_($alice, 'GET', "/admin/user-subscribe/{$bob['id']}/user/subscriptions");
check('lire les abonnements d\'un autre -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'GET', "/admin/user-subscribe/{$bob['id']}/user/subscriptions");
check('lire ses propres abonnements -> 200', $s === 200, "status=$s");
[$s] = as_($bob, 'POST', '/admin/subscribe/create', ['name' => 'Gratuit', 'price' => 1, 'duration_months' => 999]);
check('créer une offre -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'PATCH', "/admin/subscribe/$subId/edit", ['price' => 0]);
check('modifier le prix d\'une offre -> 403', $s === 403, "status=$s");
[$s] = as_($root_, 'DELETE', "/admin/user-subscribe/{$bob['id']}/$subId/delete");
check('suppression d\'abonnement (route réparée) -> 204', $s === 204, "status=$s");

echo "\n== 5. Données de référence ==\n";
[$s] = as_($bob, 'POST', '/admin/ingredients/create', ['name' => 'Pirate']);
check('créer un ingrédient -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'DELETE', "/admin/ingredients/$ingId/delete");
check('supprimer un ingrédient -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'POST', '/admin/units/create', ['name' => 'x']);
check('créer une unité -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'POST', '/admin/diet/create', ['name' => 'x']);
check('créer un régime -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'GET', "/admin/diet/user/{$alice['id']}/diets");
check('régimes d\'un autre utilisateur -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'POST', "/admin/diet/$dietId/associate/{$alice['id']}");
check('imposer un régime à un autre -> 403', $s === 403, "status=$s");
[$s] = as_($bob, 'GET', '/admin/ingredients/');
check('lecture des ingrédients reste permise -> 200', $s === 200, "status=$s");
[$s] = as_($root_, 'POST', '/admin/units/create', ['name' => 'gramme', 'symbol' => 'g']);
check('l\'admin peut créer une unité', $s === 201, "status=$s");

echo "\n== 6. Recettes ==\n";
[$s] = as_($bob, 'POST', '/admin/recipe-ingredients/', ['recipe_id' => $pubId, 'ingredient_id' => $ingId, 'quantity' => 9]);
check('ajouter un ingrédient à la recette d\'un autre -> 403', $s === 403, "status=$s");
[$s, $j] = as_($alice, 'POST', '/admin/recipe-ingredients/', ['recipe_id' => $pubId, 'ingredient_id' => $ingId, 'quantity' => 3, 'unit' => 'g']);
check('l\'auteur peut ajouter un ingrédient (unité texte)', $s === 201, "status=$s " . json_encode($j));
$riId = $j['id'] ?? 0;
[$s] = as_($bob, 'DELETE', "/admin/recipe-ingredients/$riId/delete");
check('retirer un ingrédient de la recette d\'un autre -> 403', $s === 403, "status=$s");
[$s, $j] = as_($bob, 'GET', "/admin/recipes/$privId");
check('recette privée d\'un autre -> 404', $s === 404, "status=$s");
[$s, $j] = as_($alice, 'GET', "/admin/recipes/$privId");
check('sa propre recette privée -> 200', $s === 200, "status=$s");
[$s, $j] = as_($bob, 'GET', "/admin/recipes/$pubId");
check('recette publique -> 200', $s === 200, "status=$s");
check('e-mail de l\'auteur non exposé', !str_contains(json_encode($j), 'alice@example.test') && !str_contains(json_encode($j), '@'), '');
[$s, $j] = as_($bob, 'GET', '/admin/recipes/index');
$names = array_column($j, 'name');
check('liste : pas de recette privée d\'un autre', $s === 200 && !in_array('Privee', $names, true) && in_array('Publique', $names, true), json_encode($names));
[$s, $j] = as_($bob, 'GET', '/admin/recipes/count');
check('compteur ne compte pas les privées d\'autrui', $s === 200 && ($j['count'] ?? -1) === 1, json_encode($j));
[$s, $j] = as_($alice, 'GET', '/admin/recipes/count');
check('compteur de l\'auteur inclut les siennes', $s === 200 && ($j['count'] ?? -1) === 2, json_encode($j));
[$s] = as_($bob, 'POST', "/admin/recipes/$privId/favorite");
check('mettre en favori une recette privée d\'autrui -> 404', $s === 404, "status=$s");
[$s] = as_($bob, 'POST', "/admin/ratings/create-or-update/$privId", ['rating' => 5]);
check('noter une recette privée d\'autrui -> 404', $s === 404, "status=$s");
[$s] = as_($bob, 'POST', "/admin/ratings/create-or-update/$pubId", ['rating' => 5, 'comment' => str_repeat('a', 5000)]);
check('commentaire démesuré refusé', $s === 400, "status=$s");
[$s] = as_($bob, 'POST', "/admin/ratings/create-or-update/$pubId", ['rating' => 5, 'comment' => 'super']);
check('noter une recette publique -> 201', $s === 201, "status=$s");
[$s, $j] = as_($alice, 'GET', "/admin/ratings/recipes/$pubId");
check("les notes n'exposent pas l'e-mail des auteurs", $s === 200 && !str_contains(json_encode($j), '@example.test'), json_encode($j));
[$s] = as_($bob, 'PATCH', "/admin/recipes/$pubId/edit", ['name' => 'piraté']);
check('modifier la recette d\'un autre -> 403', $s === 403, "status=$s");
[$s] = as_($alice, 'PATCH', "/admin/recipes/$pubId/edit", ['image' => 'javascript:alert(1)']);
check('image avec schéma javascript: refusée', $s === 400, "status=$s");
[$s, $j] = as_($alice, 'POST', '/admin/recipes/create', $mk(true, 'Slug') + ['slug' => 'slug-impose']);
check('slug imposé par le client ignoré', $s === 201 && ($j['recipe']['slug'] ?? '') !== 'slug-impose', json_encode($j['recipe']['slug'] ?? null));

echo "\n== 7. Fuites d'information ==\n";
[$s] = as_($bob, 'POST', '/admin/ratings/debug', ['x' => 1]);
check('route /ratings/debug supprimée', in_array($s, [404, 405], true), "status=$s");
[$s, $j] = as_($bob, 'GET', "/admin/frigo/count");
check('/admin/frigo/count fonctionne (bug corrigé)', $s === 200, "status=$s");
[$s] = as_($bob, 'GET', '/admin/frigo/', null, ['X-User-Id' => (string) $alice['id']]);
$fr = as_($bob, 'GET', '/admin/frigo/');
check('le frigo ignore X-User-Id (reste celui de bob)', $s === 200, "status=$s");

echo "\n== 8. CORS ==\n";
[, , $r] = call('OPTIONS', '/admin/recipes/index', ['headers' => ['Origin' => 'http://localhost.evil.com', 'Access-Control-Request-Method' => 'GET']]);
check('origine localhost.evil.com refusée', !$r->headers->has('Access-Control-Allow-Origin'), (string) $r->headers->get('Access-Control-Allow-Origin'));
[, , $r] = call('OPTIONS', '/admin/recipes/index', ['headers' => ['Origin' => 'https://frontmeal.osc-fr1.scalingo.io', 'Access-Control-Request-Method' => 'GET']]);
check('origine du front autorisée', $r->headers->get('Access-Control-Allow-Origin') === 'https://frontmeal.osc-fr1.scalingo.io');
[, , $r] = call('OPTIONS', '/admin/recipes/index', ['headers' => ['Origin' => 'http://localhost:8081', 'Access-Control-Request-Method' => 'GET']]);
check('origine du front local autorisée', $r->headers->get('Access-Control-Allow-Origin') === 'http://localhost:8081');
[, , $r] = call('OPTIONS', '/admin/recipes/index', ['headers' => ['Origin' => 'https://frontmeal.osc-fr1.scalingo.io', 'Access-Control-Request-Method' => 'GET', 'Access-Control-Request-Headers' => 'x-user-id']]);
check('X-User-Id n\'est plus un en-tête CORS autorisé', !str_contains(strtolower((string) $r->headers->get('Access-Control-Allow-Headers')), 'x-user-id'));


echo "\n== 9. Usages normaux (non-régression) ==\n";
$ok = fn(string $name, array $r, array $codes = [200, 201, 204]) => check($name, in_array($r[0], $codes, true), 'status=' . $r[0] . ' ' . substr(json_encode($r[1]), 0, 120));

// Référentiel (lecture, utilisateur normal)
$ok('GET /admin/reference/diets', as_($bob, 'GET', '/admin/reference/diets'));
$ok('GET /admin/reference/allergies', as_($bob, 'GET', '/admin/reference/allergies'));
$ok('GET /admin/reference/favorite-cuisines', as_($bob, 'GET', '/admin/reference/favorite-cuisines'));
$ok('GET /admin/reference/ingredients/search', as_($bob, 'GET', '/admin/reference/ingredients/search?q=tom'));
$ok('GET /admin/reference/budgets', as_($bob, 'GET', '/admin/reference/budgets'));
$ok('GET /admin/units/index', as_($bob, 'GET', '/admin/units/index'));
$ok('GET /admin/diet/', as_($bob, 'GET', '/admin/diet/'));
$ok('GET /admin/diet/{id}/show', as_($bob, 'GET', "/admin/diet/$dietId/show"));
$ok('GET /admin/subscribe/', as_($bob, 'GET', '/admin/subscribe/'));
$ok('GET /admin/subscribe/{id}/show', as_($bob, 'GET', "/admin/subscribe/$subId/show"));
$ok('GET /admin/ingredients/{id}', as_($bob, 'GET', "/admin/ingredients/$ingId"));

// Préférences de l'utilisateur
$ok('POST préférences : régimes', as_($bob, 'POST', '/admin/user/preferences/diets', ['diet_ids' => [$dietId]]));
$ok('GET préférences : régimes', as_($bob, 'GET', '/admin/user/preferences/diets'));
$ok('POST préférences : allergies', as_($bob, 'POST', '/admin/user/preferences/allergies', ['allergy_ids' => []]));
$ok('POST préférences : blacklist', as_($bob, 'POST', '/admin/user/preferences/blacklist', ['ingredient_ids' => []]));
$ok('POST préférences : cuisines', as_($bob, 'POST', '/admin/user/preferences/favorite-cuisines', ['cuisine_ids' => []]));
$ok('POST préférences : nombre de personnes', as_($bob, 'POST', '/admin/user/preferences/person-count', ['count' => 3]));
$ok('GET préférences : nombre de personnes', as_($bob, 'GET', '/admin/user/preferences/person-count'));
$ok('GET préférences : budget', as_($bob, 'GET', '/admin/user/preferences/budget'));

// Frigo
$ok('POST frigo : ajouter un ingrédient', as_($bob, 'POST', "/admin/frigo/$ingId", ['quantity' => 2]));
$ok('GET frigo : lister', as_($bob, 'GET', '/admin/frigo/'));
$ok('GET frigo : compter', as_($bob, 'GET', '/admin/frigo/count'));
$ok('GET frigo : recherche d\'ingrédient', as_($bob, 'GET', '/admin/frigo/ingredients/search?query=tom'));
$ok('GET frigo : ingrédients', as_($bob, 'GET', '/admin/frigo/ingredients'));
$ok('DELETE frigo : retirer', as_($bob, 'DELETE', "/admin/frigo/$ingId"));

// Recettes : lecture, favoris, recherche
$ok('GET recettes : favoris (liste)', as_($bob, 'GET', '/admin/recipes/favorites'));
$ok('POST favori sur une recette publique', as_($bob, 'POST', "/admin/recipes/$pubId/favorite"));
$ok('GET statut du favori', as_($bob, 'GET', "/admin/recipes/$pubId/favorite/status"));
$ok('DELETE favori', as_($bob, 'DELETE', "/admin/recipes/$pubId/favorite"));
$ok('GET recettes : recherche', as_($bob, 'GET', '/admin/recipes/search'));
$ok('GET recettes : tendances', as_($bob, 'GET', '/admin/recipes/trending'));
$ok('GET recettes : blacklist', as_($bob, 'GET', '/admin/recipes/user/blacklist'));

// Notes
$ok('GET ma note', as_($bob, 'GET', "/admin/ratings/recipes/$pubId/me"));
$ok('DELETE ma note', as_($bob, 'DELETE', "/admin/ratings/delete/$pubId"));

// Recettes de l'auteur
$ok('PATCH sa propre recette', as_($alice, 'PATCH', "/admin/recipes/$pubId/edit", ['name' => 'Renommée', 'image' => 'https://exemple.fr/a.png']));
$ok('DELETE sa propre recette privée', as_($alice, 'DELETE', "/admin/recipes/$privId/delete"));

// Administration (admin)
$ok('admin : PATCH ingrédient', as_($root_, 'PATCH', "/admin/ingredients/$ingId/edit", ['name' => 'Tomate cerise']));
$ok('admin : PATCH régime', as_($root_, 'PATCH', "/admin/diet/$dietId/edit", ['name' => 'Végétalien']));
$ok('admin : associer un régime à un utilisateur', as_($root_, 'POST', "/admin/diet/$dietId/associate/{$alice['id']}"));
$ok('admin : retirer ce régime', as_($root_, 'DELETE', "/admin/diet/$dietId/associate/{$alice['id']}/delete"));
$ok('admin : PUT unité', as_($root_, 'PUT', '/admin/units/1/edit', ['name' => 'grammes']));
$ok('admin : PATCH offre', as_($root_, 'PATCH', "/admin/subscribe/$subId/edit", ['price' => 12]));
$ok('admin : attribuer un abonnement', as_($root_, 'POST', "/admin/user-subscribe/{$alice['id']}/$subId/create", ['start_date' => '2026-01-01', 'end_date' => '2027-01-01']));
$ok('admin : PATCH abonnement', as_($root_, 'PATCH', "/admin/user-subscribe/{$alice['id']}/$subId/edit", ['is_active' => false]));
$ok('admin : lire un abonnement par id', as_($root_, 'GET', '/admin/user-subscribe/2/show'));
$ok('admin : lire n\'importe quel compte', as_($root_, 'GET', "/admin/user/{$bob['id']}"));
$ok('admin : modifier un compte sans current_password', as_($root_, 'PATCH', "/admin/user/{$bob['id']}/edit", ['firstname' => 'Robert']));
$doctrine->getConnection()->insert('ingredient', ['name' => 'Temporaire', 'slug' => 'temporaire', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
$ok('admin : DELETE ingrédient inutilisé', as_($root_, 'DELETE', '/admin/ingredients/' . (int) $doctrine->getConnection()->lastInsertId() . '/delete'));

// Recherche : la liste noire doit exclure toute recette contenant un ingrédient interdit
$doctrine->getConnection()->insert('ingredient', ['name' => 'Salade', 'slug' => 'salade', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
$saladeId = (int) $doctrine->getConnection()->lastInsertId();
[, $dj] = as_($alice, 'POST', '/admin/recipes/create', ['name' => 'Duo', 'description' => 'd', 'servings' => 2, 'is_public' => true,
    'ingredients' => [['ingredient_id' => $ingId, 'quantity' => 1], ['ingredient_id' => $saladeId, 'quantity' => 1]]]);
$duoId = $dj['recipe']['id'] ?? null;
[$s, $j] = as_($alice, 'GET', '/admin/recipes/search');
$names = array_column($j['recipes'] ?? [], 'name');
check('recherche sans liste noire : la recette à 2 ingrédients est trouvée', $s === 200 && in_array('Duo', $names, true), "status=$s " . json_encode($names));
as_($alice, 'POST', '/admin/user/preferences/blacklist', ['ingredient_ids' => [$ingId]]);
[$s, $j] = as_($alice, 'GET', '/admin/recipes/search');
$names = array_column($j['recipes'] ?? [], 'name');
check('liste noire : la recette « Duo » (ingrédient interdit + un autre) est exclue', $s === 200 && !in_array('Duo', $names, true) && in_array('Salade', array_map(fn() => 'Salade', [1])), "status=$s " . json_encode($names));

// Un utilisateur peut supprimer son propre compte
$ok('DELETE son propre compte', as_($bob, 'DELETE', "/admin/user/{$bob['id']}/delete"));
[$s] = call('GET', '/admin/recipes/index', ['token' => $bob['token']]);
check('un compte supprimé ne peut plus utiliser son jeton', $s === 401, "status=$s");


echo "\n== 10. Toutes les routes, contournements et entrées hostiles ==\n";

// ---- 10.1 Chaque route de l'application : 401 sans jeton (sauf routes publiques) ----
$router = $kernel->getContainer()->get('router');
$publicPaths = ['/', '/admin/auth/login', '/admin/auth/register', '/admin/security/csrf-token'];
$routes = [];
foreach ($router->getRouteCollection()->all() as $name => $route) {
    if (str_starts_with($name, '_')) continue; // profiler, wdt, logout interne
    $methods = $route->getMethods() ?: ['GET'];
    $path = preg_replace_callback('/\{(\w+)\}/', fn($m) => '1', $route->getPath());
    $routes[] = [$name, $methods[0], $path, $route->getPath()];
}
echo '  ' . count($routes) . " routes trouvées\n";
$open = [];
foreach ($routes as [$name, $method, $path, $tpl]) {
    if (in_array($tpl, $publicPaths, true)) continue;
    [$st] = call($method, $path, ['raw' => '{}']);
    if ($st !== 401) $open[] = "$method $tpl => $st";
}
check('toute route non publique répond 401 sans jeton (' . count($routes) . ' routes parcourues)', $open === [], implode(' | ', $open));

// ---- 10.2 Aucune route ne plante (500) ni ne divulgue de détails, avec des entrées hostiles ----
$rootTok = $root_['token']; $aliceTok = $alice['token'];
$garbage = ['{"a":', '[]', 'null', '"texte"', '{"id":{"$ne":1},"name":["x"],"quantity":"abc","rating":"x","count":"x","ids":"x"}'];
$bad500 = []; $leaks = [];
foreach ($routes as [$name, $method, $path, $tpl]) {
    if (str_starts_with($tpl, '/admin/auth') || $tpl === '/admin/user/{id}/delete' || str_contains($tpl, 'user/{id}/delete')) continue;
    foreach (['alice' => $alice, 'root' => $root_] as $who => $actor) {
        foreach ($garbage as $g) {
            $actorRef = $actor;
            $r = as_($actorRef, $method, str_replace('/1', '/999999', $path), null);
            $o = ['token' => $actor['token'], 'cookies' => $actorRef['cookies'] ?? [], 'raw' => $g,
                  'headers' => ['X-CSRF-TOKEN' => $actorRef['csrf'] ?? '']];
            [$st, , $resp] = call($method, $path, $o);
            $body = $resp->getContent();
            if ($st === 500) { $bad500["$method $tpl ($who)"] = $st; $GLOBALS['sample500'] ??= ['uri' => "$method $path", 'ct' => $resp->headers->get('Content-Type'), 'body' => preg_replace('/\[[0-9;]*m/', '', substr($body, 0, 500))]; }
            if (preg_match('#(Stack trace|/vendor/|src/Controller|SQLSTATE|Doctrine\\\\|Symfony\\\\|\.php on line)#', $body)) $leaks["$method $tpl"] = substr($body, 0, 90);
        }
    }
}
if ($bad500) echo '    exemple : ' . json_encode($GLOBALS['sample500'], JSON_UNESCAPED_UNICODE) . "
";
check('aucune route ne répond 500 avec des entrées malformées', $bad500 === [], implode(' | ', array_keys($bad500)));
check('aucune réponse ne divulgue de trace, de chemin ou de SQL', $leaks === [], json_encode(array_slice($leaks, 0, 5)));

// ---- 10.3 Variantes de chemin et de méthode sans jeton ----
$variants = ['//admin/user/', '/ADMIN/user/', '/admin/user', '/index.php/admin/user/', '/admin/auth/../user/', '/admin/./user/',
             '/%61dmin/user/', '/admin/user/?_method=DELETE', '/admin/user/%2e', '/admin/user/..%2f', '/admin/user/;x'];
$passed = [];
foreach ($variants as $v) {
    foreach (['GET', 'HEAD', 'OPTIONS'] as $m) {
        [$st, $j] = call($m, $v);
        if ($st === 200 && $m !== 'OPTIONS') $passed[] = "$m $v";
        if (str_contains(json_encode($j), '@example.test')) $passed[] = "FUITE $m $v";
    }
}
check('variantes de chemin (casse, //, ../, encodage, point-virgule) : aucune ne contourne l\'authentification', $passed === [], implode(' | ', $passed));
[$st] = as_($alice, 'POST', "/admin/user/{$root_['id']}/delete?_method=DELETE");
check('surcharge de méthode ?_method=DELETE non acceptée', in_array($st, [404, 405], true), "status=$st");
[$st] = as_($root_, 'GET', "/admin/user/{$root_['id']}");
check('le compte admin existe toujours après ces tentatives', $st === 200, "status=$st");

// ---- 10.4 En-tête Authorization : formes limites ----
$t = $alice['token'];
[$st] = call('GET', '/admin/recipes/index', ['headers' => ['Authorization' => 'bearer ' . $t]]);
check('« bearer » en minuscules accepté', $st === 200, "status=$st");
foreach (['Bearer ' . $t . ' x' => 'jeton suivi de texte', 'Basic ' . $t => 'schéma Basic', 'Bearer ' => 'jeton vide',
          'Bearer ' . $t . '.' => 'partie en trop', 'Bearer ' . explode('.', $t)[0] . '.' => 'signature vide',
          'Bearer .' . (explode('.', $t)[1] ?? '') => 'payload vide', $t => 'jeton sans schéma'] as $h => $label) {
    [$st] = call('GET', '/admin/recipes/index', ['headers' => ['Authorization' => $h]]);
    check("Authorization refusé : $label", $st === 401, "status=$st");
}
// Payload modifié (autre utilisateur) avec l'ancienne signature
[$p, $sig] = explode('.', $t);
$forged = rtrim(strtr(base64_encode(json_encode(['uid' => (int) $root_['id'], 'exp' => time() + 99999, 'pwd' => 'x'])), '+/', '-_'), '=') . '.' . $sig;
[$st] = call('GET', '/admin/user/', ['token' => $forged]);
check('payload remplacé (uid admin) avec l\'ancienne signature -> refusé', $st === 401, "status=$st");

// ---- 10.5 Élévation de privilèges par injection de champs ----
foreach ([['roles' => ['ROLE_ADMIN']], ['role' => 'ROLE_ADMIN'], ['id' => 999, 'password_hash' => 'x', 'is_admin' => true]] as $i => $extra) {
    register("escalade$i@example.test", $PW, 'Esc');  // l'appel register de base ignore les champs en trop
    [$st, $j] = call('POST', '/admin/auth/register', ['body' => $extra + ['firstname' => 'E', 'lastname' => 'E', 'email' => "escalade-b$i@example.test", 'password' => $PW]]);
    $roles = $doctrine->getConnection()->fetchOne('SELECT roles FROM "user" WHERE email = ?', ["escalade-b$i@example.test"]);
    check("inscription avec champs en trop (#$i) : pas de rôle admin", $st === 201 && !str_contains((string) $roles, 'ADMIN'), "roles=$roles");
}
[$st] = as_($alice, 'PATCH', "/admin/user/{$alice['id']}/edit", ['roles' => ['ROLE_ADMIN'], 'password_hash' => 'x', 'id' => 777]);
$roles = $doctrine->getConnection()->fetchOne('SELECT roles FROM "user" WHERE id = ?', [$alice['id']]);
$hash = $doctrine->getConnection()->fetchOne('SELECT password_hash FROM "user" WHERE id = ?', [$alice['id']]);
check('édition de son compte avec roles/password_hash/id : ignorés', !str_contains((string) $roles, 'ADMIN') && $hash !== 'x', "roles=$roles");
[$st] = as_($alice, 'GET', '/admin/user/');
check('alice n\'est toujours pas admin', $st === 403, "status=$st");

// ---- 10.6 Injection SQL dans les paramètres de recherche ----
$bad = ["' OR '1'='1", "%' OR 1=1 --", "'; DROP TABLE \"user\"; --", '" OR ""="'];
$sqli = [];
foreach ($bad as $q) {
    foreach (['/admin/frigo/ingredients/search?query=', '/admin/reference/ingredients/search?q=', '/admin/recipes/search?difficulty=', '/admin/recipes/count?dish_type=', '/admin/recipes/count?difficulty='] as $u) {
        [$st, $j] = as_($alice, 'GET', $u . rawurlencode($q));
        if ($st >= 500) $sqli[] = "$u => $st";
        elseif (str_contains($u, 'search?q') || str_contains($u, 'ingredients/search')) { if ($j !== []) $sqli[] = "$u renvoie des lignes : " . substr(json_encode($j), 0, 60); }
    }
}
[$st, $j] = as_($alice, 'GET', '/admin/recipes/search?ingredientsForm=' . rawurlencode('1,2 OR 1=1,abc,"x"'));
if ($st >= 500) $sqli[] = "ingredientsForm csv => $st";
[$st, $j] = as_($alice, 'GET', '/admin/recipes/search?ingredientsForm=' . rawurlencode('[{"id":"1 OR 1=1"},"x",null,[1]]'));
if ($st >= 500) $sqli[] = "ingredientsForm json => $st";
$tbl = $doctrine->getConnection()->fetchOne('SELECT count(*) FROM "user"');
check('injections SQL dans les paramètres : pas d\'erreur 500, aucune ligne en trop', $sqli === [], implode(' | ', $sqli));
check('la table user existe toujours après les tentatives de DROP', (int) $tbl > 0, "nb=$tbl");

// ---- 10.7 CSRF conservé sur les écritures ----
[$st, $j] = call('POST', '/admin/units/create', ['token' => $root_['token'], 'body' => ['name' => 'sans-csrf']]);
check('écriture sans en-tête CSRF -> 403', $st === 403 && str_contains(json_encode($j), 'CSRF'), "status=$st");
[$st] = call('POST', '/admin/units/create', ['token' => $root_['token'], 'headers' => ['X-CSRF-TOKEN' => 'bidon'], 'body' => ['name' => 'csrf-bidon']]);
check('écriture avec jeton CSRF bidon -> 403', $st === 403, "status=$st");

// ---- 10.7b Erreurs propres (400 / 409) au lieu de 500 ----
[$st, $j] = as_($root_, 'POST', '/admin/diet/create', ['name' => ['pas', 'un', 'texte']]);
check('donnée mal typée (tableau à la place d\'un texte) -> 400 JSON', $st === 400 && ($j['error'] ?? '') === 'Invalid request data', "status=$st " . json_encode($j));
[$st, $j] = as_($root_, 'DELETE', "/admin/ingredients/$ingId/delete");
check('supprimer un ingrédient encore utilisé -> 409 JSON (et pas 500)', $st === 409, "status=$st " . json_encode($j));
check('la réponse 409 ne contient aucun détail SQL', !preg_match('/SQLSTATE|constraint|foreign key/i', json_encode($j)), json_encode($j));

// ---- 10.7c Limitation des tentatives (anti force brute) ----
$IPX = '203.0.113.50'; $IPY = '203.0.113.51';
register('rl-a@example.test', $PW, 'RlA'); register('rl-b@example.test', $PW, 'RlB'); // comptes dédiés (alice et bob ont changé de mot de passe plus haut)
$lg = fn(string $email, string $pw, string $ip) => call('POST', '/admin/auth/login', ['ip' => $ip, 'body' => ['email' => $email, 'password' => $pw]]);
$codes = []; for ($i = 0; $i < 7; $i++) { [$st] = $lg('rl-a@example.test', "faux-$i", $IPX); $codes[] = $st; }
check('connexion : 5 échecs puis 429 (même IP et même e-mail)', array_slice($codes, 0, 5) === [401,401,401,401,401] && $codes[5] === 429 && $codes[6] === 429, implode(',', $codes));
[$st, $j, $r] = $lg('rl-a@example.test', $PW, $IPX);
check('connexion : bon mot de passe aussi bloqué pendant la fenêtre (pas de contournement)', $st === 429, "status=$st");
check('429 : en-tête Retry-After présent et positif', (int) $r->headers->get('Retry-After') > 0, (string) $r->headers->get('Retry-After'));
check('429 : réponse JSON générique', ($j['error'] ?? '') === 'Too many attempts, try again later', json_encode($j));
[$st] = $lg('rl-a@example.test', $PW, $IPY);
check('connexion : une autre IP n\'est pas bloquée par l\'échec d\'une IP (pas de blocage global du compte)', $st === 200, "status=$st");
$codes = []; for ($i = 0; $i < 4; $i++) { [$st] = $lg('rl-b@example.test', "faux-$i", '203.0.113.52'); $codes[] = $st; }
[$stOk] = $lg('rl-b@example.test', $PW, '203.0.113.52');
$codes2 = []; for ($i = 0; $i < 4; $i++) { [$st] = $lg('rl-b@example.test', "faux-$i", '203.0.113.52'); $codes2[] = $st; }
check('connexion réussie : le compteur repart de zéro (4 échecs, succès, 4 échecs sans 429)', !in_array(429, array_merge($codes, [$stOk], $codes2), true) && $stOk === 200, implode(',', $codes) . " ok=$stOk " . implode(',', $codes2));
$codes = []; for ($i = 0; $i < 33; $i++) { [$st] = $lg("inconnu$i@example.test", 'x', '203.0.113.53'); $codes[] = $st; }
check('connexion : limite par IP (30 e-mails différents, puis 429)', $codes[29] === 401 && $codes[30] === 429, 'codes 30..33: ' . implode(',', array_slice($codes, 29)));
$codes = []; for ($i = 0; $i < 12; $i++) { [$st] = call('POST', '/admin/auth/register', ['ip' => '203.0.113.54', 'body' => ['firstname' => 'R', 'lastname' => 'R', 'email' => "rate$i@example.test", 'password' => $PW]]); $codes[] = $st; }
check('inscription : 10 par heure et par IP, puis 429', count(array_filter($codes, fn($c) => $c === 201)) === 10 && $codes[10] === 429 && $codes[11] === 429, implode(',', $codes));
[$st] = call('POST', '/admin/auth/register', ['ip' => '203.0.113.55', 'body' => ['firstname' => 'R', 'lastname' => 'R', 'email' => 'rate-autre-ip@example.test', 'password' => $PW]]);
check('inscription : une autre IP n\'est pas bloquée', $st === 201, "status=$st");
[$st] = $lg('', '', '203.0.113.56'); [$st2] = $lg(str_repeat('a', 5000) . '@x.test', 'x', '203.0.113.56');
check('connexion : e-mail vide ou énorme -> pas de 500', $st < 500 && $st2 < 500, "$st/$st2");

// ---- 10.8 Aucune réponse ne contient de secret ----
$all = implode("\n", $GLOBALS['bodies'] ?? []);
$found = [];
foreach (['$argon2' => '\$argon2', 'hash bcrypt' => '\$2y\$', 'password_hash' => 'password_hash', 'APP_SECRET' => preg_quote($secret, '/'), 'clé HMAC' => 'ymeal-api-token'] as $label => $re) {
    if (preg_match('/' . $re . '/', $all)) $found[] = $label;
}
check('aucune des ' . count($GLOBALS['bodies'] ?? []) . ' réponses ne contient de hash de mot de passe ni de secret', $found === [], implode(', ', $found));

echo "\n== Résultat : $pass OK, $fail échec(s) ==\n";
foreach ($failed as $f) echo "  - $f\n";
exit($fail ? 1 : 0);

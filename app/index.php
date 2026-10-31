<?php

/**
 * MiniDesk - Point d'entrée de l'API
 *
 * Routeur minimaliste. Toutes les requêtes HTTP arrivent ici via nginx
 * (ou Apache, voir .htaccess).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/TicketRepository.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/** Taille maximale du corps JSON (512 Kio) : une description de 65 535 octets tient largement, même échappée en \uXXXX. */
const MAX_BODY_BYTES = 524288;

function read_json_body(): array
{
    // On lit un octet de plus que la limite pour détecter un dépassement sans charger tout le corps.
    $body = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
    if (strlen($body) > MAX_BODY_BYTES) {
        json_response(['error' => 'Corps de requête trop volumineux (maximum 512 Kio).'], 413);
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        json_response(['error' => 'Corps JSON invalide ou manquant.'], 400);
    }
    return $data;
}

/**
 * PATCH d'un champ de ticket typé par un enum (statut, priorité).
 *
 * Réponses : 422 si le champ est absent ou invalide, 404 si le ticket n'existe
 * pas, 200 avec le ticket mis à jour sinon. Le ticket est vérifié avant la
 * valeur : un ticket inconnu répond toujours 404.
 *
 * @param class-string<BackedEnum>        $enum   Enum qui porte les valeurs (méthode statique values())
 * @param callable(int, BackedEnum): bool $update Mise à jour dans le repository
 */
function patch_enum_field(
    TicketRepository $repo,
    int $id,
    string $field,
    string $enum,
    string $label,
    callable $update
): never {
    $data = read_json_body();

    if (empty($data[$field])) {
        json_response(['error' => "Le champ \"$field\" est obligatoire."], 422);
    }

    if (!$repo->findById($id)) {
        json_response(['error' => 'Ticket introuvable.'], 404);
    }

    $value = is_string($data[$field]) ? $enum::tryFrom($data[$field]) : null;
    if ($value === null) {
        json_response([
            'error' => "$label invalide. Valeurs acceptées : " . implode(', ', $enum::values()) . '.',
        ], 422);
    }

    if (!$update($id, $value)) {
        json_response(['error' => 'Ticket introuvable.'], 404);
    }

    json_response($repo->findById($id));
}

/** GET /openapi : spécification générée par `make openapi` (voir app/ApiDoc.php). */
function serve_openapi(): never
{
    $file = __DIR__ . '/openapi.json';
    if (!is_file($file)) {
        json_response(['error' => 'Documentation indisponible.'], 404);
    }

    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

/**
 * GET /docs : interface Swagger UI. Les scripts viennent de cdnjs (version
 * figée, contrôlée par SRI) ; la CSP n'autorise que cet hôte et le script
 * d'initialisation, signé par un nonce.
 */
function serve_docs(): never
{
    $cdn   = 'https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.17.14';
    $nonce = base64_encode(random_bytes(16));

    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    header(
        "Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'none'; "
        . "script-src 'nonce-$nonce' https://cdnjs.cloudflare.com; "
        . "style-src 'unsafe-inline' https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: https://cdnjs.cloudflare.com; "
        . "font-src https://cdnjs.cloudflare.com; connect-src 'self'"
    );
    header('Referrer-Policy: no-referrer');

    echo <<<HTML
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>MiniDesk API - Documentation</title>
        <link rel="stylesheet" href="$cdn/swagger-ui.css"
              integrity="sha384-wxLW6kwyHktdDGr6Pv1zgm/VGJh99lfUbzSn6HNHBENZlCN7W602k9VkGdxuFvPn" crossorigin="anonymous">
    </head>
    <body>
        <div id="swagger-ui"></div>
        <script src="$cdn/swagger-ui-bundle.js"
                integrity="sha384-wmyclcVGX/WhUkdkATwhaK1X1JtiNrr2EoYJ+diV3vj4v6OC5yCeSu+yW13SYJep" crossorigin="anonymous"></script>
        <script nonce="$nonce">
            SwaggerUIBundle({ url: 'openapi', dom_id: '#swagger-ui', deepLinking: true, tryItOutEnabled: true });
        </script>
    </body>
    </html>
    HTML;
    exit;
}

function log_throwable(Throwable $t): void
{
    error_log($t->getMessage() . PHP_EOL . $t->getFile() . ':' . $t->getLine() . PHP_EOL . $t->getTraceAsString());
}

// -----------------------------------------------------------------------
// APP init
// -----------------------------------------------------------------------

// Filet de sécurité : toute exception non rattrapée pendant le routage (erreur
// PDO, TypeError...) est journalisée puis renvoyée en 500 JSON, sans détail
// technique côté client.
set_exception_handler(static function (Throwable $t): void {
    log_throwable($t);
    json_response(['error' => 'Erreur interne du serveur'], 500);
});

// Documentation : servie sans accès à la base, elle reste lisible si la base est en panne.
if ($uri === '/docs' || $uri === '/openapi') {
    if ($method !== 'GET') {
        header('Allow: GET');
        json_response(['error' => 'Méthode non autorisée.'], 405);
    }
    $uri === '/docs' ? serve_docs() : serve_openapi();
}

try {
    $repo = new TicketRepository();
} catch (Throwable $t) {
    log_throwable($t);
    json_response(['error' => 'Service momentanément indisponible'], 503);
}

// -----------------------------------------------------------------------
// Routing
// -----------------------------------------------------------------------

// GET /
if ($method === 'GET' && $uri === '/') {
    json_response([
        'service' => 'MiniDesk API',
        'version' => '1.4.0',
        'status'  => 'ok',
    ]);
}

// GET /tickets
if ($method === 'GET' && $uri === '/tickets') {
    try {
        $query = TicketQuery::fromArray($_GET);
    } catch (ValidationException $e) {
        json_response(['error' => $e->getMessage(), 'details' => $e->getErrors()], 422);
    }

    header('X-Total-Count: ' . $repo->countAll($query));
    json_response($repo->findAll($query));
}

// POST /tickets
if ($method === 'POST' && $uri === '/tickets') {
    $data = read_json_body();

    try {
        $id = $repo->create($data);
    } catch (ValidationException $e) {
        json_response(['error' => $e->getMessage(), 'details' => $e->getErrors()], 422);
    }

    $ticket = $repo->findById($id);
    json_response($ticket, 201);
}

// GET /tickets/{id}
if ($method === 'GET' && preg_match('#^/tickets/(\d+)$#', $uri, $m)) {
    $ticket = $repo->findById((int) $m[1]);
    if (!$ticket) {
        json_response(['error' => 'Ticket introuvable.'], 404);
    }
    json_response($ticket);
}

// PATCH /tickets/{id}/status
if ($method === 'PATCH' && preg_match('#^/tickets/(\d+)/status$#', $uri, $m)) {
    patch_enum_field($repo, (int) $m[1], 'status', TicketStatus::class, 'Statut', $repo->updateStatus(...));
}

// PATCH /tickets/{id}/priority
if ($method === 'PATCH' && preg_match('#^/tickets/(\d+)/priority$#', $uri, $m)) {
    patch_enum_field($repo, (int) $m[1], 'priority', TicketPriority::class, 'Priorité', $repo->updatePriority(...));
}

// 405 : la route existe, mais pas avec cette méthode HTTP
$allowed = match (true) {
    $uri === '/'                                                   => ['GET'],
    $uri === '/tickets'                                            => ['GET', 'POST'],
    (bool) preg_match('#^/tickets/\d+$#', $uri)                    => ['GET'],
    (bool) preg_match('#^/tickets/\d+/(status|priority)$#', $uri)  => ['PATCH'],
    default                                                        => null,
};
if ($allowed !== null) {
    header('Allow: ' . implode(', ', $allowed));
    json_response(['error' => 'Méthode non autorisée.'], 405);
}

// 404 par défaut
json_response(['error' => 'Route introuvable.'], 404);

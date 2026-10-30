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
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function read_json_body(): array
{
    $body = file_get_contents('php://input');
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
        'version' => '1.2.3',
        'status'  => 'ok',
    ]);
}

// GET /tickets
if ($method === 'GET' && $uri === '/tickets') {
    $tickets = $repo->findAll();
    json_response($tickets);
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

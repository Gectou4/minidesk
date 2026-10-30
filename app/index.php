<?php

/**
 * MiniDesk - Point d'entrée de l'API
 *
 * Routeur minimaliste. Toutes les requêtes HTTP arrivent ici via nginx.
 *
 * NOTE POUR LE CANDIDAT :
 *   Ce fichier contient au moins un bug à identifier et corriger.
 *   Lisez-le attentivement avant d'ajouter quoi que ce soit.
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

function json_response(mixed $data, int $status = 200): void
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
        'version' => '1.2.1',
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
    $data = read_json_body();

    if (empty($data['status'])) {
        json_response(['error' => 'Le champ "status" est obligatoire.'], 422);
    }

    $ticket = $repo->findById((int) $m[1]);
    if (!$ticket) {
        json_response(['error' => 'Ticket introuvable.'], 404);
    }

    $status = is_string($data['status']) ? TicketStatus::tryFrom($data['status']) : null;
    if ($status === null) {
        json_response([
            'error' => 'Statut invalide. Valeurs acceptées : ' . implode(', ', TicketStatus::values()) . '.',
        ], 422);
    }

    if (!$repo->updateStatus((int) $m[1], $status)) {
        json_response(['error' => 'Ticket introuvable.'], 404);
    }

    $ticket = $repo->findById((int) $m[1]);
    json_response($ticket);
}

// PATCH /tickets/{id}/priority
if ($method === 'PATCH' && preg_match('#^/tickets/(\d+)/priority$#', $uri, $m)) {
    $data = read_json_body();

    if (empty($data['priority'])) {
        json_response(['error' => 'Le champ "priority" est obligatoire.'], 422);
    }

    $ticket = $repo->findById((int) $m[1]);
    if (!$ticket) {
        json_response(['error' => 'Ticket introuvable.'], 404);
    }

    $priority = is_string($data['priority']) ? TicketPriority::tryFrom($data['priority']) : null;
    if ($priority === null) {
        json_response([
            'error' => 'Priorité invalide. Valeurs acceptées : ' . implode(', ', TicketPriority::values()) . '.',
        ], 422);
    }

    if (!$repo->updatePriority((int) $m[1], $priority)) {
        json_response(['error' => 'Ticket introuvable.'], 404);
    }

    $ticket = $repo->findById((int) $m[1]);
    json_response($ticket);
}

// 404 par défaut
json_response(['error' => 'Route introuvable.'], 404);

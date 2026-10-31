<?php

use OpenApi\Attributes as OA;

/**
 * MiniDesk - Documentation OpenAPI
 *
 * Ce fichier ne contient aucune logique : ses attributs décrivent l'API pour
 * swagger-php, qui génère app/api-spec.json (servi par GET /openapi et affiché
 * par GET /docs). Les attributs sont ignorés à l'exécution, la production n'a
 * donc pas besoin de vendor/.
 *
 * Une méthode par route de app/index.php (voir l'operationId). Les schémas des
 * enums sont déduits de TicketStatus et TicketPriority.
 *
 * Après toute modification de l'API ou de cette doc : make openapi. Un test
 * échoue si app/api-spec.json n'est plus à jour.
 */
#[OA\Info(
    title: 'MiniDesk API',
    version: '1.4.0',
    description: "API REST de gestion de tickets de support.\n\n"
        . "Toutes les réponses, erreurs comprises, sont en JSON (UTF-8). Une méthode HTTP non supportée "
        . "sur une route existante répond `405` avec l'en-tête `Allow`, une route inconnue `404`. "
        . "Une erreur serveur répond `500` (`Erreur interne du serveur`) et une base de données "
        . "injoignable `503` (`Service momentanément indisponible`), sans détail technique."
)]
#[OA\Server(url: '/', description: 'Serveur courant')]
#[OA\Tag(name: 'Service', description: "État de l'API")]
#[OA\Tag(name: 'Tickets', description: 'Création, consultation et mise à jour des tickets')]
final class ApiDoc
{
    #[OA\Get(
        path: '/',
        operationId: 'getStatus',
        summary: 'État du service',
        description: "Renvoie le nom et la version de l'API. Sert aussi de contrôle de santé : la réponse suppose que la base de données est joignable.",
        tags: ['Service'],
        responses: [
            new OA\Response(
                response: 200,
                description: "L'API fonctionne.",
                content: new OA\JsonContent(
                    required: ['service', 'version', 'status'],
                    properties: [
                        new OA\Property(property: 'service', type: 'string', example: 'MiniDesk API'),
                        new OA\Property(property: 'version', type: 'string', example: '1.4.0'),
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                    ]
                )
            ),
            new OA\Response(response: 'default', ref: '#/components/responses/ServerError'),
        ]
    )]
    public function getStatus(): void
    {
    }

    #[OA\Get(
        path: '/tickets',
        operationId: 'listTickets',
        summary: 'Lister les tickets',
        description: "Renvoie les tickets du plus récent au plus ancien, avec filtres et pagination facultatifs. "
            . "L'en-tête `X-Total-Count` donne le nombre total de tickets correspondant aux filtres, "
            . "indépendamment de `limit` et `offset`.",
        tags: ['Tickets'],
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Ne garder que les tickets de ce statut.',
                schema: new OA\Schema(ref: '#/components/schemas/TicketStatus')
            ),
            new OA\Parameter(
                name: 'priority',
                in: 'query',
                required: false,
                description: 'Ne garder que les tickets de cette priorité.',
                schema: new OA\Schema(ref: '#/components/schemas/TicketPriority')
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre maximal de tickets renvoyés. Sans `limit`, tous les tickets sont renvoyés.',
                schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1, example: 20)
            ),
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                required: false,
                description: 'Nombre de tickets à ignorer en début de liste.',
                schema: new OA\Schema(type: 'integer', default: 0, minimum: 0, example: 0)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des tickets (tableau vide si aucun ne correspond).',
                headers: [
                    new OA\Header(
                        header: 'X-Total-Count',
                        description: 'Nombre total de tickets correspondant aux filtres.',
                        schema: new OA\Schema(type: 'integer', example: 42)
                    ),
                ],
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Ticket'))
            ),
            new OA\Response(
                response: 422,
                description: 'Paramètre de requête invalide. Toutes les erreurs sont listées dans `details`.',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
            new OA\Response(response: 'default', ref: '#/components/responses/ServerError'),
        ]
    )]
    public function listTickets(): void
    {
    }

    #[OA\Post(
        path: '/tickets',
        operationId: 'createTicket',
        summary: 'Créer un ticket',
        description: 'Crée un ticket au statut `open`. La priorité vaut `medium` si elle est absente. '
            . 'Les erreurs de validation sont cumulées dans `details`.',
        tags: ['Tickets'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/TicketInput',
                example: ['title' => 'Imprimante en panne', 'description' => 'Le bac 2 est bloqué.', 'priority' => 'high']
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Ticket créé.',
                content: new OA\JsonContent(ref: '#/components/schemas/Ticket')
            ),
            new OA\Response(response: 400, ref: '#/components/responses/InvalidJson'),
            new OA\Response(response: 413, ref: '#/components/responses/BodyTooLarge'),
            new OA\Response(
                response: 422,
                description: 'Données invalides.',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
            new OA\Response(response: 'default', ref: '#/components/responses/ServerError'),
        ]
    )]
    public function createTicket(): void
    {
    }

    #[OA\Get(
        path: '/tickets/{id}',
        operationId: 'getTicket',
        summary: 'Consulter un ticket',
        tags: ['Tickets'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/TicketId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Le ticket.',
                content: new OA\JsonContent(ref: '#/components/schemas/Ticket')
            ),
            new OA\Response(response: 404, ref: '#/components/responses/TicketNotFound'),
            new OA\Response(response: 'default', ref: '#/components/responses/ServerError'),
        ]
    )]
    public function getTicket(): void
    {
    }

    #[OA\Patch(
        path: '/tickets/{id}/status',
        operationId: 'updateTicketStatus',
        summary: "Changer le statut d'un ticket",
        description: 'Un ticket inconnu répond toujours `404`, avant la validation du statut.',
        tags: ['Tickets'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/TicketId')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StatusInput', example: ['status' => 'in_progress'])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Le ticket mis à jour.',
                content: new OA\JsonContent(ref: '#/components/schemas/Ticket')
            ),
            new OA\Response(response: 400, ref: '#/components/responses/InvalidJson'),
            new OA\Response(response: 404, ref: '#/components/responses/TicketNotFound'),
            new OA\Response(response: 413, ref: '#/components/responses/BodyTooLarge'),
            new OA\Response(
                response: 422,
                description: 'Statut absent ou invalide.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/Error',
                    example: ['error' => 'Statut invalide. Valeurs acceptées : open, in_progress, closed.']
                )
            ),
            new OA\Response(response: 'default', ref: '#/components/responses/ServerError'),
        ]
    )]
    public function updateTicketStatus(): void
    {
    }

    #[OA\Patch(
        path: '/tickets/{id}/priority',
        operationId: 'updateTicketPriority',
        summary: "Changer la priorité d'un ticket",
        description: 'Un ticket inconnu répond toujours `404`, avant la validation de la priorité.',
        tags: ['Tickets'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/TicketId')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PriorityInput', example: ['priority' => 'critical'])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Le ticket mis à jour.',
                content: new OA\JsonContent(ref: '#/components/schemas/Ticket')
            ),
            new OA\Response(response: 400, ref: '#/components/responses/InvalidJson'),
            new OA\Response(response: 404, ref: '#/components/responses/TicketNotFound'),
            new OA\Response(response: 413, ref: '#/components/responses/BodyTooLarge'),
            new OA\Response(
                response: 422,
                description: 'Priorité absente ou invalide.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/Error',
                    example: ['error' => 'Priorité invalide. Valeurs acceptées : low, medium, high, critical.']
                )
            ),
            new OA\Response(response: 'default', ref: '#/components/responses/ServerError'),
        ]
    )]
    public function updateTicketPriority(): void
    {
    }
}

// ---------------------------------------------------------------------------
// Composants réutilisés par les routes ci-dessus
// ---------------------------------------------------------------------------

#[OA\Schema(
    schema: 'Ticket',
    required: ['id', 'title', 'description', 'priority', 'status', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Serveur SMTP hors ligne'),
        new OA\Property(
            property: 'description',
            type: 'string',
            maxLength: 65535,
            description: "Texte libre, chaîne vide si absent à la création (65 535 octets maximum).",
            example: 'Les emails transactionnels ne partent plus depuis 14h.'
        ),
        new OA\Property(property: 'priority', ref: '#/components/schemas/TicketPriority'),
        new OA\Property(property: 'status', ref: '#/components/schemas/TicketStatus'),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            description: 'Date de création (heure du serveur, AAAA-MM-JJ HH:MM:SS).',
            example: '2026-10-30 19:06:47'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            description: 'Date de dernière modification (heure du serveur, AAAA-MM-JJ HH:MM:SS).',
            example: '2026-10-30 20:08:54'
        ),
    ]
)]
final class DocTicket
{
}

#[OA\Schema(
    schema: 'TicketInput',
    required: ['title'],
    properties: [
        new OA\Property(
            property: 'title',
            type: 'string',
            maxLength: 255,
            minLength: 1,
            description: 'Titre, obligatoire (255 caractères maximum).',
            example: 'Imprimante en panne'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            maxLength: 65535,
            nullable: true,
            description: 'Description libre (65 535 octets maximum). Absente ou `null` : chaîne vide.',
            example: 'Le bac 2 est bloqué.'
        ),
        new OA\Property(
            property: 'priority',
            nullable: true,
            description: 'Priorité. Absente ou `null` : `medium`.',
            ref: '#/components/schemas/TicketPriority'
        ),
    ]
)]
final class DocTicketInput
{
}

#[OA\Schema(
    schema: 'StatusInput',
    required: ['status'],
    properties: [new OA\Property(property: 'status', ref: '#/components/schemas/TicketStatus')]
)]
final class DocStatusInput
{
}

#[OA\Schema(
    schema: 'PriorityInput',
    required: ['priority'],
    properties: [new OA\Property(property: 'priority', ref: '#/components/schemas/TicketPriority')]
)]
final class DocPriorityInput
{
}

#[OA\Schema(
    schema: 'Error',
    required: ['error'],
    properties: [new OA\Property(property: 'error', type: 'string', example: 'Ticket introuvable.')]
)]
final class DocError
{
}

#[OA\Schema(
    schema: 'ValidationError',
    required: ['error', 'details'],
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Données invalides.'),
        new OA\Property(
            property: 'details',
            type: 'object',
            description: 'Une entrée par champ invalide : nom du champ, puis message.',
            additionalProperties: new OA\AdditionalProperties(type: 'string'),
            example: ['title' => 'Le champ "title" est obligatoire.']
        ),
    ]
)]
final class DocValidationError
{
}

#[OA\Parameter(
    parameter: 'TicketId',
    name: 'id',
    in: 'path',
    required: true,
    description: 'Identifiant du ticket.',
    schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
)]
final class DocTicketId
{
}

#[OA\Response(
    response: 'TicketNotFound',
    description: 'Ticket introuvable.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final class DocTicketNotFound
{
}

#[OA\Response(
    response: 'InvalidJson',
    description: 'Corps absent ou JSON invalide.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final class DocInvalidJson
{
}

#[OA\Response(
    response: 'BodyTooLarge',
    description: 'Corps de la requête supérieur à 512 Kio.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final class DocBodyTooLarge
{
}

#[OA\Response(
    response: 'ServerError',
    description: 'Erreur serveur : `500` (erreur interne) ou `503` (base de données injoignable).',
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final class DocServerError
{
}

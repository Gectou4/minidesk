<?php

/**
 * MiniDesk - TicketStatus
 *
 * Statuts possibles d'un ticket. Doit rester aligné sur l'ENUM de la colonne
 * `tickets.status` dans db/schema.sql.
 *
 * Le schéma OpenAPI (valeurs comprises) est déduit de l'enum : l'attribut est
 * ignoré à l'exécution, swagger-php ne le lit qu'à la génération de la doc.
 */
#[\OpenApi\Attributes\Schema(
    schema: 'TicketStatus',
    type: 'string',
    enum: TicketStatus::class,
    description: "Statut d'un ticket : `open` (ouvert), `in_progress` (en cours), `closed` (fermé). Un ticket est créé `open`.",
    example: 'open'
)]
enum TicketStatus: string
{
    case Open       = 'open';
    case InProgress = 'in_progress';
    case Closed     = 'closed';

    /**
     * @return string[] Les valeurs acceptées, dans l'ordre de déclaration.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

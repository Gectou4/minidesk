<?php

/**
 * MiniDesk - TicketPriority
 *
 * Priorités possibles d'un ticket. Doit rester aligné sur l'ENUM de la colonne
 * `tickets.priority` dans db/schema.sql.
 *
 * Le schéma OpenAPI (valeurs comprises) est déduit de l'enum : l'attribut est
 * ignoré à l'exécution, swagger-php ne le lit qu'à la génération de la doc.
 */
#[\OpenApi\Attributes\Schema(
    schema: 'TicketPriority',
    type: 'string',
    enum: TicketPriority::class,
    description: "Priorité d'un ticket, de la moins à la plus urgente : `low`, `medium`, `high`, `critical`. Par défaut `medium`.",
    example: 'medium'
)]
enum TicketPriority: string
{
    case Low      = 'low';
    case Medium   = 'medium';
    case High     = 'high';
    case Critical = 'critical';

    /**
     * @return string[] Les valeurs acceptées, dans l'ordre de déclaration.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

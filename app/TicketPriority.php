<?php

/**
 * MiniDesk - TicketPriority
 *
 * Priorités possibles d'un ticket. Doit rester aligné sur l'ENUM de la colonne
 * `tickets.priority` dans db/schema.sql.
 */
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

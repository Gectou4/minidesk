<?php

/**
 * MiniDesk - TicketStatus
 *
 * Statuts possibles d'un ticket. Doit rester aligné sur l'ENUM de la colonne
 * `tickets.status` dans db/schema.sql.
 */
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

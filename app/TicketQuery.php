<?php

require_once __DIR__ . '/Exceptions/ValidationException.php';
require_once __DIR__ . '/TicketPriority.php';
require_once __DIR__ . '/TicketStatus.php';

/**
 * MiniDesk - TicketQuery
 *
 * Filtres et pagination de GET /tickets. Objet immuable : fromArray() le
 * construit depuis la query string et valide chaque paramètre.
 *
 * Paramètres : status, priority (valeurs des enums), limit (1 à MAX_LIMIT),
 * offset (>= 0). Sans limit, toute la liste est renvoyée.
 */
final class TicketQuery
{
    public const MAX_LIMIT = 100;

    public function __construct(
        public readonly ?TicketStatus $status = null,
        public readonly ?TicketPriority $priority = null,
        public readonly ?int $limit = null,
        public readonly int $offset = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $query Paramètres de la query string ($_GET)
     * @throws ValidationException si un paramètre est invalide
     */
    public static function fromArray(array $query): self
    {
        $errors = [];

        $status = null;
        if (array_key_exists('status', $query)) {
            $status = is_string($query['status']) ? TicketStatus::tryFrom($query['status']) : null;
            if ($status === null) {
                $errors['status'] = 'Statut invalide. Valeurs acceptées : ' . implode(', ', TicketStatus::values()) . '.';
            }
        }

        $priority = null;
        if (array_key_exists('priority', $query)) {
            $priority = is_string($query['priority']) ? TicketPriority::tryFrom($query['priority']) : null;
            if ($priority === null) {
                $errors['priority'] = 'Priorité invalide. Valeurs acceptées : ' . implode(', ', TicketPriority::values()) . '.';
            }
        }

        $limit = null;
        if (array_key_exists('limit', $query)) {
            $limit = self::toInt($query['limit'], 1, self::MAX_LIMIT);
            if ($limit === null) {
                $errors['limit'] = 'Le paramètre "limit" doit être un entier entre 1 et ' . self::MAX_LIMIT . '.';
            }
        }

        $offset = 0;
        if (array_key_exists('offset', $query)) {
            $offset = self::toInt($query['offset'], 0, PHP_INT_MAX);
            if ($offset === null) {
                $errors['offset'] = 'Le paramètre "offset" doit être un entier positif ou nul.';
                $offset = 0;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($status, $priority, $limit, $offset);
    }

    /**
     * Entier décimal strict (pas de signe, d'espace ni de notation décimale),
     * limité à 18 chiffres pour éviter tout dépassement.
     */
    private static function toInt(mixed $value, int $min, int $max): ?int
    {
        if (!is_string($value) || preg_match('/^[0-9]{1,18}$/', $value) !== 1) {
            return null;
        }

        $int = (int) $value;

        return ($int >= $min && $int <= $max) ? $int : null;
    }
}

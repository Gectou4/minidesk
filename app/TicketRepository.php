<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Exceptions/ValidationException.php';
require_once __DIR__ . '/TicketPriority.php';
require_once __DIR__ . '/TicketStatus.php';

/**
 * MiniDesk - TicketRepository
 *
 * Classe responsable de tous les accès aux données de la table `tickets`.
 * Le statut et la priorité sont typés par les enums TicketStatus et
 * TicketPriority ; les données de création sont validées dans create().
 */
class TicketRepository
{
    /** Taille de la colonne `tickets.title` (VARCHAR(255)), en caractères. */
    private const TITLE_MAX_LENGTH = 255;

    /** Taille maximale de la colonne `tickets.description` (TEXT), en octets. */
    private const DESCRIPTION_MAX_BYTES = 65535;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? get_db();
    }

    /**
     * Retourne tous les tickets, triés du plus récent au plus ancien.
     */
    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM tickets ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    /**
     * Retourne un ticket par son identifiant, ou null s'il n'existe pas.
     *
     * Requête préparée : l'id est transmis comme paramètre lié, jamais
     * concaténé dans le SQL.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM tickets WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row  = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crée un nouveau ticket et retourne son identifiant.
     *
     * La priorité vaut `medium` si elle est absente (ou null) ; une valeur
     * hors de TicketPriority est refusée. La description vaut '' si elle est
     * absente (ou null). Les longueurs respectent le schéma SQL : `title` est
     * limité à 255 caractères (VARCHAR) et `description` à 65 535 octets (TEXT).
     *
     * @param array{title: string, description: string, priority: string} $data
     * @throws ValidationException si `title`, `description` ou `priority` est invalide
     */
    public function create(array $data): int
    {
        $errors = [];

        $title = $data['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            $errors['title'] = 'Le champ "title" est obligatoire.';
        } elseif (mb_strlen(trim($title)) > self::TITLE_MAX_LENGTH) {
            $errors['title'] = 'Le champ "title" ne doit pas dépasser ' . self::TITLE_MAX_LENGTH . ' caractères.';
        }

        $description = $data['description'] ?? '';
        if (!is_string($description)) {
            $errors['description'] = 'Le champ "description" doit être une chaîne de caractères.';
        } elseif (strlen($description) > self::DESCRIPTION_MAX_BYTES) {
            $errors['description'] = 'Le champ "description" ne doit pas dépasser ' . self::DESCRIPTION_MAX_BYTES . ' octets.';
        }

        $priority = TicketPriority::Medium;
        if (($data['priority'] ?? null) !== null) {
            $priority = is_string($data['priority']) ? TicketPriority::tryFrom($data['priority']) : null;
            if ($priority === null) {
                $errors['priority'] = 'Priorité invalide. Valeurs acceptées : ' . implode(', ', TicketPriority::values()) . '.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO tickets (title, description, priority)
             VALUES (:title, :description, :priority)'
        );
        $stmt->execute([
            ':title'       => trim($title),
            ':description' => $description,
            ':priority'    => $priority->value,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour le statut d'un ticket.
     *
     * Contraintes :
     *   - Utiliser une requête préparée (jamais d'interpolation directe)
     *   - Le statut est typé par l'enum TicketStatus : une valeur invalide
     *     ne peut pas arriver jusqu'ici (voir TicketStatus::tryFrom()).
     *   - Retourner true si le ticket existe et a été mis à jour,
     *     false s'il n'existe pas.
     *
     * @param int          $id     Identifiant du ticket
     * @param TicketStatus $status Nouveau statut
     */
    public function updateStatus(int $id, TicketStatus $status): bool
    {
        $stmt = $this->db->prepare('UPDATE tickets SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status->value, ':id' => $id]);

        // MySQL compte 0 ligne si le statut est déjà celui demandé : on vérifie l'existence.
        return $stmt->rowCount() > 0 || $this->findById($id) !== null;
    }

    /**
     * Met à jour la priorité d'un ticket.
     *
     * Comme updateStatus() : la priorité est typée par l'enum TicketPriority,
     * donc une valeur invalide ne peut pas arriver jusqu'ici.
     *
     * @param int            $id       Identifiant du ticket
     * @param TicketPriority $priority Nouvelle priorité
     * @return bool true si le ticket existe et a été mis à jour, false sinon
     */
    public function updatePriority(int $id, TicketPriority $priority): bool
    {
        $stmt = $this->db->prepare('UPDATE tickets SET priority = :priority WHERE id = :id');
        $stmt->execute([':priority' => $priority->value, ':id' => $id]);

        // MySQL compte 0 ligne si la priorité est déjà celle demandée : on vérifie l'existence.
        return $stmt->rowCount() > 0 || $this->findById($id) !== null;
    }
}

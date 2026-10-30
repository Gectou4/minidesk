<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Exceptions/ValidationException.php';
require_once __DIR__ . '/TicketPriority.php';
require_once __DIR__ . '/TicketStatus.php';

/**
 * MiniDesk - TicketRepository
 *
 * Classe responsable de tous les accès aux données de la table `tickets`.
 *
 * NOTE POUR LE CANDIDAT :
 *   - Certaines méthodes sont incomplètes ou contiennent des erreurs.
 *   - La méthode updateStatus() est entièrement à implémenter.
 *   - Identifiez et corrigez tous les problèmes avant de soumettre.
 */
class TicketRepository
{
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
     * hors de TicketPriority est refusée.
     *
     * @param array{title: string, description: string, priority: string} $data
     * @throws ValidationException si `title` ou `priority` est invalide
     */
    public function create(array $data): int
    {
        $errors = [];

        $title = $data['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            $errors['title'] = 'Le champ "title" est obligatoire.';
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
            ':description' => $data['description'] ?? '',
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

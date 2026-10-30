<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Exceptions/ValidationException.php';
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
     * @param array{title: string, description: string, priority: string} $data
     */
    public function create(array $data): int
    {
        $title = $data['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            throw new ValidationException(['title' => 'Le champ "title" est obligatoire.']);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO tickets (title, description, priority)
             VALUES (:title, :description, :priority)'
        );
        $stmt->execute([
            ':title'       => trim($title),
            ':description' => $data['description'] ?? '',
            ':priority'    => $data['priority']    ?? 'medium',
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
}

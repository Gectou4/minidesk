<?php

require_once __DIR__ . '/config.php';

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
    private const STATUSES = ['open', 'in_progress', 'closed'];

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
        $stmt = $this->db->prepare(
            'INSERT INTO tickets (title, description, priority)
             VALUES (:title, :description, :priority)'
        );
        $stmt->execute([
            ':title'       => $data['title'],
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
     *   - Valider que $status fait partie des valeurs autorisées :
     *       'open', 'in_progress', 'closed'
     *   - Retourner true si la mise à jour a affecté au moins une ligne,
     *     false si le ticket n'existe pas ou si le statut est invalide.
     *
     * @param int    $id     Identifiant du ticket
     * @param string $status Nouveau statut
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE tickets SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        // MySQL compte 0 ligne si le statut est déjà celui demandé : on vérifie l'existence.
        return $stmt->rowCount() > 0 || $this->findById($id) !== null;
    }
}

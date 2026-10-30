<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration sur la base MySQL de la stack.
 * Chaque test tourne dans une transaction annulée à la fin.
 */
class TicketRepositoryTest extends TestCase
{
    private PDO $db;
    private TicketRepository $repo;

    protected function setUp(): void
    {
        $this->db = get_db();
        $this->db->beginTransaction();
        $this->repo = new TicketRepository($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function creer(string $titre = 'Ticket de test'): int
    {
        return $this->repo->create(['title' => $titre]);
    }

    public function testCreateAppliqueLesValeursParDefaut(): void
    {
        $id = $this->creer();

        $ticket = $this->repo->findById($id);
        $this->assertSame('Ticket de test', $ticket['title']);
        $this->assertSame('', $ticket['description']);
        $this->assertSame('medium', $ticket['priority']);
        $this->assertSame(TicketStatus::Open->value, $ticket['status']);
    }

    public function testCreateConserveLesAccents(): void
    {
        $id = $this->repo->create([
            'title'       => 'Imprimante en panne',
            'description' => 'Bloquée depuis ce matin',
            'priority'    => 'high',
        ]);

        $ticket = $this->repo->findById($id);
        $this->assertSame('Bloquée depuis ce matin', $ticket['description']);
        $this->assertSame('high', $ticket['priority']);
    }

    public function testCreateRetireLesEspacesAutourDuTitre(): void
    {
        $id = $this->creer('  Titre à nettoyer  ');

        $this->assertSame('Titre à nettoyer', $this->repo->findById($id)['title']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function donneesSansTitreValide(): array
    {
        return [
            'title absent'      => [['description' => 'sans titre']],
            'title null'        => [['title' => null]],
            'title vide'        => [['title' => '']],
            'title en espaces'  => [['title' => "  \t "]],
            'title non chaîne'  => [['title' => 42]],
            'title tableau'     => [['title' => ['x']]],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('donneesSansTitreValide')]
    public function testCreateRefuseUnTitreInvalide(array $data): void
    {
        try {
            $this->repo->create($data);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('title', $e->getErrors());
        }
    }

    public function testCreateUtiliseMediumSiLaPrioriteEstAbsenteOuNull(): void
    {
        $sansPriorite = $this->repo->create(['title' => 'Sans priorité']);
        $prioriteNull = $this->repo->create(['title' => 'Priorité null', 'priority' => null]);

        $this->assertSame(TicketPriority::Medium->value, $this->repo->findById($sansPriorite)['priority']);
        $this->assertSame(TicketPriority::Medium->value, $this->repo->findById($prioriteNull)['priority']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function prioritesInvalides(): array
    {
        return [
            'valeur inconnue' => ['urgent'],
            'chaîne vide'     => [''],
            'casse différente' => ['HIGH'],
            'nombre'          => [3],
            'tableau'         => [['high']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('prioritesInvalides')]
    public function testCreateRefuseUnePrioriteInvalide(mixed $priorite): void
    {
        try {
            $this->repo->create(['title' => 'Ticket', 'priority' => $priorite]);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('priority', $e->getErrors());
            $this->assertArrayNotHasKey('title', $e->getErrors());
        }
    }

    public function testCreateCumuleLesErreursTitreEtPriorite(): void
    {
        try {
            $this->repo->create(['title' => '', 'priority' => 'urgent']);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertSame(['title', 'priority'], array_keys($e->getErrors()));
        }
    }

    public function testCreateAccepteUnTitreZero(): void
    {
        $id = $this->creer('0');

        $this->assertSame('0', $this->repo->findById($id)['title']);
    }

    public function testFindByIdRetourneNullSiInexistant(): void
    {
        $this->assertNull($this->repo->findById(999999999));
    }

    public function testFindAllContientLeTicketCree(): void
    {
        $id = $this->creer();

        $ids = array_column($this->repo->findAll(), 'id');
        $this->assertContains($id, array_map('intval', $ids));
    }

    public function testUpdateStatusAvecUnStatutValide(): void
    {
        $id = $this->creer();

        $this->assertTrue($this->repo->updateStatus($id, TicketStatus::InProgress));
        $this->assertSame(TicketStatus::InProgress->value, $this->repo->findById($id)['status']);
    }

    public function testUpdateStatusAvecLeStatutDejaActuel(): void
    {
        $id = $this->creer();

        $this->assertTrue($this->repo->updateStatus($id, TicketStatus::Open));
    }

    public function testUpdateStatusNeModifiePasLesAutresTickets(): void
    {
        $cible = $this->creer('Cible');
        $autre = $this->creer('Autre');

        $this->repo->updateStatus($cible, TicketStatus::Closed);

        $this->assertSame(TicketStatus::Open->value, $this->repo->findById($autre)['status']);
    }

    public function testUpdateStatusRefuseUneChaineBrute(): void
    {
        $id = $this->creer();

        $this->expectException(TypeError::class);
        $this->repo->updateStatus($id, 'nimporte_quoi');
    }

    public function testUpdateStatusRetourneFalseSiTicketInexistant(): void
    {
        $this->assertFalse($this->repo->updateStatus(999999999, TicketStatus::Closed));
    }

    public function testUpdatePriorityAvecUnePrioriteValide(): void
    {
        $id = $this->creer();

        $this->assertTrue($this->repo->updatePriority($id, TicketPriority::Critical));
        $this->assertSame(TicketPriority::Critical->value, $this->repo->findById($id)['priority']);
    }

    public function testUpdatePriorityAvecLaPrioriteDejaActuelle(): void
    {
        $id = $this->creer();

        $this->assertTrue($this->repo->updatePriority($id, TicketPriority::Medium));
    }

    public function testUpdatePriorityNeModifiePasLesAutresTicketsNiLeStatut(): void
    {
        $cible = $this->creer('Cible');
        $autre = $this->creer('Autre');

        $this->repo->updatePriority($cible, TicketPriority::Low);

        $this->assertSame(TicketPriority::Medium->value, $this->repo->findById($autre)['priority']);
        $this->assertSame(TicketStatus::Open->value, $this->repo->findById($cible)['status']);
    }

    public function testUpdatePriorityRefuseUneChaineBrute(): void
    {
        $id = $this->creer();

        $this->expectException(TypeError::class);
        $this->repo->updatePriority($id, 'nimporte_quoi');
    }

    public function testUpdatePriorityRetourneFalseSiTicketInexistant(): void
    {
        $this->assertFalse($this->repo->updatePriority(999999999, TicketPriority::High));
    }
}

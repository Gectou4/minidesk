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
}

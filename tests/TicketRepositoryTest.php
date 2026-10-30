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

    public function testCreateAccepteUnTitreDe255Caracteres(): void
    {
        $titre = str_repeat('é', 255);

        $id = $this->creer($titre);

        $this->assertSame($titre, $this->repo->findById($id)['title']);
    }

    public function testCreateCompteLaLongueurDuTitreSansLesEspacesDeBord(): void
    {
        $id = $this->creer('  ' . str_repeat('a', 255) . '  ');

        $this->assertSame(str_repeat('a', 255), $this->repo->findById($id)['title']);
    }

    public function testCreateRefuseUnTitreDe256Caracteres(): void
    {
        try {
            $this->creer(str_repeat('é', 256));
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertSame(['title'], array_keys($e->getErrors()));
        }
    }

    public function testCreateUtiliseUneDescriptionVideSiAbsenteOuNull(): void
    {
        $sansDescription = $this->repo->create(['title' => 'Sans description']);
        $descriptionNull = $this->repo->create(['title' => 'Description null', 'description' => null]);

        $this->assertSame('', $this->repo->findById($sansDescription)['description']);
        $this->assertSame('', $this->repo->findById($descriptionNull)['description']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function descriptionsInvalides(): array
    {
        return [
            'nombre'            => [42],
            'tableau'           => [['x']],
            'booléen'           => [true],
            'trop longue'       => [str_repeat('a', 65536)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('descriptionsInvalides')]
    public function testCreateRefuseUneDescriptionInvalide(mixed $description): void
    {
        try {
            $this->repo->create(['title' => 'Ticket', 'description' => $description]);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertSame(['description'], array_keys($e->getErrors()));
        }
    }

    public function testCreateAccepteUneDescriptionDeLaTailleMaximale(): void
    {
        $description = str_repeat('a', 65535);

        $id = $this->repo->create(['title' => 'Grande description', 'description' => $description]);

        $this->assertSame($description, $this->repo->findById($id)['description']);
    }

    public function testCreateCumuleLesErreursDesTroisChamps(): void
    {
        try {
            $this->repo->create(['title' => '', 'description' => 42, 'priority' => 'urgent']);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertSame(['title', 'description', 'priority'], array_keys($e->getErrors()));
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

    /**
     * @param array<int, array<string, mixed>> $tickets
     * @return int[]
     */
    private function ids(array $tickets): array
    {
        return array_map('intval', array_column($tickets, 'id'));
    }

    public function testFindAllTriePlusRecentEnPremierMemeAuMemeInstant(): void
    {
        $premier = $this->creer('Premier');
        $second  = $this->creer('Second');

        $ids = $this->ids($this->repo->findAll(new TicketQuery(limit: 2)));

        $this->assertSame([$second, $premier], $ids);
    }

    public function testFindAllFiltreParStatut(): void
    {
        $ferme = $this->creer('À fermer');
        $ouvert = $this->creer('Reste ouvert');
        $this->repo->updateStatus($ferme, TicketStatus::Closed);

        $tickets = $this->repo->findAll(new TicketQuery(status: TicketStatus::Closed));

        $this->assertContains($ferme, $this->ids($tickets));
        $this->assertNotContains($ouvert, $this->ids($tickets));
        $this->assertSame([TicketStatus::Closed->value], array_values(array_unique(array_column($tickets, 'status'))));
    }

    public function testFindAllFiltreParPriorite(): void
    {
        $critique = $this->repo->create(['title' => 'Critique', 'priority' => 'critical']);
        $normal   = $this->creer('Normal');

        $tickets = $this->repo->findAll(new TicketQuery(priority: TicketPriority::Critical));

        $this->assertContains($critique, $this->ids($tickets));
        $this->assertNotContains($normal, $this->ids($tickets));
    }

    public function testFindAllCombineLesFiltres(): void
    {
        $cible = $this->repo->create(['title' => 'Cible', 'priority' => 'low']);
        $this->repo->updateStatus($cible, TicketStatus::InProgress);
        $mauvaisStatut   = $this->repo->create(['title' => 'Mauvais statut', 'priority' => 'low']);
        $mauvaisePriorite = $this->creer('Mauvaise priorité');
        $this->repo->updateStatus($mauvaisePriorite, TicketStatus::InProgress);

        $ids = $this->ids($this->repo->findAll(new TicketQuery(
            status: TicketStatus::InProgress,
            priority: TicketPriority::Low,
        )));

        $this->assertContains($cible, $ids);
        $this->assertNotContains($mauvaisStatut, $ids);
        $this->assertNotContains($mauvaisePriorite, $ids);
    }

    public function testFindAllPagineAvecLimitEtOffset(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B');
        $c = $this->creer('C');

        $this->assertSame([$c, $b], $this->ids($this->repo->findAll(new TicketQuery(limit: 2))));
        $this->assertSame([$b, $a], $this->ids($this->repo->findAll(new TicketQuery(limit: 2, offset: 1))));
        $this->assertSame([$a], $this->ids($this->repo->findAll(new TicketQuery(limit: 1, offset: 2))));
    }

    public function testFindAllAvecOffsetSansLimitRenvoieLaSuite(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B');

        $tous = $this->ids($this->repo->findAll());
        $suite = $this->ids($this->repo->findAll(new TicketQuery(offset: 1)));

        $this->assertSame(array_slice($tous, 1), $suite);
        $this->assertContains($a, $suite);
        $this->assertNotContains($b, $suite);
    }

    public function testFindAllAvecOffsetAuDelaDuTotalRenvoieUneListeVide(): void
    {
        $this->assertSame([], $this->repo->findAll(new TicketQuery(limit: 5, offset: 1000000)));
    }

    public function testCountAllCorrespondALaListeFiltreeSansPagination(): void
    {
        $this->creer('A');
        $this->creer('B');
        $query = new TicketQuery(priority: TicketPriority::Medium);

        $this->assertSame(count($this->repo->findAll($query)), $this->repo->countAll($query));
        $this->assertSame(count($this->repo->findAll()), $this->repo->countAll());
        $this->assertSame($this->repo->countAll($query), $this->repo->countAll(new TicketQuery(
            priority: TicketPriority::Medium,
            limit: 1,
            offset: 1,
        )));
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

<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketQueryTest extends TestCase
{
    public function testSansParametreAucunFiltreNiPagination(): void
    {
        $query = TicketQuery::fromArray([]);

        $this->assertNull($query->status);
        $this->assertNull($query->priority);
        $this->assertNull($query->limit);
        $this->assertSame(0, $query->offset);
    }

    public function testTousLesParametresValides(): void
    {
        $query = TicketQuery::fromArray([
            'status'   => 'in_progress',
            'priority' => 'high',
            'limit'    => '10',
            'offset'   => '20',
        ]);

        $this->assertSame(TicketStatus::InProgress, $query->status);
        $this->assertSame(TicketPriority::High, $query->priority);
        $this->assertSame(10, $query->limit);
        $this->assertSame(20, $query->offset);
    }

    public function testLimitEtOffsetAcceptentLesBornes(): void
    {
        $this->assertSame(1, TicketQuery::fromArray(['limit' => '1'])->limit);
        $this->assertSame(TicketQuery::MAX_LIMIT, TicketQuery::fromArray(['limit' => (string) TicketQuery::MAX_LIMIT])->limit);
        $this->assertSame(0, TicketQuery::fromArray(['offset' => '0'])->offset);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function parametresInvalides(): array
    {
        return [
            'status inconnu'      => [['status' => 'nimporte_quoi'], 'status'],
            'status vide'         => [['status' => ''], 'status'],
            'status tableau'      => [['status' => ['open']], 'status'],
            'status casse'        => [['status' => 'OPEN'], 'status'],
            'priority inconnue'   => [['priority' => 'urgent'], 'priority'],
            'priority tableau'    => [['priority' => ['low']], 'priority'],
            'limit zéro'          => [['limit' => '0'], 'limit'],
            'limit trop grande'   => [['limit' => '101'], 'limit'],
            'limit négative'      => [['limit' => '-1'], 'limit'],
            'limit décimale'      => [['limit' => '1.5'], 'limit'],
            'limit texte'         => [['limit' => 'abc'], 'limit'],
            'limit signe plus'    => [['limit' => '+5'], 'limit'],
            'limit vide'          => [['limit' => ''], 'limit'],
            'limit tableau'       => [['limit' => ['1']], 'limit'],
            'offset négatif'      => [['offset' => '-1'], 'offset'],
            'offset texte'        => [['offset' => 'x'], 'offset'],
            'offset énorme'       => [['offset' => '99999999999999999999'], 'offset'],
        ];
    }

    /**
     * @param array<string, mixed> $parametres
     */
    #[DataProvider('parametresInvalides')]
    public function testRefuseUnParametreInvalide(array $parametres, string $champ): void
    {
        try {
            TicketQuery::fromArray($parametres);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertSame([$champ], array_keys($e->getErrors()));
        }
    }

    public function testCumuleLesErreursDeTousLesParametres(): void
    {
        try {
            TicketQuery::fromArray(['status' => 'x', 'priority' => 'y', 'limit' => '0', 'offset' => '-1']);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertSame(['status', 'priority', 'limit', 'offset'], array_keys($e->getErrors()));
        }
    }
}

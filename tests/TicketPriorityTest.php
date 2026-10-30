<?php

use PHPUnit\Framework\TestCase;

class TicketPriorityTest extends TestCase
{
    public function testTryFromRetourneLeBonCase(): void
    {
        $this->assertSame(TicketPriority::Low, TicketPriority::tryFrom('low'));
        $this->assertSame(TicketPriority::Medium, TicketPriority::tryFrom('medium'));
        $this->assertSame(TicketPriority::High, TicketPriority::tryFrom('high'));
        $this->assertSame(TicketPriority::Critical, TicketPriority::tryFrom('critical'));
    }

    public function testTryFromRetourneNullPourUneValeurInvalide(): void
    {
        $this->assertNull(TicketPriority::tryFrom('urgent'));
        $this->assertNull(TicketPriority::tryFrom(''));
        $this->assertNull(TicketPriority::tryFrom('HIGH'));
    }

    public function testValuesListeLesPrioritesDansLOrdre(): void
    {
        $this->assertSame(['low', 'medium', 'high', 'critical'], TicketPriority::values());
    }

    public function testLesValeursCorrespondentAuSchemaSql(): void
    {
        $schema = file_get_contents(__DIR__ . '/../db/schema.sql');
        $this->assertMatchesRegularExpression('/priority\s+ENUM\(([^)]*)\)/i', $schema);

        preg_match('/priority\s+ENUM\(([^)]*)\)/i', $schema, $m);
        $valeurs = array_map(
            static fn (string $v): string => trim($v, " '\""),
            explode(',', $m[1])
        );

        $this->assertSame($valeurs, TicketPriority::values());
    }
}

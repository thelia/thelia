<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Integration\Domain\Sequence;

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Connection\PdoConnection;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Test\IntegrationTestCase;

final class GaplessSequenceGeneratorTest extends IntegrationTestCase
{
    // Transactions are managed manually on a dedicated connection so that
    // rollback semantics can be observed for real.
    protected bool $useTransaction = false;

    private const SEQUENCE = 'test_gapless_sequence';

    private ConnectionWrapper $dedicatedConnection;

    private GaplessSequenceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dedicatedConnection = new ConnectionWrapper(new PdoConnection(
            \sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $_SERVER['DATABASE_HOST'],
                $_SERVER['DATABASE_PORT'] ?? '3306',
                $_SERVER['DATABASE_NAME'],
            ),
            $_SERVER['DATABASE_USER'],
            $_SERVER['DATABASE_PASSWORD'],
        ));

        $this->generator = new GaplessSequenceGenerator();
    }

    protected function tearDown(): void
    {
        if ($this->dedicatedConnection->inTransaction()) {
            $this->dedicatedConnection->rollBack();
        }

        $this->dedicatedConnection
            ->prepare('DELETE FROM `order_sequence` WHERE `name` = ?')
            ->execute([self::SEQUENCE]);

        parent::tearDown();
    }

    public function testAllocatesConsecutiveNumbersAndLazilyCreatesTheCounter(): void
    {
        $first = $this->generator->next(self::SEQUENCE, $this->dedicatedConnection);

        self::assertSame(1, $first);
        self::assertSame(2, $this->generator->next(self::SEQUENCE, $this->dedicatedConnection));
        self::assertSame(3, $this->generator->next(self::SEQUENCE, $this->dedicatedConnection));
    }

    public function testRolledBackAllocationLeavesNoGap(): void
    {
        $committed = $this->generator->next(self::SEQUENCE, $this->dedicatedConnection);

        $this->dedicatedConnection->beginTransaction();
        $burned = $this->generator->next(self::SEQUENCE, $this->dedicatedConnection);
        $this->dedicatedConnection->rollBack();

        self::assertSame($committed + 1, $burned);

        // The rolled back number must be handed out again: no gap.
        self::assertSame(
            $burned,
            $this->generator->next(self::SEQUENCE, $this->dedicatedConnection),
        );
    }

    public function testCommittedAllocationIsDurable(): void
    {
        $this->dedicatedConnection->beginTransaction();
        $allocated = $this->generator->next(self::SEQUENCE, $this->dedicatedConnection);
        $this->dedicatedConnection->commit();

        self::assertSame(
            $allocated + 1,
            $this->generator->next(self::SEQUENCE, $this->dedicatedConnection),
        );
    }

    public function testSetForcesTheCounterForSeriesTakeover(): void
    {
        $this->generator->set(self::SEQUENCE, 4100, $this->dedicatedConnection);

        self::assertSame(4101, $this->generator->next(self::SEQUENCE, $this->dedicatedConnection));
    }
}

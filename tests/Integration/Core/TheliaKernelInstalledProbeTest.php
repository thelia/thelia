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

namespace Thelia\Tests\Integration\Core;

use PHPUnit\Framework\Attributes\Test;
use Thelia\Core\TheliaKernel;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Whether the shop is installed is asked on every request, before anything
 * else. It has to be asked on the connection the request works on: a
 * connection of its own costs a TCP handshake and a query, on every request,
 * whatever the request goes on to do.
 */
final class TheliaKernelInstalledProbeTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetTheAnswer();
    }

    #[Test]
    public function theQuestionIsAskedOnTheConnectionOfTheRequest(): void
    {
        $statements = $this->recordSqlQueries(static function (): void {
            self::assertTrue(TheliaKernel::isInstalled());
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'config'),
            'The question must reach the Propel connection, not one opened for it alone.',
        );
    }

    #[Test]
    public function theAnswerIsAskedForOnlyOnce(): void
    {
        TheliaKernel::isInstalled();

        $statements = $this->recordSqlQueries(static function (): void {
            TheliaKernel::isInstalled();
            TheliaKernel::isInstalled();
        });

        self::assertSame([], $statements, 'A yes is remembered for the rest of the process.');
    }

    private function forgetTheAnswer(): void
    {
        $memo = new \ReflectionProperty(TheliaKernel::class, 'installed');
        $memo->setValue(null, null);
    }
}

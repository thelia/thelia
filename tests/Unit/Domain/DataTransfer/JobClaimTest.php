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

namespace Thelia\Tests\Unit\Domain\DataTransfer;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\DataTransfer\Job\JobClaim;

/**
 * The table of a job is written into the SQL: only the two job tables are ever named,
 * whatever a module's message says.
 */
final class JobClaimTest extends TestCase
{
    public function testAClaimNamesOnlyAJobTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new JobClaim())->claim('customer` SET `password` = ``#', 1);
    }

    public function testAnAbandonNamesOnlyAJobTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new JobClaim())->abandonIfQueued('customer', 1, 'Deleted.');
    }
}

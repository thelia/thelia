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

namespace Thelia\Tests\Integration\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Command\MessengerFailedPurgeCommand;
use Thelia\Test\IntegrationTestCase;

final class MessengerFailedPurgeCommandTest extends IntegrationTestCase
{
    /**
     * Zero would delete the failures of this very minute, and a figure past ten years is
     * a typing mistake: both are refused before anything is deleted.
     */
    public function testAnAgeOutOfReasonIsRefused(): void
    {
        foreach (['0', '99999999999999999999'] as $days) {
            $tester = new CommandTester($this->getService(MessengerFailedPurgeCommand::class));

            self::assertSame(Command::INVALID, $tester->execute(['--older-than' => $days, '--dry-run' => true]), $days);
        }
    }
}

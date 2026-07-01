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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Test\IntegrationTestCase;

/**
 * mail:render renders a mail message for a given order and locale to a file (or stdout)
 * without sending it, so a template can be previewed and worked on from the CLI.
 */
final class MailRenderTest extends IntegrationTestCase
{
    public function testRendersOrderConfirmationForAnOrder(): void
    {
        $order = $this->createFixtureFactory()->order();

        $tester = $this->commandTester();
        $tester->execute([
            'message-code' => 'order_confirmation',
            '--order' => (string) $order->getId(),
            '--locale' => 'fr_FR',
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString($order->getRef(), $tester->getDisplay());
    }

    public function testFailsGracefullyWhenTheOrderIsUnknown(): void
    {
        $tester = $this->commandTester();
        $tester->execute([
            'message-code' => 'order_confirmation',
            '--order' => '0',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('mail:render'));
    }
}

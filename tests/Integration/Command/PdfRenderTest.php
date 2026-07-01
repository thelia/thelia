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
 * pdf:render generates an order PDF (invoice or delivery) to a file without the HTTP flow,
 * so a PDF template can be previewed and worked on from the CLI.
 */
final class PdfRenderTest extends IntegrationTestCase
{
    private ?string $outputFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->outputFile && file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }

        parent::tearDown();
    }

    public function testRendersInvoiceForAnOrder(): void
    {
        $order = $this->createFixtureFactory()->order();
        $this->outputFile = sys_get_temp_dir().'/pdfrender_'.uniqid('', true).'.pdf';

        $tester = $this->commandTester();
        $tester->execute([
            'document' => 'invoice',
            '--order' => (string) $order->getId(),
            '--locale' => 'fr_FR',
            '--out' => $this->outputFile,
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertFileExists($this->outputFile);
        self::assertStringStartsWith('%PDF', (string) file_get_contents($this->outputFile));
    }

    public function testFailsOnUnknownDocument(): void
    {
        $tester = $this->commandTester();
        $tester->execute(['document' => 'flyer', '--order' => '1']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testFailsWhenTheOrderIsUnknown(): void
    {
        $tester = $this->commandTester();
        $tester->execute(['document' => 'invoice', '--order' => '0']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('pdf:render'));
    }
}

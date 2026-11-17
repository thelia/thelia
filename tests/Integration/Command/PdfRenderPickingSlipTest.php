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
use Thelia\Tests\Support\Order\PickingSlipFixtureTheme;

/**
 * pdf:render preparation renders several orders in one PDF; invoice and delivery stay one order.
 */
final class PdfRenderPickingSlipTest extends IntegrationTestCase
{
    use PickingSlipFixtureTheme;

    private ?string $outputFile = null;

    protected function tearDown(): void
    {
        $this->removePickingSlipTheme();

        if (null !== $this->outputFile && file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }

        parent::tearDown();
    }

    public function testRendersTwoOrdersInOnePdf(): void
    {
        $this->installPickingSlipTheme();
        $factory = $this->createFixtureFactory();
        $first = $factory->order();
        $second = $factory->order();
        $this->outputFile = sys_get_temp_dir().'/pdfrender_slip_'.uniqid('', true).'.pdf';

        $tester = $this->commandTester();
        $tester->execute([
            'document' => 'preparation',
            '--order' => [(string) $first->getId(), (string) $second->getId()],
            '--out' => $this->outputFile,
        ]);

        $tester->assertCommandIsSuccessful();
        $pdf = (string) file_get_contents($this->outputFile);
        self::assertStringStartsWith('%PDF', $pdf);
        self::assertSame(2, $this->pageCount($pdf));
        self::assertStringContainsString('2 orders', $tester->getDisplay());
    }

    public function testNamesTheMissingTemplateWhenTheThemeHasNone(): void
    {
        $this->installPickingSlipTheme(withPreparationTemplate: false);
        $order = $this->createFixtureFactory()->order();

        $tester = $this->commandTester();
        $tester->execute(['document' => 'preparation', '--order' => [(string) $order->getId()]]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('has no preparation document', $tester->getDisplay());
    }

    public function testInvoiceStillRefusesSeveralOrders(): void
    {
        $tester = $this->commandTester();
        $tester->execute(['document' => 'invoice', '--order' => ['1', '2']]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Only the preparation document takes several orders', $tester->getDisplay());
    }

    public function testPreparationNeedsAnOrder(): void
    {
        $tester = $this->commandTester();
        $tester->execute(['document' => 'preparation']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testAnUnknownLocaleIsRefusedInsteadOfFallingBackToTheDefaultLanguage(): void
    {
        $this->installPickingSlipTheme();
        $order = $this->createFixtureFactory()->order();
        $this->outputFile = sys_get_temp_dir().'/pdfrender_slip_'.uniqid('', true).'.pdf';

        $tester = $this->commandTester();
        $tester->execute([
            'document' => 'preparation',
            '--order' => [(string) $order->getId()],
            '--locale' => 'xx_XX',
            '--out' => $this->outputFile,
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No language has the locale xx_XX', $tester->getDisplay());
        self::assertFileDoesNotExist($this->outputFile);
    }

    public function testTheInvoiceRefusesAnUnknownLocaleToo(): void
    {
        $order = $this->createFixtureFactory()->order();
        // Written by the command only if it went past the locale: never in the working directory.
        $this->outputFile = sys_get_temp_dir().'/pdfrender_invoice_'.uniqid('', true).'.pdf';

        $tester = $this->commandTester();
        $tester->execute(['document' => 'invoice', '--order' => [(string) $order->getId()], '--locale' => 'xx_XX', '--out' => $this->outputFile]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No language has the locale xx_XX', $tester->getDisplay());
    }

    public function testAnOutputFileThatCannotBeWrittenIsAFailure(): void
    {
        $this->installPickingSlipTheme();
        $order = $this->createFixtureFactory()->order();

        $tester = $this->commandTester();
        $tester->execute([
            'document' => 'preparation',
            '--order' => [(string) $order->getId()],
            '--out' => sys_get_temp_dir().'/no-such-directory-'.uniqid('', true).'/slip.pdf',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Could not write', $tester->getDisplay());
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('pdf:render'));
    }
}

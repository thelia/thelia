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
use Thelia\Model\PriceList;
use Thelia\Model\PriceListItemQuery;
use Thelia\Test\IntegrationTestCase;

final class PriceListImportCommandTest extends IntegrationTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function testADryRunReportsAndWritesNothing(): void
    {
        [$priceList, $saleElement] = $this->setUpList();
        $path = $this->file(['pse_id;price', $saleElement->getId().';5', 'NOPE;6']);

        $tester = $this->commandTester();
        $tester->execute(['code' => $priceList->getCode(), 'file' => $path, '--dry-run' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), 'a line in error gives a code that is not zero');
        self::assertStringContainsString('Dry run, nothing written: 1 created, 0 updated, 1 error(s).', $tester->getDisplay());
        self::assertStringContainsString('line 3: unknown_sale_element_id (NOPE)', $tester->getDisplay());
        self::assertSame(0, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count());
    }

    public function testAnImportWritesTheValidLinesAndSucceedsWhenThereIsNoError(): void
    {
        [$priceList, $saleElement] = $this->setUpList();
        $path = $this->file(['pse_id;min_quantity;price', $saleElement->getId().';1;5', $saleElement->getId().';10;4']);

        $tester = $this->commandTester();
        $tester->execute(['code' => $priceList->getCode(), 'file' => $path]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Imported: 2 created, 0 updated, 0 error(s).', $tester->getDisplay());
        self::assertSame(2, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count());
    }

    public function testAnImportWithAnErrorStillWritesTheOtherLinesAndFails(): void
    {
        [$priceList, $saleElement] = $this->setUpList();
        $path = $this->file(['pse_id;price', $saleElement->getId().';5', 'NOPE;6']);

        $tester = $this->commandTester();
        $tester->execute(['code' => $priceList->getCode(), 'file' => $path]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(1, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count());
    }

    public function testAnUnknownListOrARefusedFileFails(): void
    {
        [$priceList] = $this->setUpList();
        $tester = $this->commandTester();

        $tester->execute(['code' => 'NO-SUCH-LIST', 'file' => $this->file(['pse_id;price', '1;5'])]);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No price list has this code.', $tester->getDisplay());

        $tester->execute(['code' => $priceList->getCode(), 'file' => $this->file(['pse_id;secret', '1;5'])]);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Unknown column', $tester->getDisplay());

        $tester->execute(['code' => $priceList->getCode(), 'file' => '/nonexistent/prices.csv']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /**
     * @return array{PriceList, \Thelia\Model\ProductSaleElements}
     */
    private function setUpList(): array
    {
        $factory = $this->createFixtureFactory();
        $priceList = (new PriceList())->setCode('CMD-'.bin2hex(random_bytes(4)))->setCurrencyId($factory->currency()->getId());
        $priceList->save();

        return [$priceList, $factory->product($factory->category(), $factory->taxRule(), $factory->currency())->getDefaultSaleElements()];
    }

    /**
     * @param list<string> $lines
     */
    private function file(array $lines): string
    {
        $path = sys_get_temp_dir().'/thelia-price-list-command-'.bin2hex(random_bytes(6)).'.csv';
        file_put_contents($path, implode("\n", $lines)."\n");
        $this->files[] = $path;

        return $path;
    }

    private function commandTester(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('thelia:price-list:import'));
    }
}

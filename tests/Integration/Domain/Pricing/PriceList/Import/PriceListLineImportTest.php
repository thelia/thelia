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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceList\Import;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Pricing\PriceList\Import\PriceListCsvReader;
use Thelia\Domain\Pricing\PriceList\Import\PriceListImportException;
use Thelia\Domain\Pricing\PriceList\Import\PriceListImportLineError;
use Thelia\Domain\Pricing\PriceList\Import\PriceListLineImport;
use Thelia\Model\PriceListItemQuery;

final class PriceListLineImportTest extends PriceListImportTestCase
{
    public function testTwoHundredLinesGiveTheExactReportAndOnlyTheValidOnesAreWritten(): void
    {
        $priceList = $this->priceList();
        $saleElements = [];

        for ($index = 0; $index < 25; ++$index) {
            $saleElements[] = $this->saleElement();
        }

        // 20 lines exist already: they are updated, the other 150 valid lines are created.
        for ($index = 0; $index < 20; ++$index) {
            $this->item($priceList, $saleElements[$index], 1, '99.000000');
        }

        $lines = ['pse_ref;pse_id;min_quantity;price;discount_percent'];
        $valid = 0;

        foreach ($saleElements as $position => $saleElement) {
            foreach ([1, 5, 10, 20, 50, 100, 200] as $quantity) {
                if ($valid >= 170) {
                    break 2;
                }

                $byId = 0 === $position % 2;
                $amount = 0 === $quantity % 10 && 10 !== $quantity ? ['', '12.5'] : [(string) (50 - $position).'.25', ''];
                $lines[] = implode(';', [$byId ? '' : $saleElement->getRef(), $byId ? (string) $saleElement->getId() : '', (string) $quantity, $amount[0], $amount[1]]);
                ++$valid;
            }
        }

        self::assertCount(171, $lines);

        $existing = $saleElements[0];
        $badLines = [
            'NOPE-REF;;1;5;' => PriceListImportLineError::UNKNOWN_REFERENCE,
            ';999999999;1;5;' => PriceListImportLineError::UNKNOWN_SALE_ELEMENT_ID,
            ';abc;1;5;' => PriceListImportLineError::UNKNOWN_SALE_ELEMENT_ID,
            ';;1;5;' => PriceListImportLineError::NO_SALE_ELEMENT,
            $existing->getRef().';'.$saleElements[1]->getId().';1;5;' => PriceListImportLineError::REFERENCE_AND_ID_DISAGREE,
            $existing->getRef().';;0;5;' => PriceListImportLineError::INVALID_MINIMUM_QUANTITY,
            $existing->getRef().';;-2;5;' => PriceListImportLineError::INVALID_MINIMUM_QUANTITY,
            $existing->getRef().';;two;5;' => PriceListImportLineError::INVALID_MINIMUM_QUANTITY,
            $existing->getRef().';;300;5;10' => PriceListImportLineError::TWO_AMOUNTS,
            $existing->getRef().';;301;;' => PriceListImportLineError::NO_AMOUNT,
            $existing->getRef().';;302;-3;' => PriceListImportLineError::INVALID_PRICE,
            $existing->getRef().';;303;abc;' => PriceListImportLineError::INVALID_PRICE,
            $existing->getRef().';;304;1e3;' => PriceListImportLineError::INVALID_PRICE,
            $existing->getRef().';;305;;0' => PriceListImportLineError::INVALID_DISCOUNT_PERCENT,
            $existing->getRef().';;306;;100.5' => PriceListImportLineError::INVALID_DISCOUNT_PERCENT,
            $existing->getRef().';;307;;-1' => PriceListImportLineError::INVALID_DISCOUNT_PERCENT,
            $existing->getRef().';;308;1;2;3' => PriceListImportLineError::WRONG_COLUMN_COUNT,
        ];

        $expected = [];

        foreach ($badLines as $line => $reason) {
            $lines[] = $line;
            $expected[\count($lines)] = $reason;
        }

        // The first line of the file carries (existing sale element, quantity 1): the same key twice is a duplicate.
        $lines[] = $existing->getRef().';;1;77;';
        $expected[\count($lines)] = PriceListImportLineError::DUPLICATE_IN_FILE;
        $duplicateLine = \count($lines);

        // Pad with more invalid lines up to 200 lines of data.
        while (\count($lines) - 1 < 200) {
            $lines[] = 'NOPE-'.\count($lines).';;1;5;';
            $expected[\count($lines)] = PriceListImportLineError::UNKNOWN_REFERENCE;
        }

        $file = $this->file($lines);
        $service = $this->getService(PriceListLineImport::class);
        $before = PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count();

        $preview = $service->preview($priceList, $file);

        self::assertFalse($preview->applied);
        self::assertSame(150, $preview->created);
        self::assertSame(20, $preview->updated);
        self::assertSame(\count($expected), $preview->errorCount);
        self::assertSame($expected, $this->reasons($preview));
        self::assertSame('2', $this->detail($preview, $duplicateLine));
        self::assertSame($before, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count(), 'a preview writes nothing');
        self::assertSame('99.000000', PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->filterByProductSaleElementsId($saleElements[0]->getId())->findOne()->getPrice(), 'a preview changes nothing');

        $applied = $service->apply($priceList, $file);

        self::assertTrue($applied->applied);
        self::assertSame(150, $applied->created);
        self::assertSame(20, $applied->updated);
        self::assertSame($expected, $this->reasons($applied));
        self::assertSame(170, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count(), 'the 170 valid lines are written, not one of the lines in error');
        $first = PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->filterByProductSaleElementsId($saleElements[0]->getId())->filterByMinQuantity(1)->findOne();
        self::assertSame('50.250000', $first->getPrice(), 'an existing line is updated by the first line of the file, not by its duplicate');
        $discount = PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->filterByProductSaleElementsId($saleElements[0]->getId())->filterByMinQuantity(20)->findOne();
        self::assertNull($discount->getPrice());
        self::assertSame('12.500000', $discount->getDiscountPercent());
        self::assertSame(0, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->filterByProductSaleElementsId($existing->getId())->filterByMinQuantity([300, 301, 302, 303, 304, 305, 306, 307, 308])->count());

        $again = $service->apply($priceList, $file);
        self::assertSame(0, $again->created, 'a second import of the same file creates nothing');
        self::assertSame(170, $again->updated);
    }

    public function testALineThatSwitchesFromAPriceToADiscountEmptiesThePrice(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();
        $this->item($priceList, $saleElement, 1, '10.000000');
        $this->item($priceList, $saleElement, 5, null, '20.000000');

        $report = $this->service()->apply($priceList, $this->file([
            'pse_id,min_quantity,price,discount_percent',
            $saleElement->getId().',1,,15',
            $saleElement->getId().',5,8.5,',
        ]));

        self::assertSame(0, $report->created);
        self::assertSame(2, $report->updated);
        $one = PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->filterByMinQuantity(1)->findOne();
        self::assertNull($one->getPrice());
        self::assertSame('15.000000', $one->getDiscountPercent());
        $five = PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->filterByMinQuantity(5)->findOne();
        self::assertSame('8.500000', $five->getPrice());
        self::assertNull($five->getDiscountPercent());
    }

    public function testTheDelimiterTheDecimalSeparatorTheByteOrderMarkAndTheDefaultQuantityAreRead(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();

        $report = $this->service()->apply($priceList, $this->file("\xEF\xBB\xBF".'PSE_REF ; Price'."\n".$saleElement->getRef().';12,5'."\n"));

        self::assertSame(1, $report->created);
        self::assertFalse($report->hasErrors());
        $row = PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->findOne();
        self::assertSame('12.500000', $row->getPrice());
        self::assertSame(1, $row->getMinQuantity(), 'without a min_quantity column, the minimum quantity is 1');
        self::assertSame($saleElement->getId(), $row->getProductSaleElementsId());
    }

    public function testAReferenceIsMatchedToTheSaleElementWithoutRegardToCase(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();

        $report = $this->service()->apply($priceList, $this->file(['pse_ref;price', strtolower($saleElement->getRef()).';3']));

        self::assertSame(1, $report->created, 'the reference is compared as the database compares it');
    }

    public function testBlankLinesAreSkippedAndTheLineNumbersStayTheOnesOfTheFile(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();

        $report = $this->service()->preview($priceList, $this->file(['pse_ref;price', '', $saleElement->getRef().';3', '', 'UNKNOWN;3']));

        self::assertSame(1, $report->created);
        self::assertSame([5 => PriceListImportLineError::UNKNOWN_REFERENCE], $this->reasons($report));
    }

    public function testALargeFileIsWrittenByBatches(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();
        $lines = ['pse_id;min_quantity;price'];

        for ($quantity = 1; $quantity <= 1100; ++$quantity) {
            $lines[] = $saleElement->getId().';'.$quantity.';'.$quantity;
        }

        $lines[] = $saleElement->getId().';1000;7';

        $report = $this->service()->apply($priceList, $this->file($lines));

        self::assertSame(1100, $report->created);
        self::assertSame([1102 => PriceListImportLineError::DUPLICATE_IN_FILE], $this->reasons($report), 'a duplicate is seen across the batches');
        self::assertSame(1100, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count());
        self::assertSame('1000.000000', PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->filterByMinQuantity(1000)->findOne()->getPrice());
    }

    public function testTheReportKeepsTheDetailOfTheFirstThousandErrorsAndCountsThemAll(): void
    {
        $priceList = $this->priceList();
        $lines = ['pse_ref;price'];

        for ($index = 1; $index <= 1200; ++$index) {
            $lines[] = 'NOPE-'.$index.';3';
        }

        $report = $this->service()->preview($priceList, $this->file($lines));

        self::assertSame(1200, $report->errorCount);
        self::assertCount(1000, $report->errors);
        self::assertSame(200, $report->omittedErrorCount());
    }

    #[DataProvider('provideRefusedHeaders')]
    public function testAFileWhoseHeaderIsNotTheExpectedOneIsRefusedWhole(string $content, string $messagePart): void
    {
        $priceList = $this->priceList();

        try {
            $this->service()->apply($priceList, $this->file($content));
            self::fail('The file must be refused.');
        } catch (PriceListImportException $exception) {
            self::assertStringContainsString($messagePart, $exception->getMessage());
        }

        self::assertSame(0, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRefusedHeaders(): iterable
    {
        yield 'a column that is not expected' => ["pse_id;price;path\n1;2;/etc/passwd\n", 'Unknown column'];
        yield 'no sale element column' => ["min_quantity;price\n1;2\n", 'pse_ref or pse_id'];
        yield 'no amount column' => ["pse_id;min_quantity\n1;2\n", 'price or discount_percent'];
        yield 'a column twice' => ["pse_id;price;price\n1;2;3\n", 'twice'];
        yield 'an empty file' => ['', 'empty'];
    }

    public function testAFileOverFiveMegabytesIsRefusedBeforeBeingRead(): void
    {
        $priceList = $this->priceList();
        $header = "pse_id;price\n";
        $file = $this->file($header.str_repeat("1;1\n", (int) ((5 * 1024 * 1024) / 4) + 1));

        self::assertGreaterThan(5 * 1024 * 1024, $file->getSize());
        $this->expectException(PriceListImportException::class);
        $this->expectExceptionMessage('larger than 5 MB');

        $this->service()->apply($priceList, $file);
    }

    public function testAFileOfExactlyFiveMegabytesIsAccepted(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();
        $header = 'pse_id;min_quantity;price'."\n";
        $line = $saleElement->getId().';1;1'."\n";
        $padding = 5 * 1024 * 1024 - \strlen($header) - \strlen($line);
        $file = $this->file($header.$line.str_repeat("\n", $padding));

        self::assertSame(5 * 1024 * 1024, $file->getSize());
        self::assertSame(1, $this->service()->preview($priceList, $file)->created);
    }

    public function testAnImportRefusesWhatIsNotARegularFileOutOfThePublicDirectory(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();
        $service = $this->service();
        $target = $this->file(['pse_id;price', $saleElement->getId().';3']);

        foreach ([
            'a directory' => new \SplFileInfo(sys_get_temp_dir()),
            'a missing file' => new \SplFileInfo(sys_get_temp_dir().'/thelia-price-list-missing-'.bin2hex(random_bytes(4)).'.csv'),
        ] as $label => $file) {
            try {
                $service->apply($priceList, $file);
                self::fail('Refused: '.$label);
            } catch (PriceListImportException) {
                self::assertSame(0, PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->count());
            }
        }

        $link = sys_get_temp_dir().'/thelia-price-list-link-'.bin2hex(random_bytes(4)).'.csv';
        symlink($target->getPathname(), $link);
        $this->forget($link);

        $this->expectException(PriceListImportException::class);
        $this->expectExceptionMessage('symbolic link');
        $service->apply($priceList, new \SplFileInfo($link));
    }

    public function testAFileInThePublicDirectoryIsRefused(): void
    {
        $root = sys_get_temp_dir().'/thelia-price-list-root-'.bin2hex(random_bytes(4));
        mkdir($root.'/public', 0o777, true);
        $path = $root.'/public/prices.csv';
        file_put_contents($path, "pse_id;price\n1;2\n");

        $reader = new PriceListCsvReader($root);

        try {
            iterator_to_array($reader->read(new \SplFileInfo($path)));
            self::fail('A file served by the web server must be refused.');
        } catch (PriceListImportException $exception) {
            self::assertStringContainsString('public directory', $exception->getMessage());
        } finally {
            unlink($path);
            rmdir($root.'/public');
            rmdir($root);
        }
    }

    public function testALineOutsideTheEncodingIsReportedNotWritten(): void
    {
        $priceList = $this->priceList();

        $report = $this->service()->preview($priceList, $this->file("pse_ref;price\n\xFF\xFE;3\n"));

        self::assertSame([2 => PriceListImportLineError::INVALID_ENCODING], $this->reasons($report));
    }

    public function testAFailureOfTheDatabaseRollsBackTheBatchItHitAndSaysSo(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();
        $file = $this->file(['pse_id;price', $saleElement->getId().';3']);
        $priceList->delete();

        try {
            $this->service()->apply($priceList, $file);
            self::fail('A line pointing to a list that is gone cannot be written.');
        } catch (PriceListImportException $exception) {
            self::assertStringContainsString('lines 2 to 2', $exception->getMessage());
            self::assertNotNull($exception->getPrevious());
        }

        self::assertSame(0, PriceListItemQuery::create()->filterByProductSaleElementsId($saleElement->getId())->count());
    }

    private function service(): PriceListLineImport
    {
        return $this->getService(PriceListLineImport::class);
    }
}

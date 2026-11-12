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

use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Pricing\PriceList\Export\PriceListLineExport;
use Thelia\Domain\Pricing\PriceList\Import\PriceListImportLineError;
use Thelia\Domain\Pricing\PriceList\Import\PriceListLineImport;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\PriceListItemQuery;

final class PriceListExportRoundTripTest extends PriceListImportTestCase
{
    public function testTheExportWritesTheColumnsOfTheImportAndIsLogged(): void
    {
        $priceList = $this->priceList();
        $first = $this->saleElement();
        $second = $this->saleElement();
        $this->item($priceList, $first, 1, '10.000000');
        $this->item($priceList, $first, 10, '9.000000');
        $this->item($priceList, $second, 1, null, '15.500000');
        $otherList = $this->priceList();
        $this->item($otherList, $second, 1, '1.000000');

        $stream = fopen('php://memory', 'w+');
        $written = $this->getService(PriceListLineExport::class)->write($priceList, $stream);
        rewind($stream);
        $rows = array_map(static fn (string $line): array => str_getcsv($line, ';', '"', ''), array_filter(explode("\n", (string) stream_get_contents($stream))));

        self::assertSame(3, $written);
        self::assertSame(['pse_ref', 'pse_id', 'min_quantity', 'price', 'discount_percent'], $rows[0]);
        self::assertSame([$first->getRef(), (string) $first->getId(), '1', '10.000000', ''], $rows[1]);
        self::assertSame([$first->getRef(), (string) $first->getId(), '10', '9.000000', ''], $rows[2]);
        self::assertSame([$second->getRef(), (string) $second->getId(), '1', '', '15.500000'], $rows[3]);
        self::assertCount(4, $rows, 'the lines of another list are not exported');

        $log = AdminLogQuery::create()->filterByResource(AdminResources::PRICING)->filterByResourceId($priceList->getId())->findOne();
        self::assertNotNull($log, 'an export is logged');
        self::assertStringContainsString($priceList->getCode(), $log->getMessage());
        self::assertStringContainsString('3 line', $log->getMessage());
    }

    public function testWhatIsExportedIsImportedBackIdentically(): void
    {
        $source = $this->priceList();
        $target = $this->priceList();
        $saleElement = $this->saleElement();
        $this->item($source, $saleElement, 1, '10.250000');
        $this->item($source, $saleElement, 25, null, '7.000000');

        $path = $this->file('')->getPathname();
        $stream = fopen($path, 'w');
        $this->getService(PriceListLineExport::class)->write($source, $stream);
        fclose($stream);

        $report = $this->getService(PriceListLineImport::class)->apply($target, new \SplFileInfo($path));

        self::assertSame(2, $report->created);
        self::assertFalse($report->hasErrors());
        $lines = PriceListItemQuery::create()->filterByPriceListId($target->getId())->orderByMinQuantity()->find();
        self::assertSame(['10.250000', null], [$lines[0]->getPrice(), $lines[1]->getPrice()]);
        self::assertSame([null, '7.000000'], [$lines[0]->getDiscountPercent(), $lines[1]->getDiscountPercent()]);
    }

    public function testWhatIsExportedIsImportedBackWhenTheReferenceIsSharedBySaleElements(): void
    {
        $source = $this->priceList();
        $target = $this->priceList();
        $first = $this->saleElement();
        $second = $this->saleElement();
        $second->setRef($first->getRef())->save();
        $this->item($source, $second, 1, '10.000000');

        $path = $this->file('')->getPathname();
        $stream = fopen($path, 'w');
        $this->getService(PriceListLineExport::class)->write($source, $stream);
        fclose($stream);

        $report = $this->getService(PriceListLineImport::class)->apply($target, new \SplFileInfo($path));

        self::assertFalse($report->hasErrors(), 'the identifier tells the sale element the reference does not');
        self::assertSame(1, $report->created);
        $lines = PriceListItemQuery::create()->filterByPriceListId($target->getId())->find();
        self::assertCount(1, $lines);
        self::assertSame($second->getId(), $lines[0]->getProductSaleElementsId());
    }

    public function testASharedReferenceAloneIsStillAmbiguousAndAnIdentifierOutsideItsMatchesDisagrees(): void
    {
        $target = $this->priceList();
        $first = $this->saleElement();
        $second = $this->saleElement();
        $second->setRef($first->getRef())->save();
        $other = $this->saleElement();

        $report = $this->getService(PriceListLineImport::class)->preview($target, $this->file([
            'pse_ref;pse_id;price',
            $first->getRef().';;5',
            $first->getRef().';'.$other->getId().';5',
            $first->getRef().';'.$first->getId().';5',
        ]));

        self::assertSame(
            [2 => PriceListImportLineError::AMBIGUOUS_REFERENCE, 3 => PriceListImportLineError::REFERENCE_AND_ID_DISAGREE],
            $this->reasons($report),
        );
        self::assertSame(1, $report->created);
    }

    public function testAnExportOfMoreThanOneBatchLosesNoLine(): void
    {
        $priceList = $this->priceList();
        $saleElement = $this->saleElement();

        for ($quantity = 1; $quantity <= PriceListLineExport::BATCH_SIZE + 3; ++$quantity) {
            $this->item($priceList, $saleElement, $quantity, (string) $quantity.'.000000');
        }

        $stream = fopen('php://memory', 'w+');
        $written = $this->getService(PriceListLineExport::class)->write($priceList, $stream);

        self::assertSame(PriceListLineExport::BATCH_SIZE + 3, $written);
        rewind($stream);
        self::assertCount(PriceListLineExport::BATCH_SIZE + 4, array_filter(explode("\n", (string) stream_get_contents($stream))));
    }
}

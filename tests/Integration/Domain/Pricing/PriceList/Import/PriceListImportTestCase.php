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

use Thelia\Domain\Pricing\PriceList\Import\PriceListImportLineError;
use Thelia\Domain\Pricing\PriceList\Import\PriceListImportReport;
use Thelia\Model\PriceList;
use Thelia\Model\PriceListItem;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\IntegrationTestCase;

/**
 * The set-up shared by the tests of the import and the export of the price list lines.
 */
abstract class PriceListImportTestCase extends IntegrationTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_link($file) || is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    protected function priceList(): PriceList
    {
        $priceList = (new PriceList())
            ->setCode('IMPORT-'.bin2hex(random_bytes(4)))
            ->setCurrencyId($this->createFixtureFactory()->currency()->getId());
        $priceList->save();

        return $priceList;
    }

    protected function saleElement(): ProductSaleElements
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency())->getDefaultSaleElements();
    }

    protected function item(PriceList $priceList, ProductSaleElements $saleElement, int $minimumQuantity, ?string $price, ?string $discountPercent = null): PriceListItem
    {
        $item = (new PriceListItem())
            ->setPriceListId($priceList->getId())
            ->setProductSaleElementsId($saleElement->getId())
            ->setMinQuantity($minimumQuantity)
            ->setPrice($price)
            ->setDiscountPercent($discountPercent);
        $item->save();

        return $item;
    }

    /**
     * @param string|list<string> $content
     */
    protected function file(string|array $content, string $suffix = '.csv'): \SplFileInfo
    {
        $path = sys_get_temp_dir().'/thelia-price-list-'.bin2hex(random_bytes(6)).$suffix;
        file_put_contents($path, \is_array($content) ? implode("\n", $content)."\n" : $content);
        $this->files[] = $path;

        return new \SplFileInfo($path);
    }

    protected function forget(string $path): void
    {
        $this->files[] = $path;
    }

    /**
     * @return array<int, string> the reason of every line in error, by line number
     */
    protected function reasons(PriceListImportReport $report): array
    {
        $reasons = [];

        foreach ($report->errors as $error) {
            $reasons[$error->line] = $error->reason;
        }

        return $reasons;
    }

    protected function detail(PriceListImportReport $report, int $line): ?string
    {
        foreach ($report->errors as $error) {
            if ($error->line === $line) {
                return $error->detail;
            }
        }

        return null;
    }

    protected function lineErrorClass(): string
    {
        return PriceListImportLineError::class;
    }
}

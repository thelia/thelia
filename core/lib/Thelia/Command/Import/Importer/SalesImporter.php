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

namespace Thelia\Command\Import\Importer;

use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleOffsetCurrency;
use Thelia\Model\SaleProduct;

final class SalesImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 90;
    }

    public function description(): string
    {
        return 'Sales';
    }

    public function import(DemoImportContext $context): void
    {
        $currencies = CurrencyQuery::create()->find($context->connection);
        $products = ProductQuery::create()->find($context->connection);

        foreach ($this->readCsv($context->dataDir.'sales.csv') as $data) {
            $sale = (new Sale())
                ->setActive(0)
                ->setStartDate((new \DateTime())->setTimestamp((int) strtotime('today - 1 month')))
                ->setEndDate((new \DateTime())->setTimestamp((int) strtotime('today + 1 month')))
                ->setPriceOffsetType((int) $data[2])
                ->setDisplayInitialPrice(true)
                ->setLocale('fr_FR')->setTitle(trim($data[0]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle(trim($data[1]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.');
            $sale->save($context->connection);

            foreach ($currencies as $currency) {
                (new SaleOffsetCurrency())
                    ->setCurrencyId($currency->getId())
                    ->setSaleId($sale->getId())
                    ->setPriceOffsetValue((float) $data[3])
                    ->save($context->connection);
            }

            $count = 5;
            foreach ($products as $product) {
                if (--$count < 0) {
                    break;
                }

                (new SaleProduct())
                    ->setSaleId($sale->getId())
                    ->setProductId($product->getId())
                    ->setAttributeAvId(null)
                    ->save($context->connection);
            }
        }
    }
}

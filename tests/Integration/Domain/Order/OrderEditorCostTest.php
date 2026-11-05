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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Domain\Order\DTO\OrderEdit;
use Thelia\Domain\Order\Service\OrderEditor;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;

final class OrderEditorCostTest extends IntegrationTestCase
{
    /**
     * Measured with the instance pool disabled, as every integration test is: a
     * request that has the pool on pays about one query less per line. A line
     * costs ten or eleven queries (the two checks that it is neither shipped nor
     * returned, its product, the update, the replacement of its taxes and their
     * translations); a new read per line would show here before it reaches the
     * order sheet of a shop with long orders.
     */
    public function testAPriceEditCostsAFixedNumberOfQueriesPerLine(): void
    {
        $costs = [];

        foreach ([1, 6, 12] as $count) {
            [$order, $lineIds] = $this->anOrderOfLines($count);
            $prices = array_fill_keys($lineIds, '9.000000');

            $costs[$count] = $this->queriesOf(fn () => $this->getService(OrderEditor::class)->edit($order, new OrderEdit(unitPrices: $prices)));
        }

        $perLine = ($costs[12] - $costs[1]) / 11;

        self::assertLessThanOrEqual(12.0, $perLine, \sprintf('A price edit costs %.1f queries per line (1, 6 and 12 lines: %s).', $perLine, json_encode($costs)));
        self::assertEqualsWithDelta(($costs[6] - $costs[1]) / 5, $perLine, 1.0, 'The cost must stay linear in the number of lines.');
    }

    private function queriesOf(callable $work): int
    {
        $connection = $this->getPropelConnection();
        $connection->useDebug(true);
        $before = $connection->getQueryCount();
        $work();
        $queries = $connection->getQueryCount() - $before;
        $connection->useDebug(false);

        return $queries;
    }

    /**
     * @return array{Order, list<int>}
     */
    private function anOrderOfLines(int $count): array
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country(['isocode' => '056', 'isoalpha2' => 'BE', 'isoalpha3' => 'BEL', 'shopCountry' => false]);
        $rule = $factory->taxRule(['isDefault' => false]);
        (new TaxRuleCountry())
            ->setTaxRuleId($rule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($factory->tax(['requirements' => ['percent' => '20'], 'title' => 'VAT 20'])->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_NOT_PAID]);
        OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())->setCountryId($country->getId())->save();

        $lineIds = [];

        for ($index = 0; $index < $count; ++$index) {
            $product = $factory->product($factory->category(), $rule, $factory->currency(), ['title' => 'Widget '.$index, 'baseQuantity' => 10]);
            $pse = $product->getDefaultSaleElements();
            $line = $factory->orderProduct($order, [
                'productRef' => $product->getRef(),
                'productSaleElementsRef' => $pse->getRef(),
                'productSaleElementsId' => $pse->getId(),
                'quantity' => 1.0,
                'price' => '10.000000',
                'promoPrice' => '10.000000',
            ]);
            (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT 20')->setAmount('2')->setPromoAmount('2')->save($this->getPropelConnection());
            $lineIds[] = (int) $line->getId();
        }

        ProductSaleElementsTableMap::clearInstancePool();

        return [$order, $lineIds];
    }
}

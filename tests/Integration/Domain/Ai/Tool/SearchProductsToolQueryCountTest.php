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

namespace Thelia\Tests\Integration\Domain\Ai\Tool;

use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiToolCall;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\Builtin\SearchProductsTool;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Support\Ai\AiToolTestCase;

final class SearchProductsToolQueryCountTest extends AiToolTestCase
{
    use RecordsSqlQueries;

    public function testTheSearchReadsThePricesInOneStatementWhateverTheNumberOfProducts(): void
    {
        $this->buildServices(['thelia_search_products' => SearchProductsTool::class]);
        $factory = $this->createFixtureFactory();
        $currency = CurrencyQuery::create()->filterByByDefault(true)->findOne() ?? $factory->currency();
        $category = $factory->category();
        $taxRule = $factory->taxRule();

        for ($i = 0; $i < 8; ++$i) {
            $factory->product($category, $taxRule, $currency, ['ref' => 'NPLUS-'.$i, 'title' => 'Nplus '.$i, 'basePrice' => 10.0, 'baseQuantity' => 1]);
        }

        $admin = $factory->admin();
        $context = new AiToolContext(AiActor::admin($admin->getId()), 'aiprobe.tools_admin', 'aiprobe.conversation.'.bin2hex(random_bytes(4)));
        $offer = $this->registry->offer($context, ['thelia_search_products']);

        ProductPriceTableMap::clearInstancePool();
        ProductSaleElementsTableMap::clearInstancePool();

        $result = null;
        $statements = $this->recordSqlQueries(function () use ($offer, &$result): void {
            $result = $this->registry->call(new AiToolCall('toolu_01', 'thelia_search_products', ['query' => 'NPLUS', 'limit' => 20]), $offer->context);
        });

        self::assertSame(8, $result->content['count']);
        self::assertLessThanOrEqual(1, self::countSqlQueriesSelectingFrom($statements, 'product_price'), 'one product_price SELECT per variant (N+1)');
        self::assertLessThanOrEqual(1, self::countSqlQueriesSelectingFrom($statements, 'product_sale_elements'), 'one product_sale_elements SELECT per product (N+1)');
    }
}

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

namespace Thelia\Tests\Api\Front;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A shop with no customer group feature in use - no price of a group, no discount of a
 * group, no category closed to a group - and no rule nor reserved operation pays no
 * query for them on a front request: whatever the request asks of the rules, the
 * operations, the groups and the closed categories is answered by one statement, where
 * the shop paid one statement per question before the customer groups.
 *
 * Before the customer groups, these requests ran a statement for the public rules, one
 * for the named rules and one for the reserved operations; the customer groups added
 * one for the prices of the groups, one for their discounts and one for the closed
 * categories. Every one of them is now part of the single activity statement.
 */
final class CustomerGroupFreeShopQueryCountTest extends ApiTestCase
{
    use RecordsSqlQueries;

    /**
     * Statements the requests ran before the customer groups, measured with the groups
     * statements taken out: a request may not run more.
     */
    private const STATEMENTS_BEFORE_THE_CUSTOMER_GROUPS = [
        'product page' => 19,
        'product list' => 15,
        'cart line added to' => 16,
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function frontRequests(): iterable
    {
        yield 'product page' => ['product page'];
        yield 'product list' => ['product list'];
        yield 'cart line added to' => ['cart line added to'];
        yield 'new cart line' => ['new cart line'];
    }

    #[DataProvider('frontRequests')]
    public function testAShopWithoutCustomerGroupsAsksOneStatementAboutWhatPricesTheCatalog(string $request): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency(), ['baseQuantity' => 10]);
        $other = $factory->product($category, $factory->taxRule(), $factory->currency(), ['baseQuantity' => 10]);
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $token = $this->authenticateAsCustomer($customer);
        $addToCart = fn (int $productSaleElementsId): Response => $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$productSaleElementsId,
            'quantity' => 1,
        ], $token);

        $send = match ($request) {
            'product page' => fn (): Response => $this->jsonRequest('GET', '/api/front/products/'.$product->getId()),
            'product list' => fn (): Response => $this->jsonRequest('GET', '/api/front/products?productCategories.category.id='.$category->getId()),
            'cart line added to' => static fn (): Response => $addToCart((int) $product->getProductSaleElementss()->getFirst()->getId()),
            'new cart line' => static fn (): Response => $addToCart((int) $other->getProductSaleElementss()->getFirst()->getId()),
        };

        // A first request warms what a shop keeps between requests.
        if ('new cart line' !== $request) {
            self::assertLessThan(300, $send()->getStatusCode());
        }

        $statements = $this->recordSqlQueries(function () use ($send): void {
            // A later request is another process: nothing of the first one is remembered.
            $this->getService('services_resetter')->reset();

            self::assertLessThan(300, $send()->getStatusCode());
        });

        $activityStatements = array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/FROM `(catalog_price_rule|sale|customer_group\w*)`/', $statement),
        ));

        self::assertCount(1, $activityStatements, implode("\n", $activityStatements));

        if (isset(self::STATEMENTS_BEFORE_THE_CUSTOMER_GROUPS[$request])) {
            self::assertLessThanOrEqual(self::STATEMENTS_BEFORE_THE_CUSTOMER_GROUPS[$request], \count($statements), implode("\n", $statements));
        }
    }
}

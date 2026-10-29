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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Security\CartOwnership;
use Thelia\Api\State\Processor\FrontCartItemProcessor;
use Thelia\Domain\Cart\Bundle\CartCompositionResolver;
use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;

/**
 * An addition through the front API reads the combination the way the catalog
 * shows it to the visitor (docs/product-visibility.md, "The cart"): one that a
 * module rule hides answers like one that does not exist, and the cart is left
 * as it was.
 */
final class CartItemHiddenByAModuleRuleApiTest extends ApiTestCase
{
    public function testACombinationAModuleRuleHidesCannotBeAddedThroughTheFrontApi(): void
    {
        [$token, $cart, $product] = $this->buyerWithACart();
        $this->hideFromTheCatalog($product);

        $response = $this->add($token, $cart, $product);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheSameCombinationIsAddedWithoutTheRule(): void
    {
        [$token, $cart, $product] = $this->buyerWithACart();

        $response = $this->add($token, $cart, $product);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    /**
     * @return array{string, Cart, Product}
     */
    private function buyerWithACart(): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100]);

        return [$this->authenticateAsCustomer($customer), $factory->cart($customer), $product];
    }

    private function add(string $token, Cart $cart, Product $product): Response
    {
        return $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$product->getProductSaleElementss()->getFirst()->getId(),
            'quantity' => 2,
        ], $token);
    }

    /**
     * A module rule, as a module declares it, hiding this product from every visitor.
     */
    private function hideFromTheCatalog(Product $product): void
    {
        $rule = new class((int) $product->getId()) implements ProductVisibilityRuleInterface {
            public function __construct(private readonly int $hiddenProductId)
            {
            }

            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return \sprintf('%s <> %d', $productIdColumn, $this->hiddenProductId);
            }
        };

        $customers = $this->getService(CurrentCustomerProvider::class);
        $reservedSaleRule = new ReservedSaleProductRule($this->getService(SaleAudienceChecker::class), $customers);

        $visibility = new ReservedSaleVisibility(
            $this->getService(SaleAudienceChecker::class),
            $customers,
            new ProductVisibility($reservedSaleRule, $customers, [$rule]),
            $reservedSaleRule,
        );

        // The processor is built on the first addition: given the visibility a container holding the rule would give it.
        static::getContainer()->set(FrontCartItemProcessor::class, new FrontCartItemProcessor(
            $this->getService(CartFacade::class),
            $this->getService(CartOwnership::class),
            $visibility,
            $this->getService(ApiResourcePropelTransformerService::class),
            $this->getService(EventDispatcherInterface::class),
            $this->getService(CartLineAvailability::class),
            $this->getService(CartCompositionResolver::class),
        ));
    }
}

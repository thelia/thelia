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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * A product that becomes a bundle, or stops being one, takes its lines out of the carts: a line
 * written for the one form is wrong in the other. The model of the product does it, so the API
 * covers it like every other writer.
 */
final class ProductFormChangeCartLinesApiTest extends ApiTestCase
{
    use BuildsBundles;

    public function testAProductThatBecomesABundleLeavesNoLineInAnyCart(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $this->component($factory, '10', 1, ['title' => 'Future bundle']);
        $other = $this->component($factory, '10', 1, ['title' => 'Another']);
        $cart = $factory->cart();
        $factory->cartItem($cart, $product);
        $factory->cartItem($factory->cart(), $product);
        $factory->cartItem($cart, $other);

        $response = $this->patch($product, ['isBundle' => true, 'bundlePriceMode' => 'fixed']);

        self::assertJsonResponseSuccessful($response);
        self::assertSame(0, CartItemQuery::create()->filterByProductId($product->getId())->count());
        self::assertSame(1, CartItemQuery::create()->filterByProductId($other->getId())->count(), 'The lines of the other products stay.');
    }

    public function testAProductThatStopsBeingABundleLeavesNoLineInAnyCart(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service());
        $cart = $factory->cart();
        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($example['bundle']->getId())
            ->setProductSaleElementsId($this->saleElementsOf($example['bundle'])->getId())
            ->setQuantity(1.0)
            ->setPrice('600')
            ->setPromoPrice('540')
            ->setPromo(1)
            ->setBundleComposition('[{"bundleItemId":'.$example['bike']->getId().',"quantity":"1"}]')
            ->save();
        self::assertSame(1, CartItemQuery::create()->filterByProductId($example['bundle']->getId())->count());

        $response = $this->patch($example['bundle'], ['isBundle' => false]);

        self::assertJsonResponseSuccessful($response);
        self::assertSame(0, CartItemQuery::create()->filterByProductId($example['bundle']->getId())->count());
    }

    public function testAnotherChangeOfTheProductKeepsItsLines(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $this->component($factory, '10', 1, ['title' => 'Plain']);
        $factory->cartItem($factory->cart(), $product);

        $response = $this->patch($product, ['visible' => false]);

        self::assertJsonResponseSuccessful($response);
        self::assertSame(1, CartItemQuery::create()->filterByProductId($product->getId())->count());
    }

    private function service(): BundleCompositionService
    {
        return $this->getService(BundleCompositionService::class);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patch(Product $product, array $payload): Response
    {
        return $this->jsonRequest('PATCH', '/api/admin/products/'.$product->getId(), $payload + [
            'i18ns' => ['en_US' => ['title' => 'Product', 'locale' => 'en_US']],
        ], $this->authenticateAsAdmin(), 'merge-patch+json');
    }
}

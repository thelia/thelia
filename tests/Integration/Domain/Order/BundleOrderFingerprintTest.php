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

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Order\Service\OrderFingerprint;
use Thelia\Model\Cart;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\PlacesBundleOrders;

/**
 * The composition of a pack is part of what a cart says, and only of that: the fingerprint
 * frozen on an unpaid order placed before packs existed must keep matching its cart.
 */
final class BundleOrderFingerprintTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use PlacesBundleOrders;

    public function testTheLineOfACartWithoutAnyBundleIsTheOneItHasAlwaysBeen(): void
    {
        $product = $this->component($this->factory, '10', 5);
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $cartItem = $this->addSimpleLine($cart, $product, 2.0, '12.500000');

        $shape = $this->shapeOf($cart);

        self::assertSame(
            [[
                'pse' => $this->saleElementsIdOf($product),
                'quantity' => '2.000000',
                'price' => '12.500000',
                'promo_price' => '12.500000',
                'in_promo' => 0,
                'offered' => 0,
            ]],
            $shape['lines'],
            'A line that is not a pack carries no composition key: the frozen fingerprints stay valid.',
        );
        self::assertSame(
            ['lines', 'delivery', 'invoice', 'delivery_module', 'postage', 'postage_tax', 'payment_module', 'currency', 'discount', 'gift_wrapping', 'gift_message'],
            array_keys($shape),
        );
        self::assertSame(
            hash('sha256', json_encode($shape, \JSON_THROW_ON_ERROR)),
            $this->getService(OrderFingerprint::class)->of($cart, 1, 1, $currency->getId()),
        );
        self::assertNotNull($cartItem->getId());
    }

    public function testTheCompositionOfAPackIsPartOfItsLine(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2']);

        $line = $this->shapeOf($cart)['lines'][0];

        self::assertSame(
            \sprintf('[{"bundleItemId":%d,"quantity":"1"},{"bundleItemId":%d,"quantity":"2"}]', $example['bike']->getId(), $example['helmet']->getId()),
            $line['composition'],
        );
    }

    public function testTwoPacksAtTheSamePriceButOfOtherComponentsAreNotTheSameCart(): void
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);
        [$customer, $currency] = $this->aBuyer();
        $fingerprint = $this->getService(OrderFingerprint::class);

        $withoutLock = $this->aCart($customer, $currency);
        $this->addBundleLine($withoutLock, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2']);
        $withLock = $this->aCart($customer, $currency);
        $this->addBundleLine($withLock, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2', (int) $example['lock']->getId() => '1']);
        $sameAsFirst = $this->aCart($customer, $currency);
        $this->addBundleLine($sameAsFirst, $example['bundle'], [(int) $example['helmet']->getId() => '2', (int) $example['bike']->getId() => '1']);

        self::assertNotSame(
            $fingerprint->of($withoutLock, 1, 1, $currency->getId()),
            $fingerprint->of($withLock, 1, 1, $currency->getId()),
            'The buyer who adds a lock to the pack is not paying for the order of the other pack.',
        );
        self::assertSame(
            $fingerprint->of($withoutLock, 1, 1, $currency->getId()),
            $fingerprint->of($sameAsFirst, 1, 1, $currency->getId()),
            'The same choice, entered in another order, is the same cart.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeOf(Cart $cart): array
    {
        $cart->reload(true);
        $method = new \ReflectionMethod(OrderFingerprint::class, 'ofCart');

        return $method->invoke($this->getService(OrderFingerprint::class), $cart, 1, 1, (int) $cart->getCurrencyId(), null, null);
    }
}

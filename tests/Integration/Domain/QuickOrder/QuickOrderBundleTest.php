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

namespace Thelia\Tests\Integration\Domain\QuickOrder;

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\QuickOrder\QuickOrderFacade;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * Ordering a bundle by its reference: priced by its components and supplied by them, never by
 * the stock of its own sale element.
 */
final class QuickOrderBundleTest extends IntegrationTestCase
{
    use BuildsBundles;

    protected function setUp(): void
    {
        parent::setUp();
        ConfigQuery::write('check-available-stock', '1');
        $this->getService(PricingActivityChecker::class)->reset();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        $this->getService(PricingActivityChecker::class)->reset();

        parent::tearDown();
    }

    public function testTheLineIsPricedByTheComponentsAndLimitedByTheirStock(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $customer = $factory->customer($factory->customerTitle());
        $facade = $this->getService(QuickOrderFacade::class);
        $reference = (string) $example['bundle']->getRef();

        $resolved = $facade->resolve($customer, new ReferenceQuantityLines([new ReferenceQuantity($reference, 2)]), Currency::getDefaultCurrency())->lines[0];
        $refused = $facade->resolve($customer, new ReferenceQuantityLines([new ReferenceQuantity($reference, 3)]), Currency::getDefaultCurrency())->lines[0];

        self::assertSame('resolved', $resolved->status->value);
        self::assertEqualsWithDelta(504.0, $resolved->untaxedUnitPrice, 0.000001);
        self::assertTrue($resolved->promo);
        self::assertSame('quantity_refused', $refused->status->value);
        self::assertSame(2.0, $refused->availableQuantity, 'The two bundles the components allow, not the zero of the sale element of the bundle.');
    }

    public function testABundleWithoutDiscountIsSoldAtTheSumOfItsComponents(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class), discount: '0');
        $customer = $factory->customer($factory->customerTitle());

        $line = $this->getService(QuickOrderFacade::class)
            ->resolve($customer, new ReferenceQuantityLines([new ReferenceQuantity((string) $example['bundle']->getRef(), 1)]), Currency::getDefaultCurrency())
            ->lines[0];

        self::assertEqualsWithDelta(560.0, $line->untaxedUnitPrice, 0.000001);
        self::assertFalse($line->promo);
    }

    public function testAddingBeyondTheComponentsIsReportedRefusedAndTheCartKeepsItsLine(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $customer = $factory->customer($factory->customerTitle());
        $cart = $factory->cart($customer, ['currency' => Currency::getDefaultCurrency()]);
        $facade = $this->getService(QuickOrderFacade::class);
        $lines = new ReferenceQuantityLines([new ReferenceQuantity((string) $example['bundle']->getRef(), 2)]);

        $first = $facade->addToCart($customer, $cart, $lines)->lines[0];
        $second = $facade->addToCart($customer, $cart, new ReferenceQuantityLines([new ReferenceQuantity((string) $example['bundle']->getRef(), 1)]))->lines[0];

        self::assertTrue($first->added);
        self::assertFalse($second->added);
        self::assertSame('quantity_refused', $second->status->value);
        self::assertSame(0.0, $second->availableQuantity);
        $items = CartItemQuery::create()->filterByCartId($cart->getId())->find();
        self::assertCount(1, $items);
        self::assertSame(2.0, (float) $items[0]->getQuantity());
        self::assertNotNull($items[0]->getBundleComposition());
    }
}

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

namespace Thelia\Tests\Integration\Domain\Taxation;

use Thelia\Core\Event\Tax\TaxCalculatorEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Taxation\TaxEngine\Calculator;
use Thelia\Domain\Taxation\TaxEngine\OrderProductTaxCollection;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;
use Thelia\Model\State;
use Thelia\Model\TaxRule;
use Thelia\Test\IntegrationTestCase;

/**
 * A shop with its own tax rules must be able to swap the calculator, including
 * on the Propel models, which have no access to the container.
 */
final class TaxCalculatorReplacementTest extends IntegrationTestCase
{
    public const SUBSTITUTED_TAXED_PRICE = 133.7;

    private ?\Closure $listener = null;

    protected function tearDown(): void
    {
        if ($this->listener instanceof \Closure) {
            static::getContainer()->get('event_dispatcher')
                ->removeListener(TheliaEvents::TAX_GET_CALCULATOR, $this->listener);
            $this->listener = null;
        }

        parent::tearDown();
    }

    public function testProductTaxedPriceGoesThroughTheReplacedCalculator(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $country = $factory->country();

        self::assertSame(
            100.0,
            (float) $product->getTaxedPrice($country, 100.0),
            'The shipped calculator adds nothing when the tax rule carries no tax.',
        );

        $this->replaceCalculator();

        self::assertSame(
            self::SUBSTITUTED_TAXED_PRICE,
            (float) $product->getTaxedPrice($country, 100.0),
            'Product::getTaxedPrice() must use the substituted calculator.',
        );
    }

    public function testCartItemAlsoUsesTheReplacedCalculator(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $country = $factory->country();

        $cartItem = new CartItem();
        $cartItem
            ->setCart($factory->cart())
            ->setProduct($product)
            ->setProductSaleElements($product->getDefaultSaleElements())
            ->setQuantity(1)
            ->setPrice('100')
            ->setPromoPrice('90')
            ->setPromo(0)
            ->save($this->getPropelConnection());

        self::assertSame(100.0, $cartItem->getTaxedPrice($country));

        $this->replaceCalculator();

        self::assertSame(self::SUBSTITUTED_TAXED_PRICE, $cartItem->getTaxedPrice($country));
    }

    public function testTaxRuleDetailAlsoUsesTheReplacedCalculator(): void
    {
        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule();
        $product = $factory->product($factory->category(), $taxRule, $factory->currency());
        $country = $factory->country();

        self::assertSame(0, $taxRule->getTaxDetail($product, $country, 100.0, 90.0)->getCount());

        $this->replaceCalculator();

        $taxDetail = $taxRule->getTaxDetail($product, $country, 100.0, 90.0);

        self::assertSame(1, $taxDetail->getCount());
        self::assertSame('substituted', $taxDetail->getKey(0)->getTitle());
    }

    public function testFactoryIsAliasedAndHandsOutAFreshInstanceEveryTime(): void
    {
        $factory = $this->getService(TaxCalculatorFactoryInterface::class);

        $first = $factory->createTaxCalculator();
        $second = $factory->createTaxCalculator();

        self::assertInstanceOf(TaxCalculatorInterface::class, $first);
        self::assertNotSame($first, $second, 'A calculator holds the product it was loaded with: never share one.');
    }

    public function testShippedCalculatorRemainsUsableOnItsOwn(): void
    {
        self::assertInstanceOf(TaxCalculatorInterface::class, new Calculator());
    }

    private function replaceCalculator(): void
    {
        $this->listener = static function (TaxCalculatorEvent $event): void {
            $event->setTaxCalculator(new class implements TaxCalculatorInterface {
                public function load(Product $product, Country $country, ?State $state = null): static
                {
                    return $this;
                }

                public function loadTaxRule(TaxRule $taxRule, Country $country, Product $product, ?State $state = null): static
                {
                    return $this;
                }

                public function loadTaxRuleWithoutCountry(TaxRule $taxRule, Product $product): static
                {
                    return $this;
                }

                public function loadTaxRuleWithoutProduct(TaxRule $taxRule, Country $country, ?State $state = null): static
                {
                    return $this;
                }

                public function getTaxAmountFromUntaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null): int|float
                {
                    return TaxCalculatorReplacementTest::SUBSTITUTED_TAXED_PRICE - $untaxedPrice;
                }

                public function getTaxAmountFromTaxedPrice($taxedPrice): int|float
                {
                    return 0;
                }

                public function getTaxedPrice(float $untaxedPrice, ?OrderProductTaxCollection &$taxCollection = null, ?string $askedLocale = null): int|float
                {
                    if ($taxCollection instanceof OrderProductTaxCollection) {
                        $taxCollection->addTax(
                            (new OrderProductTax())
                                ->setTitle('substituted')
                                ->setDescription('')
                                ->setAmount((string) (TaxCalculatorReplacementTest::SUBSTITUTED_TAXED_PRICE - $untaxedPrice)),
                        );
                    }

                    return TaxCalculatorReplacementTest::SUBSTITUTED_TAXED_PRICE;
                }

                public function getUntaxedPrice($taxedPrice): int|float
                {
                    return $taxedPrice;
                }
            });
        };

        static::getContainer()->get('event_dispatcher')
            ->addListener(TheliaEvents::TAX_GET_CALCULATOR, $this->listener);
    }
}

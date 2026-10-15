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

namespace Thelia\Tests\Integration\Domain\Taxation\TaxEngine;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Taxation\TaxEngine\Calculator;
use Thelia\Domain\Taxation\TaxEngine\Exception\TaxEngineException;
use Thelia\Domain\Taxation\TaxEngine\OrderProductTaxCollection;
use Thelia\Domain\Taxation\TaxEngine\TaxType\FixAmountTaxType;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;
use Thelia\Model\Tax;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;

final class CalculatorTest extends IntegrationTestCase
{
    public function testTaxesOfTheSamePositionAreAllComputedOnTheUntaxedPrice(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load(
            $this->productTaxedBy($country, [1 => [$this->percentTax('10'), $this->percentTax('5')]]),
            $country,
        );
        self::assertEqualsWithDelta(115.0, $calculator->getTaxedPrice(100.0), 0.0001);
        self::assertEqualsWithDelta(100.0, $calculator->getUntaxedPrice(115.0), 0.0001);
    }

    public function testEachTaxAppliedIsListedWithItsTitleAndItsAmountInAFreshCollection(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load(
            $this->productTaxedBy($country, [1 => [$this->percentTax('10'), $this->percentTax('5')]]),
            $country,
        );

        $taxes = new OrderProductTaxCollection();
        $taxes->addTax((new OrderProductTax())->setTitle('Left over from another line')->setAmount('99'));

        self::assertEqualsWithDelta(115.0, $calculator->getTaxedPrice(100.0, $taxes, 'en_US'), 0.0001);

        $listed = [];
        foreach ($taxes as $tax) {
            $listed[] = [$tax->getTitle(), round((float) $tax->getAmount(), 4)];
        }
        sort($listed);
        self::assertSame([['VAT 10', 10.0], ['VAT 5', 5.0]], $listed);
    }

    public function testNoTaxCollectionIsBuiltForACallerThatAskedForNone(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load($this->productTaxedBy($country, [1 => [$this->percentTax('10')]]), $country);

        $noCollectionAsked = null;
        $calculator->getTaxedPrice(100.0, $noCollectionAsked);

        self::assertNull($noCollectionAsked);
    }

    public function testATaxOfALaterPositionIsComputedOnThePriceTheEarlierOnesAlreadyTaxed(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load(
            $this->productTaxedBy($country, [1 => [$this->percentTax('10')], 2 => [$this->percentTax('10')]]),
            $country,
        );

        self::assertEqualsWithDelta(121.0, $calculator->getTaxedPrice(100.0), 0.0001);
        self::assertEqualsWithDelta(100.0, $calculator->getUntaxedPrice(121.0), 0.0001);
    }

    public function testAConstantAmountIsAddedToThePriceAndTakenOffItAgain(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load(
            $this->productTaxedBy($country, [1 => [$this->constantTax('5')]]),
            $country,
        );

        self::assertEqualsWithDelta(105.0, $calculator->getTaxedPrice(100.0), 0.0001);
        self::assertEqualsWithDelta(100.0, $calculator->getUntaxedPrice(105.0), 0.0001);
    }

    public function testAConstantAmountOfALaterPositionIsTakenOffBeforeTheEarlierRates(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load(
            $this->productTaxedBy($country, [1 => [$this->percentTax('10')], 2 => [$this->constantTax('5')]]),
            $country,
        );

        self::assertEqualsWithDelta(115.0, $calculator->getTaxedPrice(100.0), 0.0001);
        self::assertEqualsWithDelta(100.0, $calculator->getUntaxedPrice(115.0), 0.0001);
    }

    public function testTheTaxAmountIsFoundFromEitherSideOfThePrice(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load($this->productTaxedBy($country, [1 => [$this->percentTax('10')]]), $country);

        self::assertEqualsWithDelta(10.0, $calculator->getTaxAmountFromUntaxedPrice(100.0), 0.0001);
        self::assertEqualsWithDelta(10.0, $calculator->getTaxAmountFromTaxedPrice(110.0), 0.0001);
    }

    public function testConstantAmountsOfTheSamePositionAddUp(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load(
            $this->productTaxedBy($country, [1 => [$this->constantTax('5'), $this->constantTax('3')]]),
            $country,
        );

        self::assertEqualsWithDelta(108.0, $calculator->getTaxedPrice(100.0), 0.0001);
        self::assertEqualsWithDelta(100.0, $calculator->getUntaxedPrice(108.0), 0.0001);
    }

    /**
     * @return iterable<string, array{\Closure(Calculator): mixed, int}>
     */
    public static function callsMissingWhatTheyNeed(): iterable
    {
        $product = static fn (): Product => (new Product())->setId(1);
        $country = static fn (): Country => (new Country())->setId(1);
        $taxRule = static fn (): TaxRule => (new TaxRule())->setId(1);

        yield 'load, product never saved' => [
            static fn (Calculator $calculator) => $calculator->load(new Product(), $country()),
            TaxEngineException::UNDEFINED_PRODUCT,
        ];
        yield 'load, country never saved' => [
            static fn (Calculator $calculator) => $calculator->load($product(), new Country()),
            TaxEngineException::UNDEFINED_COUNTRY,
        ];
        yield 'loadTaxRule, tax rule never saved' => [
            static fn (Calculator $calculator) => $calculator->loadTaxRule(new TaxRule(), $country(), $product()),
            TaxEngineException::UNDEFINED_TAX_RULE,
        ];
        yield 'loadTaxRule, country never saved' => [
            static fn (Calculator $calculator) => $calculator->loadTaxRule($taxRule(), new Country(), $product()),
            TaxEngineException::UNDEFINED_COUNTRY,
        ];
        yield 'loadTaxRule, product never saved' => [
            static fn (Calculator $calculator) => $calculator->loadTaxRule($taxRule(), $country(), new Product()),
            TaxEngineException::UNDEFINED_PRODUCT,
        ];
        yield 'loadTaxRuleWithoutCountry, tax rule never saved' => [
            static fn (Calculator $calculator) => $calculator->loadTaxRuleWithoutCountry(new TaxRule(), $product()),
            TaxEngineException::UNDEFINED_TAX_RULE,
        ];
        yield 'loadTaxRuleWithoutCountry, product never saved' => [
            static fn (Calculator $calculator) => $calculator->loadTaxRuleWithoutCountry($taxRule(), new Product()),
            TaxEngineException::UNDEFINED_PRODUCT,
        ];
        yield 'loadTaxRuleWithoutProduct, tax rule never saved' => [
            static fn (Calculator $calculator) => $calculator->loadTaxRuleWithoutProduct(new TaxRule(), $country()),
            TaxEngineException::UNDEFINED_TAX_RULE,
        ];
        yield 'loadTaxRuleWithoutProduct, country never saved' => [
            static fn (Calculator $calculator) => $calculator->loadTaxRuleWithoutProduct($taxRule(), new Country()),
            TaxEngineException::UNDEFINED_COUNTRY,
        ];
        yield 'getTaxedPrice, nothing loaded' => [
            static fn (Calculator $calculator) => $calculator->getTaxedPrice(100.0),
            TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION,
        ];
        yield 'getUntaxedPrice, nothing loaded' => [
            static fn (Calculator $calculator) => $calculator->getUntaxedPrice(100.0),
            TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION,
        ];
    }

    /**
     * @param \Closure(Calculator): mixed $call
     */
    #[DataProvider('callsMissingWhatTheyNeed')]
    public function testACallMissingWhatItNeedsIsRefused(\Closure $call, int $expectedCode): void
    {
        $this->expectException(TaxEngineException::class);
        $this->expectExceptionCode($expectedCode);

        $call(new Calculator());
    }

    public function testALoadingWithoutCountryThatFailsForgetsTheRulesLoadedBefore(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load($this->productTaxedBy($country, [1 => [$this->percentTax('10')]]), $country);
        self::assertEqualsWithDelta(110.0, $calculator->getTaxedPrice(100.0), 0.0001);

        try {
            $calculator->loadTaxRuleWithoutCountry(new TaxRule(), (new Product())->setId(1));
            self::fail('A tax rule never saved must be refused.');
        } catch (TaxEngineException) {
        }

        $this->expectException(TaxEngineException::class);
        $this->expectExceptionCode(TaxEngineException::UNDEFINED_TAX_RULES_COLLECTION);

        $calculator->getTaxedPrice(100.0);
    }

    public function testAnUntaxedPriceAskedForSomethingThatIsNotANumberIsRefused(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load($this->productTaxedBy($country, [1 => [$this->percentTax('10')]]), $country);

        $this->expectException(TaxEngineException::class);
        $this->expectExceptionCode(TaxEngineException::BAD_AMOUNT_FORMAT);

        $calculator->getUntaxedPrice('ten');
    }

    public function testAnUntaxedPriceCannotBeFoundWhenNoTaxAppliesInTheCountry(): void
    {
        $country = $this->country();
        $calculator = (new Calculator())->load($this->productTaxedBy($country, []), $country);

        $this->expectException(TaxEngineException::class);
        $this->expectExceptionCode(TaxEngineException::NO_TAX_IN_TAX_RULES_COLLECTION);

        $calculator->getUntaxedPrice(100.0);
    }

    public function testAnOrderWithoutDiscountHasANeutralTaxFactor(): void
    {
        $order = $this->orderWithLines('0.000000', [['100.000000', '20.000000']]);

        self::assertSame(1.0, (new Calculator())->computeOrderTaxFactor($order));
    }

    public function testADiscountOfOneUnitIsSpreadOverTheTaxRateOfTheOrder(): void
    {
        // 1.00 is a discount like any other: only 0.00 means the order has none.
        $order = $this->orderWithLines('1.000000', [['100.000000', '20.000000']]);

        self::assertEqualsWithDelta(1.2, (new Calculator())->computeOrderTaxFactor($order), 0.0001);
        self::assertEqualsWithDelta(1 / 1.2, (new Calculator())->computeUntaxedOrderDiscount($order), 0.0001);
    }

    public function testTheOrderTaxFactorAveragesTheRatesOfAllItsPricedLines(): void
    {
        // A line sold 1.00 counts like any other: only a line sold 0.00 is left out.
        $order = $this->orderWithLines('10.000000', [['1.000000', '0.200000'], ['1.000000', '0.055000']]);

        self::assertEqualsWithDelta((1.2 + 1.055) / 2, (new Calculator())->computeOrderTaxFactor($order), 0.0001);
    }

    public function testALineChargedNothingIsLeftOutOfTheOrderTaxFactor(): void
    {
        $order = $this->orderWithLines('10.000000', [['0.000000', '0.000000'], ['100.000000', '20.000000']]);

        self::assertEqualsWithDelta(1.2, (new Calculator())->computeOrderTaxFactor($order), 0.0001);
    }

    public function testADiscountedOrderWithNoTaxLineKeepsItsDiscount(): void
    {
        $order = $this->orderWithLines('10.000000', [['100.000000', null]]);

        self::assertSame(1.0, (new Calculator())->computeOrderTaxFactor($order));
        self::assertEqualsWithDelta(10.0, (new Calculator())->computeUntaxedOrderDiscount($order), 0.0001);
    }

    public function testACartWithoutDiscountHasANeutralTaxFactor(): void
    {
        $country = $this->country();
        $cart = $this->cartOf($country, '0.000000', ['20']);

        self::assertSame(1.0, (new Calculator())->computeCartTaxFactor($cart, $country));
    }

    public function testADiscountOfOneUnitIsSpreadOverTheTaxRateOfTheCart(): void
    {
        $country = $this->country();
        // 1.00 is a discount like any other: only 0.00 means the cart has none.
        $cart = $this->cartOf($country, '1.000000', ['20']);

        self::assertEqualsWithDelta(1.2, (new Calculator())->computeCartTaxFactor($cart, $country), 0.0001);
        self::assertEqualsWithDelta(1 / 1.2, (new Calculator())->computeUntaxedCartDiscount($cart, $country), 0.0001);
    }

    public function testTheCartTaxFactorAveragesTheRatesOfItsItems(): void
    {
        $country = $this->country();
        $cart = $this->cartOf($country, '10.000000', ['20', '5.5']);

        self::assertEqualsWithDelta((1.2 + 1.055) / 2, (new Calculator())->computeCartTaxFactor($cart, $country), 0.0001);
    }

    public function testTheStaticShortcutsWarnTheyAreDeprecated(): void
    {
        $country = $this->country();
        $cart = $this->cartOf($country, '0.000000', ['20']);
        $order = $this->orderWithLines('0.000000', [['100.000000', '20.000000']]);

        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            Calculator::getUntaxedCartDiscount($cart, $country);
            Calculator::getUntaxedOrderDiscount($order);
            Calculator::getOrderTaxFactor($order);
            Calculator::getCartTaxFactor($cart, $country);
        } finally {
            restore_error_handler();
        }

        foreach (['getUntaxedCartDiscount', 'getUntaxedOrderDiscount', 'getOrderTaxFactor', 'getCartTaxFactor'] as $method) {
            self::assertCount(
                1,
                array_filter($deprecations, static fn (string $message): bool => str_contains($message, 'Calculator::'.$method.'() is deprecated')),
                $method.'() must say it is deprecated.',
            );
        }
    }

    private function country(): Country
    {
        return $this->createFixtureFactory()->country(['isocode' => '999', 'isoalpha2' => 'XT', 'isoalpha3' => 'XTX']);
    }

    private function percentTax(string $percent): Tax
    {
        return $this->createFixtureFactory()->tax(['requirements' => ['percent' => $percent], 'title' => 'VAT '.$percent]);
    }

    private function constantTax(string $amount): Tax
    {
        return $this->createFixtureFactory()->tax([
            'type' => FixAmountTaxType::class,
            'requirements' => ['amount' => $amount],
            'title' => 'Eco-tax '.$amount,
        ]);
    }

    /**
     * @param array<int, list<Tax>> $taxesByPosition
     */
    private function productTaxedBy(Country $country, array $taxesByPosition): Product
    {
        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule(['isDefault' => false]);

        foreach ($taxesByPosition as $position => $taxes) {
            foreach ($taxes as $tax) {
                (new TaxRuleCountry())
                    ->setTaxRuleId($taxRule->getId())
                    ->setCountryId($country->getId())
                    ->setTaxId($tax->getId())
                    ->setPosition($position)
                    ->save($this->getPropelConnection());
            }
        }

        return $factory->product($factory->category(), $taxRule, $factory->currency());
    }

    /**
     * @param list<string> $percents one item per rate, each priced 100
     */
    private function cartOf(Country $country, string $discount, array $percents): Cart
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart();
        $cart->setDiscount($discount)->save($this->getPropelConnection());

        foreach ($percents as $percent) {
            $factory->cartItem(
                $cart,
                $this->productTaxedBy($country, [1 => [$this->percentTax($percent)]]),
                null,
                ['price' => '100.000000', 'promoPrice' => '100.000000'],
            );
        }

        return $cart;
    }

    /**
     * @param list<array{string, ?string}> $lines price and tax amount of each line, no tax line when null
     */
    private function orderWithLines(string $discount, array $lines): Order
    {
        $order = $this->createFixtureFactory()->order();
        $order->setDiscount($discount)->save($this->getPropelConnection());

        foreach ($lines as $index => [$price, $taxAmount]) {
            $orderProduct = new OrderProduct();
            $orderProduct
                ->setOrderId($order->getId())
                ->setProductRef('ref-'.$index)
                ->setProductSaleElementsRef('pse-ref-'.$index)
                ->setTitle('Line '.$index)
                ->setQuantity(1.0)
                ->setPrice($price)
                ->setWasNew(0)
                ->setWasInPromo(0)
                ->save($this->getPropelConnection());

            if (null === $taxAmount) {
                continue;
            }

            (new OrderProductTax())
                ->setOrderProductId($orderProduct->getId())
                ->setTitle('VAT')
                ->setDescription('')
                ->setAmount($taxAmount)
                ->save($this->getPropelConnection());
        }

        return $order;
    }
}

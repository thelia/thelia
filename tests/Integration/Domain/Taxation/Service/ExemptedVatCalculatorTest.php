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

namespace Thelia\Tests\Integration\Domain\Taxation\Service;

use Thelia\Domain\Checkout\Service\GiftWrappingProvider;
use Thelia\Domain\Taxation\Service\ExemptedVatCalculator;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactory;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\GiftWrapping;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;

/**
 * The VAT the buyer of an exempt cart accounts for himself, the figure frozen on
 * the invoice address.
 */
final class ExemptedVatCalculatorTest extends IntegrationTestCase
{
    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = $this->createFixtureFactory()->country(['isocode' => '996', 'isoalpha2' => 'XV', 'isoalpha3' => 'XVX']);
    }

    public function testAnItemInPromotionIsTaxedOnItsPromoPriceAndTheOthersOnTheirPrice(): void
    {
        $cart = $this->createFixtureFactory()->cart();
        $this->addItem($cart, '20', ['price' => '100.000000', 'promoPrice' => '50.000000', 'promo' => 1]);
        $this->addItem($cart, '20', ['price' => '100.000000', 'promoPrice' => '80.000000', 'promo' => 0]);

        self::assertSame(10.0 + 20.0, $this->forCart($cart));
    }

    public function testAVatOfLessThanOneUnitIsReportedAsItIs(): void
    {
        $cart = $this->createFixtureFactory()->cart();
        $this->addItem($cart, '20', ['price' => '1.000000', 'promoPrice' => '1.000000']);

        self::assertSame(0.2, $this->forCart($cart));
    }

    public function testTheVatOfADiscountedCartIsTheVatOfTheDiscountedBaseToTheCent(): void
    {
        $cart = $this->createFixtureFactory()->cart();
        $cart->setDiscount('3.330000')->save($this->getPropelConnection());
        $this->addItem($cart, '20', ['price' => '10.000000', 'promoPrice' => '10.000000']);

        // 2.00 of VAT on 10.00, less the share of the 3.33 discount: 1.334.
        self::assertSame(1.33, $this->forCart($cart));
    }

    public function testACartOfFreeItemsOwesOnlyThePostageVat(): void
    {
        $cart = $this->createFixtureFactory()->cart();
        $this->addItem($cart, '20', ['price' => '0.000000', 'promoPrice' => '0.000000']);

        self::assertSame(1.5, $this->forCart($cart, 1.5));
    }

    public function testTheGiftWrappingVatIsTheGapBetweenItsTaxedAndItsUntaxedPrice(): void
    {
        $cart = $this->createFixtureFactory()->cart();
        $wrapping = (new GiftWrapping())
            ->setCode('gift-box-vat')
            ->setPrice('2.450000')
            ->setTaxRuleId($this->taxRule('20')->getId())
            ->setActive(1);
        $wrapping->setLocale('en_US')->setTitle('Gift box');
        $wrapping->save($this->getPropelConnection());
        $cart->setGiftWrappingId($wrapping->getId())->save($this->getPropelConnection());

        // 2.45 taxed at 20 % is 2.94.
        self::assertSame(0.49, $this->forCart($cart));
    }

    public function testEachUnitIsRoundedBeforeTheLineIsTotalledByDefault(): void
    {
        $cart = $this->threeLinesOfThreeUnitsAtAReducedRate();

        // 1.02 taxed at 5.5 % is 1.0761: 3 x 1.08 - 3 x 1.02 on each of the three lines.
        self::assertSame(0.54, $this->forCart($cart));
    }

    public function testTheLineIsRoundedOnceWhenTheShopRoundsItsSums(): void
    {
        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);
        $cart = $this->threeLinesOfThreeUnitsAtAReducedRate();

        // 3 x 1.0761 is 3.2283, rounded to 3.23, less 3.06, on each of the three lines.
        self::assertSame(0.51, $this->forCart($cart));
    }

    private function forCart(Cart $cart, float $postageVat = 0.0): float
    {
        $taxCalculatorFactory = new TaxCalculatorFactory();

        return (new ExemptedVatCalculator($taxCalculatorFactory, new GiftWrappingProvider($taxCalculatorFactory)))
            ->forCart($cart, $this->country, null, $postageVat);
    }

    private function threeLinesOfThreeUnitsAtAReducedRate(): Cart
    {
        $cart = $this->createFixtureFactory()->cart();

        for ($line = 0; $line < 3; ++$line) {
            $this->addItem($cart, '5.5', ['price' => '1.020000', 'promoPrice' => '1.020000', 'quantity' => 3.0]);
        }

        return $cart;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function addItem(Cart $cart, string $percent, array $overrides): void
    {
        $this->createFixtureFactory()->cartItem($cart, $this->product($percent), null, $overrides);
    }

    private function product(string $percent): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $this->taxRule($percent), $factory->currency());
    }

    private function taxRule(string $percent): TaxRule
    {
        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule(['isDefault' => false]);
        $tax = $factory->tax(['requirements' => ['percent' => $percent], 'title' => 'VAT '.$percent]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($this->country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }
}

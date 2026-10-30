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

namespace Thelia\Tests\Integration\Domain\Pricing;

use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Pricing\BundleQuoter;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The quote of a composition is what the line of the cart would cost, taxed by the rule of the
 * bundle: 600 struck and 540 for two locks, 560 and 504 for none, at 20 % of tax.
 */
final class BundleQuoterTest extends IntegrationTestCase
{
    use BuildsBundles;

    public function testTheQuoteOfTheDefaultCompositionIsSettledAndTaxed(): void
    {
        $example = $this->exampleTaxedBundle();

        $quote = $this->quoter()->quote(
            (int) $example['bundle']->getId(),
            (int) $this->saleElementsOf($example['bundle'])->getId(),
            $this->composition($example, 0),
            Currency::getDefaultCurrency(),
        );

        self::assertNotNull($quote);
        self::assertEqualsWithDelta(504.0, $quote->untaxedPrice, 0.001);
        self::assertEqualsWithDelta(604.8, $quote->taxedPrice, 0.001);
        self::assertEqualsWithDelta(560.0, $quote->untaxedFullPrice, 0.001);
        self::assertEqualsWithDelta(672.0, $quote->taxedFullPrice, 0.001);
        self::assertEqualsWithDelta(56.0, $quote->untaxedSaving, 0.001);
        self::assertEqualsWithDelta(67.2, $quote->taxedSaving, 0.001);
    }

    public function testTwoLocksMakeTheFiguresOfThePlan(): void
    {
        $example = $this->exampleTaxedBundle();

        $quote = $this->quoter()->quote(
            (int) $example['bundle']->getId(),
            (int) $this->saleElementsOf($example['bundle'])->getId(),
            $this->composition($example, 2),
            Currency::getDefaultCurrency(),
        );

        self::assertEqualsWithDelta(648.0, $quote->taxedPrice, 0.001);
        self::assertEqualsWithDelta(720.0, $quote->taxedFullPrice, 0.001);
        self::assertEqualsWithDelta(72.0, $quote->taxedSaving, 0.001);
    }

    public function testTheDiscountOfTheCustomerIsAppliedAsTheCartDoesIt(): void
    {
        $example = $this->exampleTaxedBundle();
        $customer = $this->createFixtureFactory()->customer($this->createFixtureFactory()->customerTitle());
        $customer->setDiscount('10')->save();

        $quote = $this->quoter()->quote(
            (int) $example['bundle']->getId(),
            (int) $this->saleElementsOf($example['bundle'])->getId(),
            $this->composition($example, 2),
            Currency::getDefaultCurrency(),
            $customer,
        );

        // 540 and 600, 10 % less each, then 20 % of tax.
        self::assertEqualsWithDelta(486.0, $quote->untaxedPrice, 0.001);
        self::assertEqualsWithDelta(583.2, $quote->taxedPrice, 0.001);
        self::assertEqualsWithDelta(648.0, $quote->taxedFullPrice, 0.001);
        self::assertEqualsWithDelta(64.8, $quote->taxedSaving, 0.001);
    }

    public function testABundleWithoutDiscountStrikesNothingThrough(): void
    {
        $example = $this->exampleTaxedBundle('0');

        $quote = $this->quoter()->quote(
            (int) $example['bundle']->getId(),
            (int) $this->saleElementsOf($example['bundle'])->getId(),
            $this->composition($example, 2),
            Currency::getDefaultCurrency(),
        );

        self::assertEqualsWithDelta(720.0, $quote->taxedPrice, 0.001);
        self::assertNull($quote->taxedFullPrice);
        self::assertNull($quote->taxedSaving);
        self::assertNull($quote->untaxedSaving);
    }

    public function testABundleInFixedModeHasNoQuote(): void
    {
        $factory = $this->createFixtureFactory();
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($factory, $service, BundlePriceMode::Fixed);

        self::assertNull($this->quoter()->quote(
            (int) $example['bundle']->getId(),
            (int) $this->saleElementsOf($example['bundle'])->getId(),
            $this->composition($example, 2),
            Currency::getDefaultCurrency(),
        ));
    }

    private function quoter(): BundleQuoter
    {
        return $this->getService(BundleQuoter::class);
    }

    /**
     * @return array{bundle: Product, bike: \Thelia\Model\ProductBundleItem, helmet: \Thelia\Model\ProductBundleItem, lock: \Thelia\Model\ProductBundleItem}
     */
    private function exampleTaxedBundle(string $discount = '10'): array
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class), BundlePriceMode::Computed, $discount);

        $taxRule = $factory->taxRule(['isDefault' => false]);
        $tax = $factory->tax(['requirements' => ['percent' => '20'], 'title' => 'VAT 20']);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save();
        $example['bundle']->setTaxRuleId($taxRule->getId())->save();

        return $example;
    }

    /**
     * @param array<string, mixed> $example
     */
    private function composition(array $example, int $locks): BundleComposition
    {
        return BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => $locks],
        ]);
    }
}

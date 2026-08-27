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

namespace Thelia\Tests\Integration\Api;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPrice;
use Thelia\Test\IntegrationTestCase;

/**
 * psesByProduct() feeds the variant selector of a product page with its prices.
 * It read the first price row of the sale element, whatever currency that row
 * held, so a shop browsed in a secondary currency quoted its variants in the
 * default one.
 */
final class PsePriceCurrencyTest extends IntegrationTestCase
{
    private const CURRENCY_CODE = 'TCU';

    private ProductSaleElementsAccessService $pseAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pseAccess = static::getContainer()->get(ProductSaleElementsAccessService::class);
    }

    public function testThePriceOfTheDefaultCurrencyIsConvertedToTheOneBeingBrowsed(): void
    {
        $product = $this->product(basePrice: 10.0);
        $this->browseIn($this->currency());

        $saleElements = $this->saleElementsOf($product);

        self::assertCount(1, $saleElements);
        self::assertSame(20.0, (float) $saleElements[0]['untaxedPrice']);
    }

    public function testThePriceSetForTheCurrencyIsAnsweredAsIs(): void
    {
        $product = $this->product(basePrice: 10.0);
        $currency = $this->currency();
        $this->browseIn($currency);

        $productPrice = new ProductPrice();
        $productPrice
            ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->setCurrencyId($currency->getId())
            ->setPrice('9.99')
            ->setPromoPrice('7.99')
            ->setFromDefaultCurrency(0)
            ->save($this->getPropelConnection());

        $saleElements = $this->saleElementsOf($product);

        self::assertCount(1, $saleElements);
        self::assertSame(9.99, (float) $saleElements[0]['untaxedPrice']);
        self::assertSame(7.99, (float) $saleElements[0]['promoUntaxedPrice']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function saleElementsOf(Product $product): array
    {
        return json_decode(
            (string) $this->pseAccess->psesByProduct($product->getId()),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }

    private function product(float $basePrice): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product(
            $factory->category(),
            $factory->taxRule(),
            Currency::getDefaultCurrency(),
            ['basePrice' => $basePrice],
        );
    }

    /**
     * Rated twice the default currency, whatever rate that one is given: the
     * suite shares the default currency and does not always leave it at 1.
     */
    private function currency(): Currency
    {
        return $this->createFixtureFactory()->currency([
            'code' => self::CURRENCY_CODE,
            'symbol' => 'T',
            'rate' => 2.0 * Currency::getDefaultCurrency()->getRate(),
            'visible' => 1,
        ]);
    }

    private function browseIn(Currency $currency): void
    {
        $session = static::getContainer()->get(RequestStack::class)->getCurrentRequest()?->getSession();

        self::assertInstanceOf(Session::class, $session);

        $session->setCurrency($currency);
    }
}

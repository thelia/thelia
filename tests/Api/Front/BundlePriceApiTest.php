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

use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The front API serves a bundle priced by its components at the price of its default composition:
 * 560 struck, 504 charged, the struck price shown.
 */
final class BundlePriceApiTest extends ApiTestCase
{
    use BuildsBundles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getService(PricingActivityChecker::class)->reset();
    }

    protected function tearDown(): void
    {
        $this->getService(PricingActivityChecker::class)->reset();

        parent::tearDown();
    }

    public function testTheSaleElementOfAComputedBundleIsPricedByItsComponents(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));

        $saleElement = $this->readProduct((int) $example['bundle']->getId())['productSaleElements'][0];

        self::assertTrue($saleElement['promo']);
        self::assertTrue($saleElement['displayInitialPrice']);
        self::assertSame(560.0, (float) $saleElement['productPrices'][0]['price']);
        self::assertSame(504.0, (float) $saleElement['productPrices'][0]['promoPrice']);
    }

    public function testABundleWithoutDiscountIsNotOnPromotion(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class), discount: '0');

        $saleElement = $this->readProduct((int) $example['bundle']->getId())['productSaleElements'][0];

        self::assertFalse($saleElement['promo']);
        self::assertFalse($saleElement['displayInitialPrice']);
        self::assertSame(560.0, (float) $saleElement['productPrices'][0]['price']);
    }

    public function testAFixedBundleKeepsThePriceOfItsSaleElement(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class), BundlePriceMode::Fixed);

        $saleElement = $this->readProduct((int) $example['bundle']->getId())['productSaleElements'][0];

        self::assertFalse($saleElement['promo']);
        self::assertSame(999.0, (float) $saleElement['productPrices'][0]['price']);
    }

    /**
     * @return array<string, mixed>
     */
    private function readProduct(int $productId): array
    {
        $response = $this->jsonRequest('GET', '/api/front/products/'.$productId);

        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}

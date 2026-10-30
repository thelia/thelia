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

namespace Thelia\Tests\Integration\Api\Service\DataAccess;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * What a theme reads of the sale elements of a product page: for a bundle, the quantity is the
 * number of bundles the components allow, whole.
 */
final class BundleStockAccessTest extends IntegrationTestCase
{
    use BuildsBundles;

    public function testTheQuantityOfABundleIsWhatItsComponentsAllow(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));

        $saleElements = $this->readSaleElements((int) $example['bundle']->getId());

        self::assertCount(1, $saleElements);
        self::assertSame(2, $saleElements[0]['quantity']);
    }

    public function testTheQuantityOfAProductThatIsNotABundleIsItsOwn(): void
    {
        $simple = $this->component($this->createFixtureFactory(), '12.5', 3);

        self::assertEquals(3, $this->readSaleElements((int) $simple->getId())[0]['quantity']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readSaleElements(int $productId): array
    {
        return json_decode(
            (string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($productId),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }
}

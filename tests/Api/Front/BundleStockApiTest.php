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
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The front read of the sale element of a bundle says how many bundles the components allow
 * (2 for the bundle of the plan); the admin read keeps the counter of the catalog, zero.
 */
final class BundleStockApiTest extends ApiTestCase
{
    use BuildsBundles;

    public function testTheFrontReadOfABundleSaleElementIsWhatTheComponentsAllow(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        $saleElementId = (int) $this->saleElementsOf($example['bundle'])->getId();

        $response = $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$saleElementId);

        self::assertJsonResponseSuccessful($response);
        self::assertSame(2, $this->quantityOf($response));
    }

    public function testTheFrontReadKeepsTheQuantityOfAProductThatIsNotABundle(): void
    {
        $factory = $this->createFixtureFactory();
        $this->exampleBundle($factory, $this->getService(BundleCompositionService::class));
        $simple = $this->component($factory, '12.5', 3);

        $response = $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$this->saleElementsOf($simple)->getId());

        self::assertJsonResponseSuccessful($response);
        self::assertSame(3, $this->quantityOf($response));
    }

    public function testTheAdminReadKeepsTheCounterOfTheCatalog(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));

        $response = $this->jsonRequest('GET', '/api/admin/product_sale_elements/'.$this->saleElementsOf($example['bundle'])->getId(), token: $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);
        self::assertSame(0, $this->quantityOf($response));
    }

    private function quantityOf(\Symfony\Component\HttpFoundation\Response $response): int
    {
        return (int) json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['quantity'];
    }
}

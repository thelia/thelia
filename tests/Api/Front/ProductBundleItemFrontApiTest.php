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
 * What a visitor is told of the components of a bundle: the ones it can buy, and nothing else.
 */
final class ProductBundleItemFrontApiTest extends ApiTestCase
{
    use BuildsBundles;

    public function testTheComponentsOfABundleAreListedWithoutAToken(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));

        $response = $this->jsonRequest('GET', '/api/front/product_bundle_items?bundleProduct.id='.$example['bundle']->getId());

        self::assertJsonResponseSuccessful($response);
        self::assertJsonCollectionHasCount(3, $response);
        $first = self::decodeJson($response)['hydra:member'][0];
        self::assertArrayNotHasKey('createdAt', $first);
        self::assertSame(1.0, (float) $first['quantity']);
    }

    public function testAComponentTakenOffLineIsHidden(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        $example['lockProduct']->setVisible(0)->save();

        $response = $this->jsonRequest('GET', '/api/front/product_bundle_items?bundleProduct.id='.$example['bundle']->getId());

        self::assertJsonCollectionHasCount(2, $response);
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/product_bundle_items/'.$example['lock']->getId())->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/product_bundle_items/'.$example['bike']->getId())->getStatusCode());
    }

    public function testAComponentWhoseSaleElementIsOfflineIsHidden(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        $this->saleElementsOf($example['helmetProduct'])->setVisible(false)->save();

        $response = $this->jsonRequest('GET', '/api/front/product_bundle_items?bundleProduct.id='.$example['bundle']->getId());

        self::assertJsonCollectionHasCount(2, $response);
    }

    public function testAnOrphanedComponentIsHidden(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        $example['helmetProduct']->delete();

        $response = $this->jsonRequest('GET', '/api/front/product_bundle_items?bundleProduct.id='.$example['bundle']->getId());

        self::assertJsonCollectionHasCount(2, $response);
    }

    public function testTheComponentsOfAnOfflineBundleAreHidden(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));
        $example['bundle']->setVisible(0)->save();

        $response = $this->jsonRequest('GET', '/api/front/product_bundle_items?bundleProduct.id='.$example['bundle']->getId());

        self::assertJsonCollectionHasCount(0, $response);
    }

    public function testTheFrontDoesNotWrite(): void
    {
        $example = $this->exampleBundle($this->createFixtureFactory(), $this->getService(BundleCompositionService::class));

        foreach (['POST' => '/api/front/product_bundle_items', 'DELETE' => '/api/front/product_bundle_items/'.$example['bike']->getId(), 'PATCH' => '/api/front/product_bundle_items/'.$example['bike']->getId()] as $method => $uri) {
            self::assertContains($this->jsonRequest($method, $uri, [], null, 'PATCH' === $method ? 'merge-patch+json' : 'jsonld')->getStatusCode(), [404, 405], $method);
        }
    }
}

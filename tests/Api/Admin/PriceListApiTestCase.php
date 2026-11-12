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

namespace Thelia\Tests\Api\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\PriceList;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ApiTestCase;

/**
 * The shared set-up of the administration API tests of the price lists and the assortments.
 */
abstract class PriceListApiTestCase extends ApiTestCase
{
    private static int $sequence = 0;

    protected function priceList(array $overrides = []): PriceList
    {
        $factory = $this->createFixtureFactory();
        $priceList = (new PriceList())
            ->setCode($overrides['code'] ?? 'PRO-'.(++self::$sequence).'-'.bin2hex(random_bytes(3)))
            ->setCurrencyId($factory->currency()->getId());
        $priceList->setLocale('en_US')->setTitle('Resellers');
        $priceList->save();

        return $priceList;
    }

    protected function saleElement(): ProductSaleElements
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency())->getDefaultSaleElements();
    }

    protected function pricingAdminToken(array $accesses = [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE]): string
    {
        return $this->authenticateAsAdmin($this->createFixtureFactory()->restrictedAdmin([AdminResources::PRICING => $accesses]));
    }

    protected function catalogOnlyAdminToken(): string
    {
        return $this->authenticateAsAdmin($this->createFixtureFactory()->restrictedAdmin([
            AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE],
            AdminResources::CUSTOMER_GROUP => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response, int $expectedStatus = 200): array
    {
        self::assertSame($expectedStatus, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string> the paths of the violations of a 422
     */
    protected function violationPaths(Response $response): array
    {
        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return array_values(array_map(static fn (array $violation): string => $violation['propertyPath'], $body['violations'] ?? []));
    }
}

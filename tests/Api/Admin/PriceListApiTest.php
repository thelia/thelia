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

use Thelia\Core\Security\AccessManager;
use Thelia\Model\PriceListQuery;

final class PriceListApiTest extends PriceListApiTestCase
{
    public function testAListIsCreatedReadBackUpdatedAndDeleted(): void
    {
        $token = $this->authenticateAsAdmin();
        $currency = $this->createFixtureFactory()->currency();

        $created = $this->decode($this->jsonRequest('POST', '/api/admin/price_lists', [
            'code' => 'PRO-2026',
            'currency' => '/api/admin/currencies/'.$currency->getId(),
            'priority' => 10,
            'exclusive' => true,
            'startsAt' => '2026-01-01T00:00:00+00:00',
            'endsAt' => '2026-12-31T00:00:00+00:00',
            'i18ns' => ['en_US' => ['title' => 'Resellers'], 'fr_FR' => ['title' => 'Revendeurs']],
        ], $token), 201);

        $row = PriceListQuery::create()->findPk($created['id']);
        self::assertSame('PRO-2026', $row->getCode());
        self::assertSame(10, $row->getPriority());
        self::assertSame(1, $row->getExclusive());
        self::assertSame(1, $row->getActive(), 'a list is active unless it says otherwise');
        self::assertSame($currency->getId(), $row->getCurrencyId());
        self::assertSame('Revendeurs', $row->setLocale('fr_FR')->getTitle());

        $read = $this->decode($this->jsonRequest('GET', '/api/admin/price_lists/'.$created['id'], token: $token));
        self::assertSame('PRO-2026', $read['code']);
        self::assertTrue($read['exclusive']);
        self::assertSame('Resellers', $read['i18ns']['en_US']['title']);

        $this->decode($this->jsonRequest('PATCH', '/api/admin/price_lists/'.$created['id'], ['priority' => 5, 'active' => false], $token, 'merge-patch+json'));
        $row = PriceListQuery::create()->findPk($created['id']);
        self::assertSame(5, $row->getPriority());
        self::assertSame(0, $row->getActive());
        self::assertSame('PRO-2026', $row->getCode());

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/price_lists/'.$created['id'], token: $token)->getStatusCode());
        self::assertNull(PriceListQuery::create()->findPk($created['id']));
    }

    public function testTheListsAreFilteredByCode(): void
    {
        $wanted = $this->priceList(['code' => 'FILTER-ME']);
        $this->priceList();
        $token = $this->authenticateAsAdmin();

        $collection = $this->decode($this->jsonRequest('GET', '/api/admin/price_lists?code=FILTER-ME', token: $token));

        $members = $collection['member'] ?? $collection['hydra:member'];
        self::assertCount(1, $members);
        self::assertSame($wanted->getId(), $members[0]['id']);
    }

    public function testAProfileWithoutThePricingRightIsRefusedEveryOperation(): void
    {
        $list = $this->priceList();
        $token = $this->catalogOnlyAdminToken();
        $currency = $this->createFixtureFactory()->currency();

        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/price_lists', token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/admin/price_lists/'.$list->getId(), token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/price_lists', ['code' => 'NOPE', 'currency' => '/api/admin/currencies/'.$currency->getId()], $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/admin/price_lists/'.$list->getId(), ['priority' => 1], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/admin/price_lists/'.$list->getId(), token: $token)->getStatusCode());
        self::assertNotNull(PriceListQuery::create()->findPk($list->getId()), 'a refused deletion leaves the list');
        self::assertNull(PriceListQuery::create()->findOneByCode('NOPE'));
    }

    public function testAReadOnlyPricingProfileReadsAndCannotWrite(): void
    {
        $list = $this->priceList();
        $token = $this->pricingAdminToken([AccessManager::VIEW]);

        self::assertSame(200, $this->jsonRequest('GET', '/api/admin/price_lists/'.$list->getId(), token: $token)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('PATCH', '/api/admin/price_lists/'.$list->getId(), ['priority' => 1], $token, 'merge-patch+json')->getStatusCode());
        self::assertSame(403, $this->jsonRequest('DELETE', '/api/admin/price_lists/'.$list->getId(), token: $token)->getStatusCode());
    }

    public function testACodeAlreadyUsedIsRefused(): void
    {
        $existing = $this->priceList(['code' => 'TAKEN']);
        $currency = $this->createFixtureFactory()->currency();
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/price_lists', ['code' => 'TAKEN', 'currency' => '/api/admin/currencies/'.$currency->getId()], $token);

        self::assertContains('code', $this->violationPaths($response));
        self::assertSame(1, PriceListQuery::create()->filterByCode('TAKEN')->count());

        $this->decode($this->jsonRequest('PATCH', '/api/admin/price_lists/'.$existing->getId(), ['priority' => 7], $token, 'merge-patch+json'));
    }

    public function testACodeThatIsNotAMachineNameIsRefused(): void
    {
        $currency = $this->createFixtureFactory()->currency();
        $token = $this->authenticateAsAdmin();

        foreach (['', 'two words', 'tag<b>', str_repeat('a', 65)] as $code) {
            $response = $this->jsonRequest('POST', '/api/admin/price_lists', ['code' => $code, 'currency' => '/api/admin/currencies/'.$currency->getId()], $token);
            self::assertContains('code', $this->violationPaths($response), 'refused: '.$code);
        }
    }

    public function testAListThatEndsBeforeItStartsIsRefused(): void
    {
        $currency = $this->createFixtureFactory()->currency();
        $token = $this->authenticateAsAdmin();

        foreach ([['2026-06-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00'], ['2026-06-01T00:00:00+00:00', '2026-06-01T00:00:00+00:00']] as [$start, $end]) {
            $response = $this->jsonRequest('POST', '/api/admin/price_lists', ['code' => 'DATES', 'currency' => '/api/admin/currencies/'.$currency->getId(), 'startsAt' => $start, 'endsAt' => $end], $token);
            self::assertContains('endsAt', $this->violationPaths($response));
        }

        self::assertNull(PriceListQuery::create()->findOneByCode('DATES'));
    }

    public function testAListWithoutACurrencyIsRefused(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/price_lists', ['code' => 'NO-CURRENCY'], $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(PriceListQuery::create()->findOneByCode('NO-CURRENCY'));
    }

    /**
     * A negotiated price never leaves toward another customer: none of the four resources has a
     * route outside /api/admin.
     */
    public function testNoOperationOfThePriceListsIsExposedOutsideTheAdministration(): void
    {
        $resources = ['PriceList', 'PriceListItem', 'PriceListAssignment', 'CatalogRestriction'];
        $leaks = [];

        foreach ($this->getService('router')->getRouteCollection() as $name => $route) {
            $class = $route->getDefault('_api_resource_class');

            if (\is_string($class) && \in_array(substr(strrchr($class, '\\'), 1), $resources, true) && !str_starts_with($route->getPath(), '/api/admin/')) {
                $leaks[] = $name.' '.$route->getPath();
            }
        }

        self::assertSame([], $leaks);
        self::assertNotSame(200, $this->jsonRequest('GET', '/api/front/price_lists')->getStatusCode());
        self::assertNotSame(200, $this->jsonRequest('GET', '/api/price_lists')->getStatusCode());
    }
}

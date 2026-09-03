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

namespace Thelia\Tests\Api\Pagination;

use Thelia\Test\ApiTestCase;

/**
 * Collections let the caller choose its page size, so the page size needs a
 * ceiling of its own: without one, a single anonymous call can ask the shop to
 * build and serialize the whole table at once.
 *
 * The total is reported from the count, not from the page, so capping the page
 * must not change what the collection says it holds.
 */
final class CollectionItemsPerPageCapTest extends ApiTestCase
{
    private const int CAP = 100;

    public function testAnOversizedPageRequestIsCappedWithoutChangingTheTotal(): void
    {
        $factory = $this->createFixtureFactory();
        for ($i = 0; $i < self::CAP + 5; ++$i) {
            $factory->customerTitle(['position' => (string) (1000 + $i)]);
        }

        $uncapped = $this->readCollection('/api/front/customer_titles?itemsPerPage=100000');
        $unpaged = $this->readCollection('/api/front/customer_titles');

        self::assertGreaterThan(self::CAP, $unpaged['hydra:totalItems']);
        self::assertSame($unpaged['hydra:totalItems'], $uncapped['hydra:totalItems']);
        self::assertLessThanOrEqual(self::CAP, \count($uncapped['hydra:member']));
    }

    public function testAPageSizeUnderTheCapIsHonoured(): void
    {
        $factory = $this->createFixtureFactory();
        for ($i = 0; $i < 12; ++$i) {
            $factory->customerTitle(['position' => (string) (2000 + $i)]);
        }

        $page = $this->readCollection('/api/front/customer_titles?itemsPerPage=7');

        self::assertCount(7, $page['hydra:member']);
    }

    /**
     * @return array{'hydra:member': array<int, mixed>, 'hydra:totalItems': int}
     */
    private function readCollection(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri);

        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}

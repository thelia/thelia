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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The status lookup by code is cached for the lifetime of the process, which is
 * what makes it cheap in a request and a liability everywhere else: a Messenger
 * worker keeps serving a status the merchant has since renamed, and a test keeps
 * the row a rolled-back transaction has already taken away.
 */
final class OrderReturnStatusQueryTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testTheCodeLookupIsCached(): void
    {
        $status = OrderReturnStatusQuery::create()->findOneByCodeCached(OrderReturnStatus::CODE_REQUESTED);

        self::assertNotNull($status);

        $status->setCode('requested-renamed')->save($this->getPropelConnection());

        self::assertSame(
            $status->getId(),
            OrderReturnStatusQuery::create()->findOneByCodeCached(OrderReturnStatus::CODE_REQUESTED)?->getId(),
            'The lookup is expected to be cached: without the cache this test proves nothing.',
        );
    }

    public function testTheCodeCacheCanBePurged(): void
    {
        $status = OrderReturnStatusQuery::create()->findOneByCodeCached(OrderReturnStatus::CODE_REQUESTED);

        self::assertNotNull($status);

        $status->setCode('requested-renamed')->save($this->getPropelConnection());

        OrderReturnStatusQuery::resetCache();

        self::assertNull(
            OrderReturnStatusQuery::create()->findOneByCodeCached(OrderReturnStatus::CODE_REQUESTED),
            'A purged cache has to go back to the database, where the code no longer exists.',
        );
        self::assertSame(
            $status->getId(),
            OrderReturnStatusQuery::create()->findOneByCodeCached('requested-renamed')?->getId(),
        );
    }
}

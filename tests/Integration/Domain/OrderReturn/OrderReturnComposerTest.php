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

namespace Thelia\Tests\Integration\Domain\OrderReturn;

use Thelia\Domain\OrderReturn\Service\OrderReturnComposer;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What a return being opened is worth.
 */
final class OrderReturnComposerTest extends IntegrationTestCase
{
    private OrderReturnComposer $composer;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->composer = $this->getService(OrderReturnComposer::class);
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        // ConfigQuery keeps a static cache the transaction rollback cannot reach.
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    /**
     * The postage is stored tax included: what carrying it back adds to the
     * refund is what the customer paid for it.
     */
    public function testTheReturnedPostageAddsWhatTheCustomerPaidForIt(): void
    {
        $order = $this->factory->order(null, [
            'statusCode' => OrderStatus::CODE_PAID,
            'postage' => '6.000000',
            'postageTax' => '1.000000',
        ]);

        self::assertSame(6.0, $this->composer->postageRefund($order));
    }
}

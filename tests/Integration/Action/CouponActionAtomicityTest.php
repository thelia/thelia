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

namespace Thelia\Tests\Integration\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Promotion\Coupon\Exception\InvalidCouponExclusionException;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The transaction the action opens is the real one: the test runs outside the
 * usual test transaction, because inside it a rollback of the inner level
 * changes nothing on disk.
 */
final class CouponActionAtomicityTest extends ActionIntegrationTestCase
{
    protected bool $useTransaction = false;

    private int $lastCouponIdBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lastCouponIdBefore = (int) CouponQuery::create()->orderById('desc')->findOne()?->getId();
    }

    protected function tearDown(): void
    {
        CouponQuery::create()->filterById($this->lastCouponIdBefore, Criteria::GREATER_THAN)->delete();

        parent::tearDown();
    }

    public function testARefusedExclusionLeavesTheCouponUnchanged(): void
    {
        $coupon = $this->factory->coupon(['code' => 'PSE-ATOMIC', 'priority' => 1]);

        $event = $this->newEvent('PSE-ATOMIC', title: 'Not saved')
            ->setPriority(9)
            ->setExcludedCouponIds([$coupon->getId()]);
        $event->setCouponModel($coupon);

        try {
            $this->dispatch($event, TheliaEvents::COUPON_UPDATE);
            self::fail('A promotion excluding itself must be refused.');
        } catch (InvalidCouponExclusionException) {
        }

        $reloaded = CouponQuery::create()->findOneByCode('PSE-ATOMIC');
        self::assertSame(1, $reloaded->getPriority());
    }

    private function newEvent(string $code, string $title = 'Promotion'): CouponCreateOrUpdateEvent
    {
        return new CouponCreateOrUpdateEvent(
            code: $code,
            serviceId: 'thelia.coupon.type.remove_x_amount',
            title: $title,
            effects: ['amount' => 10.0],
            shortDescription: '',
            description: '',
            isEnabled: true,
            expirationDate: new \DateTime('+1 month'),
            isAvailableOnSpecialOffers: false,
            isCumulative: false,
            isRemovingPostage: false,
            maxUsage: Coupon::UNLIMITED_COUPON_USE,
            locale: 'en_US',
            freeShippingForCountries: [],
            freeShippingForMethods: [],
            perCustomerUsageCount: false,
        );
    }
}

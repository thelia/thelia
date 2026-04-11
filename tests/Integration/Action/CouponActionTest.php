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

use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class CouponActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsCoupon(): void
    {
        $event = $this->newCreateEvent('NEW-CPN-1');

        $this->dispatch($event, TheliaEvents::COUPON_CREATE);

        $coupon = CouponQuery::create()->findOneByCode('NEW-CPN-1');
        self::assertNotNull($coupon);
        self::assertSame('10% summer promo', $coupon->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesTitleAndAmount(): void
    {
        $coupon = $this->factory->coupon(['code' => 'UPD-CPN', 'title' => 'Old Title']);

        $event = $this->newCreateEvent('UPD-CPN', ['amount' => 25.0], title: 'New Title');
        $event->setCouponModel($coupon);

        $this->dispatch($event, TheliaEvents::COUPON_UPDATE);

        $reloaded = CouponQuery::create()->findOneByCode('UPD-CPN');
        self::assertSame('New Title', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testDeleteRemovesCoupon(): void
    {
        $coupon = $this->factory->coupon(['code' => 'DEL-CPN']);
        $couponId = $coupon->getId();

        $event = new CouponDeleteEvent($coupon);
        $this->dispatch($event, TheliaEvents::COUPON_DELETE);

        self::assertNull(CouponQuery::create()->findPk($couponId));
    }

    /**
     * @param array<string, mixed> $effects
     */
    private function newCreateEvent(
        string $code,
        array $effects = ['amount' => 10.0],
        string $title = '10% summer promo',
    ): CouponCreateOrUpdateEvent {
        return new CouponCreateOrUpdateEvent(
            code: $code,
            serviceId: 'thelia.coupon.type.remove_x_amount',
            title: $title,
            effects: $effects,
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

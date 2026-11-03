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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter;
use Thelia\Model\Coupon;
use Thelia\Model\CouponExclusionQuery;
use Thelia\Model\CouponQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class CouponPriorityStopExclusionActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsThePriorityTheStopAndTheExclusions(): void
    {
        $other = $this->factory->coupon()->getId();

        $event = $this->newEvent('PSE-CREATE')
            ->setPriority(7)
            ->setStopProcessing(true)
            ->setExcludedCouponIds([$other]);

        $this->dispatch($event, TheliaEvents::COUPON_CREATE);

        $coupon = CouponQuery::create()->findOneByCode('PSE-CREATE');
        self::assertSame(7, $coupon->getPriority());
        self::assertTrue($coupon->getStopProcessing());
        self::assertSame([$other], CouponExclusionQuery::create()->excludedCouponIdsFor($coupon->getId()));
        self::assertSame([$coupon->getId()], CouponExclusionQuery::create()->excludedCouponIdsFor($other));
    }

    public function testUpdateWithoutThemLeavesThePriorityTheStopAndTheExclusionsUntouched(): void
    {
        $other = $this->factory->coupon()->getId();
        $coupon = $this->factory->coupon(['code' => 'PSE-KEEP', 'priority' => 4, 'stopProcessing' => true]);
        $this->getService(CouponExclusionWriter::class)->replace($coupon->getId(), [$other]);

        $event = $this->newEvent('PSE-KEEP', title: 'Renamed');
        $event->setCouponModel($coupon);
        $this->dispatch($event, TheliaEvents::COUPON_UPDATE);

        $reloaded = CouponQuery::create()->findOneByCode('PSE-KEEP');
        self::assertSame('Renamed', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame(4, $reloaded->getPriority());
        self::assertTrue($reloaded->getStopProcessing());
        self::assertSame([$other], CouponExclusionQuery::create()->excludedCouponIdsFor($coupon->getId()));
    }

    public function testUpdateWithAnEmptyListRemovesTheExclusions(): void
    {
        $other = $this->factory->coupon()->getId();
        $coupon = $this->factory->coupon(['code' => 'PSE-CLEAR']);
        $this->getService(CouponExclusionWriter::class)->replace($coupon->getId(), [$other]);

        $event = $this->newEvent('PSE-CLEAR')->setExcludedCouponIds([]);
        $event->setCouponModel($coupon);
        $this->dispatch($event, TheliaEvents::COUPON_UPDATE);

        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($coupon->getId()));
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($other));
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

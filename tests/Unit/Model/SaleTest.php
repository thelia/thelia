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

namespace Thelia\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Thelia\Model\Sale;

/**
 * The two questions the whole reserved-sales feature asks of a sale row:
 * who is it open to, and does its countdown show right now.
 */
final class SaleTest extends TestCase
{
    public function testASaleOpenToEveryoneIsNotReserved(): void
    {
        $sale = new Sale();

        // A row created before the column existed reads the default.
        self::assertSame(Sale::AUDIENCE_MODE_PUBLIC, $sale->getAudienceMode());
        self::assertFalse($sale->isReserved());
    }

    public function testASaleTargetingNamedCustomersIsReserved(): void
    {
        $sale = (new Sale())->setAudienceMode(Sale::AUDIENCE_MODE_CUSTOMERS);

        self::assertTrue($sale->isReserved());
    }

    /**
     * Customer groups are US #122; the mode is already reserved so that a group
     * operation never writes a public price the day the groups land.
     */
    public function testASaleTargetingCustomerGroupsIsReserved(): void
    {
        $sale = (new Sale())->setAudienceMode(Sale::AUDIENCE_MODE_CUSTOMER_GROUPS);

        self::assertTrue($sale->isReserved());
    }

    public function testNoCountdownIsDisplayedWhenTheModeIsNone(): void
    {
        $sale = (new Sale())
            ->setCountdownMode(Sale::COUNTDOWN_MODE_NONE)
            ->setEndDate(new \DateTime('2026-01-10 12:00:00'));

        self::assertFalse($sale->shouldDisplayCountdown(new \DateTime('2026-01-10 11:00:00')));
    }

    public function testTheCountdownFromTheOpeningIsDisplayedAtOnce(): void
    {
        $sale = (new Sale())
            ->setCountdownMode(Sale::COUNTDOWN_MODE_FROM_OPENING)
            ->setStartDate(new \DateTime('2026-01-01 00:00:00'))
            ->setEndDate(new \DateTime('2026-01-10 12:00:00'));

        self::assertTrue($sale->shouldDisplayCountdown(new \DateTime('2026-01-01 00:00:01')));
    }

    /**
     * The lead-hours mode has a threshold: nothing before it, the countdown from it on.
     */
    public function testTheLeadHoursCountdownStaysHiddenBeforeItsThreshold(): void
    {
        $sale = $this->saleEndingWithLeadHours(48);

        self::assertFalse($sale->shouldDisplayCountdown(new \DateTime('2026-01-08 11:59:59')));
    }

    public function testTheLeadHoursCountdownAppearsOnItsThreshold(): void
    {
        $sale = $this->saleEndingWithLeadHours(48);

        self::assertTrue($sale->shouldDisplayCountdown(new \DateTime('2026-01-08 12:00:00')));
        self::assertTrue($sale->shouldDisplayCountdown(new \DateTime('2026-01-10 11:00:00')));
    }

    /**
     * A countdown counts down to something. Without an end date there is nothing
     * to count to, whatever the mode says.
     */
    public function testNoCountdownIsDisplayedWithoutAnEndDate(): void
    {
        $now = new \DateTime('2026-01-08 12:00:00');

        $fromOpening = (new Sale())->setCountdownMode(Sale::COUNTDOWN_MODE_FROM_OPENING);
        self::assertFalse($fromOpening->shouldDisplayCountdown($now));

        $leadHours = (new Sale())
            ->setCountdownMode(Sale::COUNTDOWN_MODE_LEAD_HOURS)
            ->setCountdownLeadHours(48);
        self::assertFalse($leadHours->shouldDisplayCountdown($now));
    }

    /**
     * The lead-hours mode without a number of hours has no threshold to compare
     * against; that is a misconfigured row, not a countdown that starts now.
     */
    public function testTheLeadHoursCountdownStaysHiddenWithoutANumberOfHours(): void
    {
        $sale = (new Sale())
            ->setCountdownMode(Sale::COUNTDOWN_MODE_LEAD_HOURS)
            ->setEndDate(new \DateTime('2026-01-10 12:00:00'));

        self::assertFalse($sale->shouldDisplayCountdown(new \DateTime('2026-01-10 11:00:00')));
    }

    /**
     * An operation that has not opened yet counts down to nothing a visitor can
     * act on: the products are not on offer, so neither mode shows a countdown
     * before the start date.
     */
    public function testNoCountdownIsDisplayedBeforeTheOperationOpens(): void
    {
        $fromOpening = (new Sale())
            ->setCountdownMode(Sale::COUNTDOWN_MODE_FROM_OPENING)
            ->setStartDate(new \DateTime('2026-01-05 00:00:00'))
            ->setEndDate(new \DateTime('2026-01-10 12:00:00'));

        self::assertFalse($fromOpening->shouldDisplayCountdown(new \DateTime('2026-01-04 23:59:59')));
        self::assertTrue($fromOpening->shouldDisplayCountdown(new \DateTime('2026-01-05 00:00:00')));
    }

    /**
     * The lead-hours threshold can fall before the operation opens — a window
     * shorter than the lead time does exactly that. The start date wins.
     */
    public function testTheLeadHoursCountdownStaysHiddenBeforeTheOperationOpens(): void
    {
        $sale = $this->saleEndingWithLeadHours(48)
            ->setStartDate(new \DateTime('2026-01-09 00:00:00'));

        // Inside the 48-hour window of the end date, but the operation is not open yet.
        self::assertFalse($sale->shouldDisplayCountdown(new \DateTime('2026-01-08 13:00:00')));
        self::assertTrue($sale->shouldDisplayCountdown(new \DateTime('2026-01-09 00:00:00')));
    }

    /**
     * An operation with no start date has always been open, so nothing about the
     * countdown changes for it.
     */
    public function testAnOperationWithoutAStartDateCountsDownAsBefore(): void
    {
        $sale = (new Sale())
            ->setCountdownMode(Sale::COUNTDOWN_MODE_FROM_OPENING)
            ->setEndDate(new \DateTime('2026-01-10 12:00:00'));

        self::assertTrue($sale->shouldDisplayCountdown(new \DateTime('2026-01-01 00:00:00')));
    }

    private function saleEndingWithLeadHours(int $leadHours): Sale
    {
        return (new Sale())
            ->setCountdownMode(Sale::COUNTDOWN_MODE_LEAD_HOURS)
            ->setCountdownLeadHours($leadHours)
            ->setEndDate(new \DateTime('2026-01-10 12:00:00'));
    }
}

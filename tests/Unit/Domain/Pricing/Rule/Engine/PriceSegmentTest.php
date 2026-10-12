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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule\Engine;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\Rule\Engine\ChainResult;
use Thelia\Domain\Pricing\Rule\Engine\PriceSegment;

/**
 * A period includes its start and excludes its end: on the stroke of the opening the
 * rule price applies, on the stroke of the closing the period is over.
 */
final class PriceSegmentTest extends TestCase
{
    public function testAPeriodCoversTheInstantItStarts(): void
    {
        $january = $this->januarySegment();

        self::assertTrue($january->coversAt(new \DateTimeImmutable('2027-01-01 00:00:00')));
        self::assertFalse($january->coversAt(new \DateTimeImmutable('2026-12-31 23:59:59')));
    }

    public function testAPeriodIsOverTheInstantItEnds(): void
    {
        $january = $this->januarySegment();

        self::assertFalse($january->isOverAt(new \DateTimeImmutable('2027-01-31 23:59:59')));
        self::assertTrue($january->isOverAt(new \DateTimeImmutable('2027-02-01 00:00:00')));
    }

    public function testAPeriodWithoutAnEndIsNeverOver(): void
    {
        $segment = new PriceSegment(new \DateTimeImmutable('2027-01-01 00:00:00'), null, new ChainResult(80.0, 1, false, [1]));

        self::assertFalse($segment->isOverAt(new \DateTimeImmutable('2099-01-01 00:00:00')));
    }

    private function januarySegment(): PriceSegment
    {
        return new PriceSegment(
            new \DateTimeImmutable('2027-01-01 00:00:00'),
            new \DateTimeImmutable('2027-02-01 00:00:00'),
            new ChainResult(80.0, 1, false, [1]),
        );
    }
}

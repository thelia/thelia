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

namespace Thelia\Tests\Unit\Domain\Shipping\ZipCode;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Shipping\ZipCode\ZipCodePattern;
use Thelia\Domain\Shipping\ZipCode\ZipCodeRuleSet;

final class ZipCodeRuleSetTest extends TestCase
{
    public function testAZoneWithoutRuleAcceptsEveryCode(): void
    {
        self::assertTrue((new ZipCodeRuleSet())->accepts('20090'));
    }

    public function testAnExclusionRefusesTheCodesItMatchesOnly(): void
    {
        $rules = new ZipCodeRuleSet(exclusions: [ZipCodePattern::fromString('20*')]);

        self::assertFalse($rules->accepts('20090'), 'Corsica is excluded.');
        self::assertTrue($rules->accepts('69001'), 'Lyon is not.');
    }

    public function testInclusionsAcceptOnlyTheCodesTheyMatch(): void
    {
        $rules = new ZipCodeRuleSet(inclusions: [ZipCodePattern::fromString('75001...75020'), ZipCodePattern::fromString('92*')]);

        self::assertTrue($rules->accepts('75011'));
        self::assertTrue($rules->accepts('92100'));
        self::assertFalse($rules->accepts('93100'));
    }

    public function testAnExclusionWinsOverAnInclusion(): void
    {
        $rules = new ZipCodeRuleSet(
            inclusions: [ZipCodePattern::fromString('75*')],
            exclusions: [ZipCodePattern::fromString('75016')],
        );

        self::assertFalse($rules->accepts('75016'));
        self::assertTrue($rules->accepts('75015'));
    }

    public function testAnUnknownCodeIsAccepted(): void
    {
        $rules = new ZipCodeRuleSet(inclusions: [ZipCodePattern::fromString('75*')]);

        self::assertTrue($rules->accepts(null));
        self::assertTrue($rules->accepts(' '));
    }
}

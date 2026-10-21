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
use Thelia\Tests\Support\Log\SilencedTlog;

final class ZipCodeRuleSetTest extends TestCase
{
    use SilencedTlog;

    protected function setUp(): void
    {
        $this->silenceTlog();
    }

    protected function tearDown(): void
    {
        ini_restore('pcre.backtrack_limit');
        $this->restoreTlog();
    }

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

    /**
     * A pattern with * runs as a regular expression, which PCRE may give up on: an exclusion
     * then refuses the code instead of letting it through.
     */
    public function testAnExclusionThatCannotBeCheckedRefusesTheCode(): void
    {
        $rules = new ZipCodeRuleSet(exclusions: [ZipCodePattern::fromString('2*0*1')]);
        self::assertTrue($rules->accepts('2001090'), 'The code does not match the exclusion.');

        ini_set('pcre.backtrack_limit', '1');

        self::assertFalse($rules->accepts('2001090'), 'An exclusion that cannot be checked refuses.');
    }

    /**
     * An inclusion PCRE gives up on does not take the code, even one it would match.
     */
    public function testAnInclusionThatCannotBeCheckedDoesNotTakeTheCode(): void
    {
        $rules = new ZipCodeRuleSet(inclusions: [ZipCodePattern::fromString('2*0*1')]);
        self::assertTrue($rules->accepts('2001091'), 'The code matches the inclusion.');

        ini_set('pcre.backtrack_limit', '1');

        self::assertFalse($rules->accepts('2001091'), 'An inclusion that cannot be checked does not take the code.');
    }

    /**
     * A postal code that is not valid UTF-8 cannot be normalised: it is not unknown, it is
     * uncertain, and the rules fail closed on it.
     */
    public function testAPostalCodeThatCannotBeReadIsUncertain(): void
    {
        $unreadable = "20\xC3\x28";

        self::assertTrue((new ZipCodeRuleSet())->accepts($unreadable), 'A zone without a rule takes every code.');
        self::assertFalse((new ZipCodeRuleSet(exclusions: [ZipCodePattern::fromString('75*')]))->accepts($unreadable), 'An exclusion refuses it.');
        self::assertFalse((new ZipCodeRuleSet(inclusions: [ZipCodePattern::fromString('20*')]))->accepts($unreadable), 'An inclusion does not take it.');
    }
}

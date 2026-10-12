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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleCreateEvent;
use Thelia\Domain\Pricing\Rule\CatalogPriceRuleDefinitionValidator;
use Thelia\Domain\Pricing\Rule\Exception\InvalidCatalogPriceRuleException;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Model\CatalogPriceRule;

/**
 * A rule definition is refused before it is stored, with a sentence the merchant can
 * act on, at the exact edges of what it may hold.
 */
final class CatalogPriceRuleDefinitionValidatorTest extends TestCase
{
    private CatalogPriceRuleDefinitionValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CatalogPriceRuleDefinitionValidator(new ScopeMaterializer([]));
    }

    public function testARuleWithAStartDateAndNoEndDateIsAccepted(): void
    {
        self::assertNull($this->refusalOf($this->percentageRule(20.0)->setStartDate(new \DateTimeImmutable('+1 day'))));
    }

    public function testARuleEndingWhenItStartsIsRefused(): void
    {
        $start = new \DateTimeImmutable('+1 day');

        self::assertSame(
            'The end date of a catalog price rule must come after its start date.',
            $this->refusalOf($this->percentageRule(20.0)->setStartDate($start)->setEndDate($start)),
        );
    }

    public function testAPercentageOfZeroIsRefused(): void
    {
        self::assertSame('A percentage rule needs a percentage between 0 and 100.', $this->refusalOf($this->percentageRule(0.0)));
    }

    public function testAPercentageOfAHundredIsAccepted(): void
    {
        self::assertNull($this->refusalOf($this->percentageRule(100.0)));
    }

    public function testAFixedPriceRuleWithoutAnyPriceIsRefusedWithItsOwnSentence(): void
    {
        $definition = $this->percentageRule(null)->setEffectType(CatalogPriceRule::EFFECT_TYPE_FIXED_PRICE);

        self::assertSame('A fixed price rule needs a price in at least one currency.', $this->refusalOf($definition));
    }

    public function testAFixedPriceOfZeroIsAccepted(): void
    {
        $definition = $this->percentageRule(null)
            ->setEffectType(CatalogPriceRule::EFFECT_TYPE_FIXED_PRICE)
            ->setEffectValuesByCurrency([1 => 0.0]);

        self::assertNull($this->refusalOf($definition));
    }

    private function percentageRule(?float $percentage): CatalogPriceRuleCreateEvent
    {
        return (new CatalogPriceRuleCreateEvent())
            ->setTitle('Winter')
            ->setEffectType(CatalogPriceRule::EFFECT_TYPE_PERCENTAGE)
            ->setPercentageValue($percentage)
            ->setAudienceMode(CatalogPriceRule::AUDIENCE_MODE_PUBLIC);
    }

    /**
     * The sentence the definition is refused with, or null when it is accepted; any
     * other failure is reported as such rather than taken for a refusal.
     */
    private function refusalOf(CatalogPriceRuleCreateEvent $definition): ?string
    {
        try {
            $this->validator->validate($definition);
        } catch (InvalidCatalogPriceRuleException $exception) {
            return $exception->getMessage();
        } catch (\Throwable $throwable) {
            return $throwable::class.': '.$throwable->getMessage();
        }

        return null;
    }
}

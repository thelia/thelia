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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule\Storage;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Pricing\Rule\Storage\RuleEffectFactory;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Currency;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The rules as the engine reads them in one currency: a percentage everywhere, an
 * amount or a fixed price only where the merchant typed one.
 */
final class RuleEffectFactoryTest extends ActionIntegrationTestCase
{
    private RuleEffectFactory $effectFactory;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->effectFactory = $this->getService(RuleEffectFactory::class);
        $this->currency = Currency::getDefaultCurrency();
    }

    public function testRulesFetchedAsACollectionAreRead(): void
    {
        $percentage = $this->factory->catalogPriceRule(['percentageValue' => 20.0]);
        $amount = $this->factory->catalogPriceRule(['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT]);
        $this->factory->catalogPriceRuleEffectCurrency($amount, $this->currency, 5.0);

        $rules = CatalogPriceRuleQuery::create()->filterById([$percentage->getId(), $amount->getId()], Criteria::IN)->find();
        $effects = $this->effectFactory->effectsIn($rules, $this->currency);

        self::assertEqualsCanonicalizing([$percentage->getId(), $amount->getId()], array_keys($effects));
        self::assertEqualsWithDelta(20.0, $effects[$percentage->getId()]->value, 0.000001);
        self::assertEqualsWithDelta(5.0, $effects[$amount->getId()]->value, 0.000001);
    }

    public function testEveryRuleAGeneratorYieldsIsReadWhateverItsKeys(): void
    {
        $first = $this->factory->catalogPriceRule(['percentageValue' => 10.0]);
        $second = $this->factory->catalogPriceRule(['percentageValue' => 30.0]);
        $chained = (static function () use ($first, $second): \Generator {
            yield from [$first];
            yield from [$second];
        })();

        $effects = $this->effectFactory->effectsIn($chained, $this->currency);

        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], array_keys($effects));
    }

    public function testARuleWithoutAValueInTheCurrencyDoesNotStopTheRulesAfterIt(): void
    {
        $withoutValue = $this->factory->catalogPriceRule(['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT]);
        $percentage = $this->factory->catalogPriceRule(['percentageValue' => 20.0]);

        $effects = $this->effectFactory->effectsIn([$withoutValue, $percentage], $this->currency);

        self::assertSame([$percentage->getId()], array_keys($effects));
    }
}

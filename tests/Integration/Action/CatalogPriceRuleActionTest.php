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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleCreateEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleDeleteEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleRecomputeEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleToggleActivityEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\Rule\Exception\InvalidCatalogPriceRuleException;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCriterionQuery;
use Thelia\Model\CatalogPriceRuleCustomerGroupQuery;
use Thelia\Model\CatalogPriceRuleCustomerQuery;
use Thelia\Model\CatalogPriceRuleEffectCurrencyQuery;
use Thelia\Model\CatalogPriceRulePriceQuery;
use Thelia\Model\CatalogPriceRuleProductSaleElementsQuery;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Writing a rule through its events: the definition lands in its four tables, the
 * scope is materialized and the stored prices follow, in the same request.
 */
final class CatalogPriceRuleActionTest extends ActionIntegrationTestCase
{
    private Currency $currency;

    private Category $category;

    private PublicPriceReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->factory->currency();
        $this->category = $this->factory->category();
        $this->reader = $this->getService(PublicPriceReader::class);
    }

    protected function tearDown(): void
    {
        ConfigQuery::write(RuleRepricer::INLINE_LIMIT_CONFIG, (string) RuleRepricer::DEFAULT_INLINE_LIMIT);

        parent::tearDown();
    }

    public function testCreatingARuleStoresItsDefinitionItsScopeAndItsPrices(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->factory->customer($this->factory->customerTitle());

        $event = $this->dispatch($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE);

        $rule = $event->getCatalogPriceRule();
        self::assertNotNull($rule);
        self::assertSame('Winter -20%', $rule->setLocale('en_US')->getTitle());
        self::assertTrue((bool) $rule->getActive());
        self::assertSame(1, CatalogPriceRuleCriterionQuery::create()->filterByCatalogPriceRuleId($rule->getId())->count());
        self::assertSame(0, CatalogPriceRuleCustomerQuery::create()->filterByCatalogPriceRuleId($rule->getId())->count(), 'a public rule keeps no audience');
        self::assertSame(0, CatalogPriceRuleEffectCurrencyQuery::create()->filterByCatalogPriceRuleId($rule->getId())->count(), 'a percentage has no per-currency value');

        $pse = $this->defaultPseFor($product);
        self::assertSame([$pse->getId()], $this->scopeOf($rule));
        self::assertEqualsWithDelta(80.0, $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()]->untaxedPrice, 0.000001);
        self::assertFalse((bool) $rule->getDirty());
        self::assertNotNull($rule->getComputedAt());

        // Never used here, and never written: the rule prices for everyone.
        self::assertSame(0, CatalogPriceRuleCustomerQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    public function testAnAmountRuleReservedForNamedCustomersStoresItsValuesAndItsAudienceButNoPublicPrice(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->factory->customer($this->factory->customerTitle());

        $rule = $this->dispatch($this->definition([
            'effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT,
            'effectValues' => [$this->currency->getId() => 12.5],
            'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS,
            'customerIds' => [$customer->getId()],
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();

        self::assertSame('12.500000', CatalogPriceRuleEffectCurrencyQuery::create()->filterByCatalogPriceRuleId($rule->getId())->findOne()->getValue());
        self::assertSame([$customer->getId()], array_map('intval', CatalogPriceRuleCustomerQuery::create()->filterByCatalogPriceRuleId($rule->getId())->select('CustomerId')->find()->getData()));
        self::assertSame([$this->defaultPseFor($product)->getId()], $this->scopeOf($rule));
        self::assertSame(0, CatalogPriceRulePriceQuery::create()->filterByCatalogPriceRuleId($rule->getId())->count());
    }

    /**
     * A rule reserved for customer groups is stored like a rule reserved for named
     * customers: no public price, the members of its groups get it at read time.
     */
    public function testARuleReservedForCustomerGroupsIsStoredWithoutAPublicPrice(): void
    {
        $product = $this->catalogProduct();
        $group = $this->factory->customerGroup();

        $rule = $this->dispatch($this->definition([
            'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS,
            'customerGroupIds' => [$group->getId()],
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();

        self::assertSame(CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS, (int) $rule->getAudienceMode());
        self::assertSame([$this->defaultPseFor($product)->getId()], $this->scopeOf($rule));
        self::assertSame(0, CatalogPriceRulePriceQuery::create()->filterByCatalogPriceRuleId($rule->getId())->count());
    }

    public function testARuleReservedForCustomerGroupsStoresItsGroupsAndSyncsThemOnUpdate(): void
    {
        $product = $this->catalogProduct();
        $first = $this->factory->customerGroup();
        $second = $this->factory->customerGroup();
        $customer = $this->factory->customer($this->factory->customerTitle());

        $rule = $this->dispatch($this->definition([
            'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS,
            'customerGroupIds' => [$first->getId(), $second->getId(), $first->getId()],
            'customerIds' => [$customer->getId()],
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();

        self::assertSame([$first->getId(), $second->getId()], $this->groupsOf($rule), 'the list is deduplicated');
        self::assertSame(0, CatalogPriceRuleCustomerQuery::create()->filterByCatalogPriceRuleId($rule->getId())->count(), 'named customers are only kept in their own mode');

        $this->dispatch($this->fill(new CatalogPriceRuleUpdateEvent($rule->getId()), [
            'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS,
            'customerGroupIds' => [$second->getId()],
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_UPDATE);

        self::assertSame([$second->getId()], $this->groupsOf($rule), 'a group taken out loses its row');
    }

    #[DataProvider('audiencesThatKeepNoGroup')]
    public function testLeavingTheGroupAudienceEmptiesTheGroupsEvenWhenTheEventStillCarriesThem(int $audienceMode): void
    {
        $product = $this->catalogProduct();
        $group = $this->factory->customerGroup();
        $customer = $this->factory->customer($this->factory->customerTitle());
        $criteria = [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]];

        $rule = $this->dispatch($this->definition([
            'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS,
            'customerGroupIds' => [$group->getId()],
            'criteria' => $criteria,
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();
        self::assertSame([$group->getId()], $this->groupsOf($rule));

        $this->dispatch($this->fill(new CatalogPriceRuleUpdateEvent($rule->getId()), [
            'audienceMode' => $audienceMode,
            'customerGroupIds' => [$group->getId()],
            'customerIds' => [$customer->getId()],
            'criteria' => $criteria,
        ]), TheliaEvents::CATALOG_PRICE_RULE_UPDATE);

        self::assertSame([], $this->groupsOf($rule));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function audiencesThatKeepNoGroup(): iterable
    {
        yield 'everyone' => [CatalogPriceRule::AUDIENCE_MODE_PUBLIC];
        yield 'named customers' => [CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS];
    }

    /**
     * The whole way: a rule reserved for a group, written through its event, prices the
     * members of the group and nobody else.
     */
    public function testARuleWrittenForAGroupPricesTheCustomerOfTheGroupOnly(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $group = $this->factory->customerGroup();
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $outsider = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $this->factory->customerGroup()]);

        $this->dispatch($this->definition([
            'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS,
            'customerGroupIds' => [$group->getId()],
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE);

        $context = $this->getService(CustomerPriceContextInterface::class);

        $memberPrice = $context->resolve([$pse->getId()], $this->currency, $member)[$pse->getId()];
        $outsiderPrice = $context->resolve([$pse->getId()], $this->currency, $outsider)[$pse->getId()];

        self::assertEqualsWithDelta(80.0, $memberPrice->untaxedPromoPrice, 0.000001);
        self::assertSame(EffectivePrice::SOURCE_RULE, $memberPrice->source);
        self::assertSame(ResolvedPrice::SOURCE_BASE, $outsiderPrice->source);
        self::assertFalse($outsiderPrice->isPromo);
    }

    public function testUpdatingARuleReplacesItsListsAndRepricesWhatLeftAndWhatCame(): void
    {
        $inCategory = $this->catalogProduct();
        $elsewhere = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0]);

        $rule = $this->dispatch($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();

        $update = new CatalogPriceRuleUpdateEvent($rule->getId());
        $this->fill($update, [
            'percentageValue' => 50.0,
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$elsewhere->getId()]],
        ]);
        $this->dispatch($update, TheliaEvents::CATALOG_PRICE_RULE_UPDATE);

        $left = $this->defaultPseFor($inCategory)->getId();
        $came = $this->defaultPseFor($elsewhere)->getId();
        self::assertSame([$came], $this->scopeOf($rule));
        self::assertSame([], $this->reader->currentPrices([$left], $this->currency));
        self::assertEqualsWithDelta(50.0, $this->reader->currentPrices([$came], $this->currency)[$came]->untaxedPrice, 0.000001);
        self::assertSame([CatalogPriceRule::CRITERION_PRODUCT], CatalogPriceRuleCriterionQuery::create()->filterByCatalogPriceRuleId($rule->getId())->select('Type')->find()->getData());
    }

    /**
     * Recette 6: turned off in the middle of its period, the rule gives the
     * products their price back at once.
     */
    public function testTurningARuleOffRemovesItsStoredPricesAndBackOnRestoresThem(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $rule = $this->dispatch($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();

        $this->dispatch(new CatalogPriceRuleToggleActivityEvent($rule->getId()), TheliaEvents::CATALOG_PRICE_RULE_TOGGLE_ACTIVITY);
        self::assertFalse((bool) CatalogPriceRuleQuery::create()->findPk($rule->getId())->getActive());
        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $this->currency));

        // Asking for the state already stored changes nothing: a link opened twice.
        $this->dispatch(new CatalogPriceRuleToggleActivityEvent($rule->getId(), false), TheliaEvents::CATALOG_PRICE_RULE_TOGGLE_ACTIVITY);
        self::assertFalse((bool) CatalogPriceRuleQuery::create()->findPk($rule->getId())->getActive());

        $this->dispatch(new CatalogPriceRuleToggleActivityEvent($rule->getId(), true), TheliaEvents::CATALOG_PRICE_RULE_TOGGLE_ACTIVITY);
        self::assertArrayHasKey($pse->getId(), $this->reader->currentPrices([$pse->getId()], $this->currency));
    }

    public function testDeletingARuleLeavesTheOtherRulesPricingWhatItCovered(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);

        $tenPercent = $this->dispatch($this->definition([
            'percentageValue' => 10.0,
            'priority' => 100,
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();
        $half = $this->dispatch($this->definition([
            'percentageValue' => 50.0,
            'priority' => 10,
            'stopProcessing' => true,
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();

        self::assertEqualsWithDelta(50.0, $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()]->untaxedPrice, 0.000001);

        $this->dispatch(new CatalogPriceRuleDeleteEvent($half->getId()), TheliaEvents::CATALOG_PRICE_RULE_DELETE);

        self::assertNull(CatalogPriceRuleQuery::create()->findPk($half->getId()));
        self::assertSame(0, CatalogPriceRuleProductSaleElementsQuery::create()->filterByCatalogPriceRuleId($half->getId())->count());
        $price = $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()];
        self::assertEqualsWithDelta(90.0, $price->untaxedPrice, 0.000001);
        self::assertSame($tenPercent->getId(), $price->ruleId);
    }

    public function testAChangeLargerThanTheInlineLimitLeavesTheRuleDirtyForTheCommand(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        ConfigQuery::write(RuleRepricer::INLINE_LIMIT_CONFIG, '0');

        $rule = $this->dispatch($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE)->getCatalogPriceRule();

        self::assertTrue((bool) $rule->getDirty());
        self::assertSame([$pse->getId()], $this->scopeOf($rule), 'the scope is written at once, only the prices wait');
        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $this->currency));

        ConfigQuery::write(RuleRepricer::INLINE_LIMIT_CONFIG, (string) RuleRepricer::DEFAULT_INLINE_LIMIT);
        $this->dispatch(new CatalogPriceRuleRecomputeEvent(), TheliaEvents::CATALOG_PRICE_RULE_RECOMPUTE);

        self::assertFalse((bool) CatalogPriceRuleQuery::create()->findPk($rule->getId())->getDirty());
        self::assertArrayHasKey($pse->getId(), $this->reader->currentPrices([$pse->getId()], $this->currency));
    }

    public function testARuleNamingAGroupThatDoesNotExistIsRefusedBeforeAnythingIsWritten(): void
    {
        $known = $this->factory->customerGroup();
        $unknownId = (int) $known->getId() + 9999;
        $rulesBefore = CatalogPriceRuleQuery::create()->count();

        try {
            $this->dispatch($this->definition([
                'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS,
                'customerGroupIds' => [$known->getId(), $unknownId],
            ]), TheliaEvents::CATALOG_PRICE_RULE_CREATE);
            self::fail('The creation was accepted.');
        } catch (InvalidCatalogPriceRuleException $exception) {
            self::assertStringContainsString((string) $unknownId, $exception->getMessage());
        }

        self::assertSame($rulesBefore, CatalogPriceRuleQuery::create()->count(), 'no rule was created');
    }

    #[DataProvider('refusedDefinitions')]
    public function testADefinitionThatSaysNothingIsRefused(array $overrides, string $messagePart): void
    {
        $this->expectException(InvalidCatalogPriceRuleException::class);
        $this->expectExceptionMessage($messagePart);

        $this->dispatch($this->definition($overrides), TheliaEvents::CATALOG_PRICE_RULE_CREATE);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedDefinitions(): iterable
    {
        yield 'no title' => [['title' => '  '], 'needs a title'];
        yield 'end before start' => [['startDate' => new \DateTime('2030-02-01'), 'endDate' => new \DateTime('2030-01-01')], 'end date'];
        yield 'percentage missing' => [['percentageValue' => null], 'between 0 and 100'];
        yield 'percentage above 100' => [['percentageValue' => 150.0], 'between 0 and 100'];
        yield 'amount without a currency' => [['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT, 'effectValues' => []], 'at least one currency'];
        yield 'negative fixed price' => [['effectType' => CatalogPriceRule::EFFECT_TYPE_FIXED_PRICE, 'effectValues' => [1 => -5.0]], 'cannot be negative'];
        yield 'named customers without a customer' => [['audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'customerIds' => []], 'at least one customer'];
        yield 'customer groups without a group' => [['audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS, 'customerGroupIds' => []], 'at least one customer group'];
        yield 'unknown audience' => [['audienceMode' => 9], 'must be everyone, named customers or customer groups'];
        yield 'unknown criterion type' => [['criteria' => ['moon_phase' => [1]]], 'moon_phase'];
    }

    private function definition(array $overrides = []): CatalogPriceRuleCreateEvent
    {
        return $this->fill(new CatalogPriceRuleCreateEvent(), $overrides);
    }

    /**
     * @template T of CatalogPriceRuleCreateEvent
     *
     * @param T $event
     *
     * @return T
     */
    private function fill(CatalogPriceRuleCreateEvent $event, array $overrides): CatalogPriceRuleCreateEvent
    {
        $event
            ->setLocale('en_US')
            ->setTitle($overrides['title'] ?? 'Winter -20%')
            ->setActive($overrides['active'] ?? true)
            ->setPriority($overrides['priority'] ?? 100)
            ->setStopProcessing($overrides['stopProcessing'] ?? false)
            ->setStartDate($overrides['startDate'] ?? null)
            ->setEndDate($overrides['endDate'] ?? null)
            ->setEffectType($overrides['effectType'] ?? CatalogPriceRule::EFFECT_TYPE_PERCENTAGE)
            ->setPercentageValue(\array_key_exists('percentageValue', $overrides) ? $overrides['percentageValue'] : 20.0)
            ->setEffectValuesByCurrency($overrides['effectValues'] ?? [])
            ->setAudienceMode($overrides['audienceMode'] ?? CatalogPriceRule::AUDIENCE_MODE_PUBLIC)
            ->setCustomerIds($overrides['customerIds'] ?? [])
            ->setCustomerGroupIds($overrides['customerGroupIds'] ?? [])
            ->setCriteria($overrides['criteria'] ?? []);

        return $event;
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product($this->category, $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0]);
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        return $pse;
    }

    /**
     * @return list<int>
     */
    private function groupsOf(CatalogPriceRule $rule): array
    {
        return array_map('intval', CatalogPriceRuleCustomerGroupQuery::create()
            ->filterByCatalogPriceRuleId($rule->getId())
            ->orderByCustomerGroupId()
            ->select('CustomerGroupId')
            ->find()
            ->getData());
    }

    /**
     * @return list<int>
     */
    private function scopeOf(CatalogPriceRule $rule): array
    {
        return array_map('intval', CatalogPriceRuleProductSaleElementsQuery::create()
            ->filterByCatalogPriceRuleId($rule->getId())
            ->select('ProductSaleElementsId')
            ->find()
            ->getData());
    }
}

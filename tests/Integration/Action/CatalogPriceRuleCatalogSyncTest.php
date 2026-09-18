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

use Thelia\Action\CatalogPriceRuleCatalogSync;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleProductSaleElementsQuery;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The scope and the stored prices follow the catalog: what the back office does to
 * a product, a category or a brand is felt by the rules in the same request.
 */
final class CatalogPriceRuleCatalogSyncTest extends ActionIntegrationTestCase
{
    private Currency $currency;

    private Category $winter;

    private Category $summer;

    private PublicPriceReader $reader;

    private RuleRepricer $repricer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = $this->factory->currency();
        $this->winter = $this->factory->category();
        $this->summer = $this->factory->category();
        $this->reader = $this->getService(PublicPriceReader::class);
        $this->repricer = $this->getService(RuleRepricer::class);
    }

    /**
     * Recette 2 of the ticket: a product filed in the Winter category on January
     * 15th enters the January rule without anything being retouched.
     */
    public function testAProductAddedToACategoryEntersTheRuleCoveringIt(): void
    {
        $rule = $this->winterRule();
        $newcomer = $this->product($this->summer);
        $pse = $this->defaultPseFor($newcomer);
        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $this->currency));

        $event = new ProductAddCategoryEvent($newcomer, $this->winter->getId());
        $this->dispatch($event, TheliaEvents::PRODUCT_ADD_CATEGORY);

        self::assertContains($pse->getId(), $this->scopeOf($rule));
        self::assertEqualsWithDelta(80.0, $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()]->untaxedPrice, 0.000001);

        $this->dispatch(new ProductDeleteCategoryEvent($newcomer, $this->winter->getId()), TheliaEvents::PRODUCT_REMOVE_CATEGORY);

        self::assertNotContains($pse->getId(), $this->scopeOf($rule));
        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $this->currency));
    }

    public function testAChangedCatalogPriceIsRepricedByTheRulesCoveringIt(): void
    {
        $this->winterRule();
        $product = $this->product($this->winter);
        $pse = $this->defaultPseFor($product);
        self::assertEqualsWithDelta(80.0, $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()]->untaxedPrice, 0.000001);

        $event = new ProductSaleElementUpdateEvent($product, $pse->getId());
        $event
            ->setReference($pse->getRef())
            ->setPrice(200.0)
            ->setCurrencyId($this->currency->getId())
            ->setWeight(0.0)
            ->setQuantity(10.0)
            ->setSalePrice(0.0)
            ->setOnsale(0)
            ->setIsnew(0)
            ->setIsdefault(true)
            ->setEanCode('')
            ->setTaxRuleId($product->getTaxRuleId())
            ->setFromDefaultCurrency(0);
        $this->dispatch($event, TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT);

        self::assertEqualsWithDelta(160.0, $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()]->untaxedPrice, 0.000001);
    }

    public function testDeletingABrandACriterionNamesLeavesTheRuleCoveringNothing(): void
    {
        $brand = $this->factory->brand();
        $product = $this->product($this->winter);
        $product->setBrandId($brand->getId())->save();

        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_BRAND, $brand->getId());
        $this->repricer->afterRuleChanged($rule);
        $pse = $this->defaultPseFor($product);
        self::assertSame([$pse->getId()], $this->scopeOf($rule));

        $this->dispatch(new BrandDeleteEvent($brand->getId()), TheliaEvents::BRAND_DELETE);

        self::assertSame([], $this->scopeOf($rule));
        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $this->currency));
    }

    public function testMovingACategoryUnderAnotherReEvaluatesTheRulesNamingCategories(): void
    {
        $rule = $this->winterRule();
        $coats = $this->factory->category();
        $product = $this->product($coats);
        $pse = $this->defaultPseFor($product);
        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $this->currency));

        $event = new CategoryUpdateEvent($coats->getId());
        $event
            ->setLocale('en_US')
            ->setTitle('Coats')
            ->setDescription('')
            ->setChapo('')
            ->setPostscriptum('')
            ->setParent($this->winter->getId())
            ->setVisible(1)
            ->setDefaultTemplateId(0);
        $this->dispatch($event, TheliaEvents::CATEGORY_UPDATE);

        self::assertContains($pse->getId(), $this->scopeOf($rule));
        self::assertArrayHasKey($pse->getId(), $this->reader->currentPrices([$pse->getId()], $this->currency));
    }

    public function testAChangeMovingEveryPriceMarksTheTurnedOnRulesDirtyForTheCommand(): void
    {
        $rule = $this->winterRule();
        $off = $this->factory->catalogPriceRule(['active' => false]);

        // Driven through the subscriber rather than the tax event: Thelia\Action\Tax
        // needs a real tax to update, and what is under test is the marking, not the tax.
        $sync = $this->getService(CatalogPriceRuleCatalogSync::class);
        self::assertArrayHasKey(TheliaEvents::TAX_UPDATE, CatalogPriceRuleCatalogSync::getSubscribedEvents());
        self::assertArrayHasKey(TheliaEvents::CURRENCY_UPDATE_RATES, CatalogPriceRuleCatalogSync::getSubscribedEvents());
        $sync->afterEveryPriceMoved();

        self::assertTrue((bool) CatalogPriceRuleQuery::create()->findPk($rule->getId())->getDirty());
        self::assertFalse((bool) CatalogPriceRuleQuery::create()->findPk($off->getId())->getDirty(), 'a rule turned off owes nothing');
    }

    private function winterRule(): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->winter->getId());
        $this->repricer->afterRuleChanged($rule);

        return $rule;
    }

    private function product(Category $category): Product
    {
        $product = $this->factory->product($category, $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0]);
        // Created outside the events, so the scope is brought up to date by hand.
        $this->repricer->afterProductChanged($product->getId());

        return $product;
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
    private function scopeOf(CatalogPriceRule $rule): array
    {
        return array_map('intval', CatalogPriceRuleProductSaleElementsQuery::create()
            ->filterByCatalogPriceRuleId($rule->getId())
            ->select('ProductSaleElementsId')
            ->find()
            ->getData());
    }
}

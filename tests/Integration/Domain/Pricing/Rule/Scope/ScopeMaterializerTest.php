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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule\Scope;

use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleProductSaleElementsQuery;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The scope of a rule is what its criteria select in the catalog, written down as
 * sale element ids: types combine with AND, the targets of a type with OR, an
 * absent type restricts nothing, and anything unreadable restricts everything.
 */
final class ScopeMaterializerTest extends ActionIntegrationTestCase
{
    private ScopeMaterializer $materializer;

    private Currency $currency;

    private TaxRule $taxRule;

    private Category $winter;

    private Category $coats;

    private Category $summer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->materializer = $this->getService(ScopeMaterializer::class);
        $this->currency = $this->factory->currency();
        $this->taxRule = $this->factory->taxRule();
        $this->winter = $this->factory->category();
        $this->coats = $this->factory->category(['parent' => $this->winter->getId()]);
        $this->summer = $this->factory->category();
    }

    public function testACategoryCriterionCoversTheProductsFiledInItAndBelowIt(): void
    {
        $inWinter = $this->product($this->winter);
        $inCoats = $this->product($this->coats);
        $inSummer = $this->product($this->summer);

        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->winter->getId());

        $change = $this->materializer->materializeRule($rule);

        self::assertSame([], $change->before);
        self::assertEqualsCanonicalizing([$this->pseOf($inWinter)->getId(), $this->pseOf($inCoats)->getId()], $change->after);
        self::assertNotContains($this->pseOf($inSummer)->getId(), $change->after);
        self::assertEqualsCanonicalizing($change->after, $this->storedScopeOf($rule));
    }

    public function testSubcategoriesStayOutWhenTheRuleSaysSo(): void
    {
        $inWinter = $this->product($this->winter);
        $this->product($this->coats);

        $rule = $this->factory->catalogPriceRule(['includeSubcategories' => false]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->winter->getId());

        self::assertSame([$this->pseOf($inWinter)->getId()], $this->materializer->materializeRule($rule)->after);
    }

    public function testAProductFiledInASecondCategoryIsCoveredThroughIt(): void
    {
        $product = $this->product($this->summer);
        $this->factory->productCategory($product, $this->winter);

        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->winter->getId());

        self::assertSame([$this->pseOf($product)->getId()], $this->materializer->materializeRule($rule)->after);
    }

    public function testTheTargetsOfOneTypeCombineWithOrAndTheTypesWithAnd(): void
    {
        $brandX = $this->factory->brand();
        $brandY = $this->factory->brand();

        $winterX = $this->product($this->winter, $brandX);
        $summerX = $this->product($this->summer, $brandX);
        $winterY = $this->product($this->winter, $brandY);
        $this->product($this->winter);

        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->winter->getId());
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->summer->getId());
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_BRAND, $brandX->getId());
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_BRAND, $brandY->getId());

        self::assertEqualsCanonicalizing(
            [$this->pseOf($winterX)->getId(), $this->pseOf($summerX)->getId(), $this->pseOf($winterY)->getId()],
            $this->materializer->materializeRule($rule)->after,
        );
    }

    public function testTemplateFeatureValueAndNamedProductCriteriaSelectWhatTheyName(): void
    {
        $template = $this->factory->template();
        $feature = $this->factory->feature();
        $red = $this->factory->featureAv($feature);
        $blue = $this->factory->featureAv($feature);

        $templated = $this->product($this->winter);
        $templated->setTemplateId($template->getId())->save();
        $redOne = $this->product($this->winter);
        $this->factory->featureProduct($redOne, $red);
        $blueOne = $this->product($this->winter);
        $this->factory->featureProduct($blueOne, $blue);
        $named = $this->product($this->winter);

        $byTemplate = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($byTemplate, CatalogPriceRule::CRITERION_TEMPLATE, $template->getId());
        self::assertSame([$this->pseOf($templated)->getId()], $this->materializer->materializeRule($byTemplate)->after);

        $byFeature = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($byFeature, CatalogPriceRule::CRITERION_FEATURE_AV, $red->getId());
        self::assertSame([$this->pseOf($redOne)->getId()], $this->materializer->materializeRule($byFeature)->after);

        $byProduct = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($byProduct, CatalogPriceRule::CRITERION_PRODUCT, $named->getId());
        self::assertSame([$this->pseOf($named)->getId()], $this->materializer->materializeRule($byProduct)->after);
    }

    public function testAnAttributeValueCriterionKeepsOnlyTheSaleElementsCarryingIt(): void
    {
        $size = $this->factory->attribute();
        $small = $this->factory->attributeAv($size);
        $large = $this->factory->attributeAv($size);

        $product = $this->product($this->winter);
        $smallPse = $this->pseOf($product);
        $this->factory->attributeCombination($smallPse, $small);
        $largePse = $this->factory->productSaleElement($product);
        $this->factory->attributeCombination($largePse, $large);

        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_ATTRIBUTE_AV, $large->getId());

        self::assertSame([$largePse->getId()], $this->materializer->materializeRule($rule)->after);
    }

    public function testARuleWithoutAnyCriterionCoversTheWholeCatalog(): void
    {
        $this->product($this->winter);
        $this->product($this->summer);

        $rule = $this->factory->catalogPriceRule();
        $change = $this->materializer->materializeRule($rule);

        self::assertCount(ProductSaleElementsQuery::create()->count(), $change->after);
    }

    /**
     * A criterion whose target is gone must not read as "all": the AND fails and
     * the rule covers nothing until the merchant fixes it.
     */
    public function testACriterionNamingADeletedTargetCoversNothing(): void
    {
        $this->product($this->winter);

        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->winter->getId());
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_BRAND, 999_999_999);

        self::assertSame([], $this->materializer->materializeRule($rule)->after);
    }

    public function testACriterionTypeNoResolverAnswersForCoversNothingAndIsReported(): void
    {
        $this->product($this->winter);

        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, 'some_module_type', 1);

        $change = $this->materializer->materializeRule($rule);

        self::assertSame([], $change->after);
        self::assertSame(['some_module_type'], $change->unknownCriterionTypes);
    }

    /**
     * Recette 2 of the ticket: a product filed in the category on the 15th enters
     * the rule from that moment, and nothing else about the scope moves.
     */
    public function testReEvaluatingOneProductTouchesOnlyItsOwnSaleElements(): void
    {
        $alreadyIn = $this->product($this->winter);
        $newcomer = $this->product($this->summer);

        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->winter->getId());
        $this->materializer->materializeRule($rule);

        $this->factory->productCategory($newcomer, $this->winter);
        $changes = $this->materializer->materializeProduct($newcomer->getId());

        self::assertCount(1, $changes);
        self::assertSame([], $changes[0]->before);
        self::assertSame([$this->pseOf($newcomer)->getId()], $changes[0]->after);
        self::assertEqualsCanonicalizing(
            [$this->pseOf($alreadyIn)->getId(), $this->pseOf($newcomer)->getId()],
            $this->storedScopeOf($rule),
        );
    }

    public function testMaterializingAgainReportsWhatLeftAndWhatCame(): void
    {
        $inWinter = $this->product($this->winter);
        $inSummer = $this->product($this->summer);

        $rule = $this->factory->catalogPriceRule();
        $criterion = $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_CATEGORY, $this->winter->getId());
        $this->materializer->materializeRule($rule);

        $criterion->setTargetId($this->summer->getId())->save();
        $change = $this->materializer->materializeRule($rule);

        self::assertSame([$this->pseOf($inWinter)->getId()], $change->before);
        self::assertSame([$this->pseOf($inSummer)->getId()], $change->after);
        self::assertEqualsCanonicalizing(
            [$this->pseOf($inWinter)->getId(), $this->pseOf($inSummer)->getId()],
            $change->touchedProductSaleElementsIds(),
        );
        self::assertFalse($change->isUnchanged());
    }

    private function product(Category $category, ?\Thelia\Model\Brand $brand = null): Product
    {
        $product = $this->factory->product($category, $this->taxRule, $this->currency);

        if (null !== $brand) {
            $product->setBrandId($brand->getId())->save();
        }

        return $product;
    }

    private function pseOf(Product $product): ProductSaleElements
    {
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();

        self::assertNotNull($pse);

        return $pse;
    }

    /**
     * @return list<int>
     */
    private function storedScopeOf(CatalogPriceRule $rule): array
    {
        return array_map('intval', CatalogPriceRuleProductSaleElementsQuery::create()
            ->filterByCatalogPriceRuleId($rule->getId())
            ->select('ProductSaleElementsId')
            ->find()
            ->getData());
    }
}

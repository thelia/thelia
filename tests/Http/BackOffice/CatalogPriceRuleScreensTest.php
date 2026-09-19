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

namespace Thelia\Tests\Http\BackOffice;

use BackOfficeDefaultTwigBundle\Controller\CatalogPriceRule\CatalogPriceRuleController;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCriterionQuery;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the catalog price rules: the list with what each rule covers,
 * the edit screen, the preview of the prices, and the writes that go through the
 * core events.
 */
final class CatalogPriceRuleScreensTest extends WebIntegrationTestCase
{
    private const LIST_URL = '/admin/catalog-price-rule';

    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    private Currency $currency;

    private Category $category;

    protected function setUp(): void
    {
        if (!class_exists(CatalogPriceRuleController::class)) {
            self::markTestSkipped('The installed back-office theme has no catalog price rule screens.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);

        // Built without createFixtureFactory(): that helper pushes a synthetic
        // request onto the stack, which would then become the "main" request the
        // security context reads its session from.
        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->currency = $this->factory->currency();
        $this->category = $this->factory->category();
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
    }

    public function testTheListShowsEveryRuleWithItsStateAndCoverage(): void
    {
        $this->loginAdmin();
        $product = $this->catalogProduct();
        $rule = $this->runningRuleOn($product, 'Winter -20%');

        $this->assertPageRenders(self::LIST_URL);
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Winter -20%', $content);
        self::assertStringContainsString('data-testid="catalog-price-rule-toggle-'.$rule->getId().'"', $content);
        self::assertStringContainsString('data-state="running"', $content);
        self::assertMatchesRegularExpression('/datatable-catalog-price-rules-cell-products"[^>]*>\s*1\s*</', $content, 'the list shows the number of products covered');
    }

    public function testCreatingARuleOpensItsEditScreen(): void
    {
        $this->loginAdmin();
        $this->assertPageRenders(self::LIST_URL);

        $this->client->submitForm('Create and configure', [
            'thelia_catalog_price_rule_creation[title]' => 'Spring rule',
        ]);

        $rule = CatalogPriceRuleQuery::create()->orderById('desc')->findOne();
        self::assertNotNull($rule);
        self::assertSame('Spring rule', $rule->setLocale('en_US')->getTitle());
        self::assertFalse((bool) $rule->getActive(), 'a new rule comes turned off');
        self::assertResponseRedirects(self::LIST_URL.'/update/'.$rule->getId());
    }

    public function testTheEditScreenSavesTheScopeTheEffectAndTheDates(): void
    {
        $this->loginAdmin();
        $product = $this->catalogProduct();
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        $rule = $this->factory->catalogPriceRule(['title' => 'Draft']);

        $this->assertPageRenders(self::LIST_URL.'/update/'.$rule->getId());
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('data-testid="catalog-price-rule-edit-form"', $content);

        $this->client->request('POST', self::LIST_URL.'/save/'.$rule->getId(), [
            'thelia_catalog_price_rule' => [
                'id' => $rule->getId(),
                'locale' => 'en_US',
                'title' => 'Winter category',
                'active' => '1',
                'priority' => '50',
                'effect_type' => (string) CatalogPriceRule::EFFECT_TYPE_PERCENTAGE,
                'percentage_value' => '20',
                'display_initial_price' => '1',
                'include_subcategories' => '1',
                'audience_mode' => (string) CatalogPriceRule::AUDIENCE_MODE_PUBLIC,
                '_token' => $this->csrfTokenOf($content, 'thelia_catalog_price_rule'),
            ],
            'criteria' => ['category' => [(string) $this->category->getId()]],
        ]);

        self::assertResponseRedirects(self::LIST_URL.'/update/'.$rule->getId());

        $saved = CatalogPriceRuleQuery::create()->findPk($rule->getId());
        self::assertTrue((bool) $saved->getActive());
        self::assertSame(50, (int) $saved->getPriority());
        self::assertSame([CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]], $saved->getCriteriaByType());
        self::assertEqualsWithDelta(80.0, $this->getService(PublicPriceReader::class)->currentPrices([$pse->getId()], $this->currency)[$pse->getId()]->untaxedPrice, 0.000001, 'saving the rule priced the products it covers');
    }

    public function testThePreviewAnswersTheCoverageAndThePricesOfTheFormAsPosted(): void
    {
        $this->loginAdmin();
        $product = $this->catalogProduct();
        $rule = $this->factory->catalogPriceRule(['title' => 'Draft']);

        $this->assertPageRenders(self::LIST_URL.'/update/'.$rule->getId());
        $content = (string) $this->client->getResponse()->getContent();

        $this->client->request('POST', self::LIST_URL.'/preview/'.$rule->getId(), [
            'thelia_catalog_price_rule' => [
                'id' => $rule->getId(),
                'locale' => 'en_US',
                'title' => 'Draft',
                'priority' => '100',
                'effect_type' => (string) CatalogPriceRule::EFFECT_TYPE_PERCENTAGE,
                'percentage_value' => '50',
                'audience_mode' => (string) CatalogPriceRule::AUDIENCE_MODE_PUBLIC,
                '_token' => $this->csrfTokenOf($content, 'thelia_catalog_price_rule'),
            ],
            'products' => [(string) $product->getId()],
        ]);

        self::assertResponseIsSuccessful();
        $fragment = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('data-product-count="1"', $fragment);
        self::assertStringContainsString('data-testid="catalog-price-rule-preview-line-', $fragment);
        self::assertSame(0, CatalogPriceRuleCriterionQuery::create()->filterByCatalogPriceRuleId($rule->getId())->count(), 'the preview stores nothing');
    }

    public function testTogglingAndDeletingGoThroughTheTokenizedLinks(): void
    {
        $this->loginAdmin();
        $product = $this->catalogProduct();
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        $rule = $this->runningRuleOn($product, 'Toggle me');
        $reader = $this->getService(PublicPriceReader::class);
        self::assertArrayHasKey($pse->getId(), $reader->currentPrices([$pse->getId()], $this->currency));

        $this->assertPageRenders(self::LIST_URL);
        $toggleUrl = $this->client->getCrawler()->filter('[data-testid="catalog-price-rule-toggle-'.$rule->getId().'"]')->attr('href');
        $this->client->request('GET', $toggleUrl);
        self::assertResponseRedirects();
        self::assertFalse((bool) CatalogPriceRuleQuery::create()->findPk($rule->getId())->getActive());
        self::assertSame([], $reader->currentPrices([$pse->getId()], $this->currency), 'turning the rule off gives the product its price back at once');

        $this->assertPageRenders(self::LIST_URL);
        $deleteAction = $this->client->getCrawler()->filter('#catalog-price-rule-delete-modal form')->attr('action');
        $this->client->request('POST', $deleteAction, ['rule_id' => $rule->getId()]);
        self::assertResponseRedirects();
        self::assertNull(CatalogPriceRuleQuery::create()->findPk($rule->getId()));
    }

    public function testAnAdministratorWithoutTheRightIsRefused(): void
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $admin = $factory->admin(['profile' => $factory->profile()]);
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);

        $this->client->request('GET', self::LIST_URL);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    private function loginAdmin(): void
    {
        $admin = $this->factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product($this->category, $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0, 'baseQuantity' => 10, 'title' => 'Rule screen product']);
    }

    private function runningRuleOn(Product $product, string $title): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0, 'title' => $title]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        return $rule;
    }

    private function csrfTokenOf(string $content, string $formName): string
    {
        self::assertSame(1, preg_match('/name="'.preg_quote($formName, '/').'\[_token\]"[^>]*value="([^"]+)"/', $content, $matches), 'the edit form carries its CSRF token');

        return $matches[1];
    }
}

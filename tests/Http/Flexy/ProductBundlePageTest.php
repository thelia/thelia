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

namespace Thelia\Tests\Http\Flexy;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The sheet of a product bundle on the front-office theme: its components listed the way the merchant allowed
 * them to be changed, the price of the composition (the sum of the components less the discount, struck and
 * with what the shopper saves), no percentage tag, and nothing at all of it on the sheet of an ordinary product.
 *
 * The page belongs to the theme, which ships as its own package on its own release cycle: a theme older than the
 * bundles is reported as skipped rather than failed.
 */
final class ProductBundlePageTest extends WebIntegrationTestCase
{
    use InteractsWithLiveComponents;

    private const BUNDLE_URL = 'flexy-product-bundle-page-test.html';

    private const PLAIN_URL = 'flexy-product-bundle-plain-page-test.html';

    private const CATEGORY_URL = 'flexy-product-bundle-category-test.html';

    protected function setUp(): void
    {
        // A class shipped *with* the feature, not one the theme already had: an older theme has a product sheet.
        if (!class_exists(\FlexyBundle\Components\Organisms\BundleComposition\Base::class)) {
            self::markTestSkipped('The installed front-office theme does not know the product bundles.');
        }

        parent::setUp();
    }

    public function testTheSheetListsTheComponentsWithTheControlsTheMerchantAllowed(): void
    {
        $this->bundleUnderTest();

        $crawler = $this->renderPage(self::BUNDLE_URL);

        $components = $crawler->filter('[data-testid="bundle-component"]');
        self::assertCount(3, $components);
        self::assertStringContainsString('Page bike', $components->eq(0)->text());
        self::assertCount(0, $components->eq(0)->filter('input'), 'a required component the merchant fixed has no field');
        self::assertStringContainsString('Page lock', $components->eq(2)->text());
        self::assertStringContainsString('Optional', $components->eq(2)->text());
        self::assertSame('on(change)|bundleSelection['.$components->eq(2)->attr('data-bundle-item').']', $components->eq(2)->filter('input[type="number"]')->attr('data-model'), 'a quantity from 0 to 3 is bound to the sheet');
        self::assertSame('3', $components->eq(2)->filter('input[type="number"]')->attr('max'));
    }

    public function testThePriceIsTheOneOfTheDefaultCompositionWithWhatTheShopperSaves(): void
    {
        $this->bundleUnderTest();

        $crawler = $this->renderPage(self::BUNDLE_URL);

        // Bike and two helmets, no lock: 560 struck, 504 after the 10 %, 56 saved. The shop taxes at 20 %: the sheet is in taxes
        // included, like the rest of the theme, which makes 672, 604.80 and 67.20.
        $price = $crawler->filter('.PseSelector-price');
        self::assertCount(1, $price);
        self::assertMatchesRegularExpression('/604\.80/', $price->filter('strong')->text());
        self::assertMatchesRegularExpression('/672\.00/', $price->filter('.Price-original')->text());
        self::assertMatchesRegularExpression('/67\.20/', $price->filter('[data-testid="price-saving"]')->text());
        self::assertCount(0, $crawler->filter('.Tag--error'), 'no percentage tag: the sheet says what is saved');
    }

    public function testABundleInFixedModeIsPricedByItsOwnSaleElement(): void
    {
        $this->bundleUnderTest(BundlePriceMode::Fixed);

        $crawler = $this->renderPage(self::BUNDLE_URL);

        self::assertCount(3, $crawler->filter('[data-testid="bundle-component"]'));
        $price = $crawler->filter('.PseSelector-price');
        self::assertMatchesRegularExpression('/1,198\.80/', $price->filter('strong')->text());
        self::assertCount(0, $crawler->filter('[data-testid="price-saving"]'));
    }

    public function testABundleWhoseRequiredComponentWentOfflineCannotBeAdded(): void
    {
        $example = $this->bundleUnderTest();
        $example['bikeProduct']->setVisible(0)->save($this->getPropelConnection());

        $crawler = $this->renderPage(self::BUNDLE_URL);

        self::assertStringContainsString('This bundle is currently unavailable', $crawler->filter('.PseSelector')->text());
        self::assertStringNotContainsString('Add to cart', $crawler->filter('.PseSelector')->text());
    }

    /**
     * Nothing is computed by the card: it prints the price the front API publishes for the default composition (the
     * core rewrites it for a bundle priced by its components), struck through when the core asks to show it.
     */
    public function testTheCardOfAListingPrintsThePriceTheApiPublishes(): void
    {
        $this->bundleUnderTest();

        $card = $this->renderPage(self::CATEGORY_URL)->filter('.ProductCard')->reduce(static fn (Crawler $node): bool => str_contains($node->text(), 'Page bundle'));

        self::assertCount(1, $card);
        self::assertMatchesRegularExpression('/604\.80/', $card->filter('.ProductCard-price')->text());
        self::assertMatchesRegularExpression('/672\.00/', $card->filter('.ProductCard-originalPrice')->text());
    }

    public function testTheCardOfABundleInFixedModeShowsItsOwnPriceAlone(): void
    {
        $this->bundleUnderTest(BundlePriceMode::Fixed);

        $card = $this->renderPage(self::CATEGORY_URL)->filter('.ProductCard')->reduce(static fn (Crawler $node): bool => str_contains($node->text(), 'Page bundle'));

        self::assertCount(1, $card);
        self::assertMatchesRegularExpression('/1,198\.80/', $card->filter('.ProductCard-price')->text());
        self::assertCount(0, $card->filter('.ProductCard-originalPrice'));
    }

    public function testTheSheetOfAnOrdinaryProductIsLeftAlone(): void
    {
        $factory = $this->factory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['title' => 'Plain product', 'basePrice' => 25.0, 'baseQuantity' => 4]);
        $product->setRewrittenUrl('en_US', self::PLAIN_URL);

        $crawler = $this->renderPage(self::PLAIN_URL);

        self::assertCount(0, $crawler->filter('[data-testid="bundle-composition"]'));
        self::assertCount(0, $crawler->filter('[data-testid="price-saving"]'));
        self::assertMatchesRegularExpression('/30\.00/', $crawler->filter('.PseSelector-price')->text());
        self::assertStringContainsString('Add to cart', $crawler->filter('.PseSelector')->text());
    }

    /**
     * The precondition of the browser: the live controller refuses ("Invalid model name") to write a model it does not
     * find in the props the server rendered, so every field bound with data-model must point at a key that is there
     * on the first render, before anything was touched. The lookup is the one of the controller (see modelResolvesInProps).
     */
    public function testEveryFieldBoundToTheSheetPointsAtAKeyTheLiveControllerFindsInTheRenderedProps(): void
    {
        $sheet = $this->bundleSheet($this->bundleUnderTest()['bundle']);
        $html = (string) $sheet->render();
        $models = $this->boundModels($html);

        self::assertNotSame([], $models, 'the quantity of the optional component is bound to the sheet');

        foreach ($models as $model) {
            self::assertTrue($this->modelResolvesInProps($this->liveProps($html), $model), \sprintf('the live controller would refuse the model "%s"', $model));
        }
    }

    public function testATypedQuantityGoesThroughTheHydrationOfTheComponentAndPricesTheComposition(): void
    {
        $sheet = $this->bundleSheet($this->bundleUnderTest()['bundle']);
        $lock = $this->lockItemId((string) $sheet->render());

        // What the controller posts when the field changes: the model path and the new value, with the props of the last render.
        $html = (string) $sheet->set('bundleSelection.'.$lock, '2')->render();

        $crawler = new Crawler($html);
        self::assertMatchesRegularExpression('/648\.00/', $crawler->filter('.PseSelector-price strong')->text());
        self::assertMatchesRegularExpression('/720\.00/', $crawler->filter('.PseSelector-price .Price-original')->text());
        self::assertSame('2', $crawler->filter('[data-bundle-item="'.$lock.'"] input[type="number"]')->attr('value'));
        // The field can be typed in again: its key is still in the props the server hands back.
        self::assertTrue($this->modelResolvesInProps($this->liveProps($html), 'bundleSelection['.$lock.']'));

        $back = (string) $sheet->set('bundleSelection.'.$lock, '0')->render();
        self::assertMatchesRegularExpression('/604\.80/', (new Crawler($back))->filter('.PseSelector-price strong')->text());
    }

    public function testAQuantityTheShopRefusesFallsBackToTheDefaultCompositionAndTheFieldStaysUsable(): void
    {
        $sheet = $this->bundleSheet($this->bundleUnderTest()['bundle']);
        $lock = $this->lockItemId((string) $sheet->render());

        $html = (string) $sheet->set('bundleSelection.'.$lock, '4')->render();

        $crawler = new Crawler($html);
        self::assertStringContainsString('cannot be sold as chosen', $crawler->filter('[data-testid="bundle-composition"]')->text());
        self::assertSame('0', $crawler->filter('[data-bundle-item="'.$lock.'"] input[type="number"]')->attr('value'));
        self::assertTrue($this->modelResolvesInProps($this->liveProps($html), 'bundleSelection['.$lock.']'));
        // The next render answers the default composition, not the refusal again.
        $next = (string) $sheet->refresh()->render();
        self::assertStringNotContainsString('cannot be sold as chosen', $next);
    }

    private function bundleSheet(Product $bundle): TestLiveComponent
    {
        $sheet = $this->createLiveComponent('Layouts:ProductDetails:Base', [
            'product' => ['id' => (int) $bundle->getId(), 'isBundle' => true, 'i18ns' => ['title' => 'Page bundle']],
            'title' => 'Page bundle',
        ], $this->client);

        // The first render mounts the component in this process, and mount() reads the API: it needs a request on the stack
        // (the page has one). Taken off again once the component answers, so that the later requests start from a clean stack.
        $requestStack = $this->getService(RequestStack::class);
        $request = TheliaRequest::create('/', 'GET', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $request->setSession($this->getService('session.factory')->createSession());
        $requestStack->push($request);

        try {
            $sheet->render();
        } finally {
            $requestStack->pop();
        }

        return $sheet;
    }

    private function lockItemId(string $html): int
    {
        $components = (new Crawler($html))->filter('[data-testid="bundle-component"]');
        self::assertCount(3, $components);

        return (int) $components->eq(2)->attr('data-bundle-item');
    }

    /**
     * @return list<string>
     */
    private function boundModels(string $html): array
    {
        $models = [];

        foreach ((new Crawler($html))->filter('[data-bundle-item] [data-model]') as $field) {
            $models[] = explode('|', (string) $field->getAttribute('data-model'), 2)[1];
        }

        return $models;
    }

    /**
     * @return array<string, mixed>
     */
    private function liveProps(string $html): array
    {
        return json_decode((string) (new Crawler($html))->filter('[data-live-props-value]')->first()->attr('data-live-props-value'), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * The lookup of the live controller (assets/dist/live_controller.js, normalizeModelName then ValueStore.has): the
     * model "a[1].b" becomes the path "a.1.b", and it exists when the value read at that path in the props is not undefined.
     *
     * @param array<string, mixed> $props
     */
    private function modelResolvesInProps(array $props, string $model): bool
    {
        $parts = explode('.', implode('.', array_map(static fn (string $part): string => str_replace(']', '', $part), explode('[', preg_replace('/\[]$/', '', $model)))));
        $level = $props;

        foreach ($parts as $part) {
            if (!\is_array($level) || !\array_key_exists($part, $level)) {
                return false;
            }

            $level = $level[$part];
        }

        return true;
    }

    private function renderPage(string $url): Crawler
    {
        $this->assertPageRenders('/'.$url);

        return new Crawler((string) $this->client->getResponse()->getContent());
    }

    /**
     * Bike (1, required, stock 7), helmets (2, required, stock 5) and a lock (optional, 0 to 3, stock 10), priced
     * by the components with 10 % off, or at 999 when fixed (all of them taxed at 20 % by the default rule of the shop).
     *
     * @return array{bundle: Product, bikeProduct: Product}
     */
    private function bundleUnderTest(BundlePriceMode $mode = BundlePriceMode::Computed): array
    {
        $factory = $this->factory();
        $service = $this->getService(BundleCompositionService::class);
        $category = $factory->category();
        $category->setLocale('en_US')->setTitle('Bundle test category');
        $category->save($this->getPropelConnection());
        $category->setRewrittenUrl('en_US', self::CATEGORY_URL);
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $bike = $factory->product($category, $taxRule, $currency, ['title' => 'Page bike', 'basePrice' => 500.0, 'baseQuantity' => 7]);
        $helmet = $factory->product($category, $taxRule, $currency, ['title' => 'Page helmet', 'basePrice' => 30.0, 'baseQuantity' => 5]);
        $lock = $factory->product($category, $taxRule, $currency, ['title' => 'Page lock', 'basePrice' => 20.0, 'baseQuantity' => 10]);
        $bundle = $factory->product($category, $taxRule, $currency, ['title' => 'Page bundle', 'basePrice' => 999.0, 'baseQuantity' => 0]);
        $bundle = $service->setBundleSettings((int) $bundle->getId(), true, $mode, '10', BundleDiscountType::Percent);
        $bundle->setRewrittenUrl('en_US', self::BUNDLE_URL);

        $service->addItem((int) $bundle->getId(), (int) $bike->getProductSaleElementss()->getFirst()->getId(), '1');
        $service->addItem((int) $bundle->getId(), (int) $helmet->getProductSaleElementss()->getFirst()->getId(), '2');
        $service->addItem((int) $bundle->getId(), (int) $lock->getProductSaleElementss()->getFirst()->getId(), '1', '0', '3', false);

        return ['bundle' => $bundle, 'bikeProduct' => $bike];
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the stack is empty, and it
     * would then be the "main" request of the page render, the one the session is read from.
     */
    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}

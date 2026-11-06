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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DomCrawler\Crawler;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\UnitCode;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The quantity rules of a sale element, as a shopper meets them on the front-office pages: a lot
 * of six offered in sixes, a quantity off the step refused with the reason the core gives, a
 * weight bought with its decimals, and a line restored from an older cart brought back to the
 * rule the moment the cart page shows it, for a visitor nobody knows.
 *
 * The core decides every quantity; these tests pin that the theme hands the shopper's quantity
 * to it unchanged and shows what it answers. The theme ships as its own package on its own
 * release cycle: a theme older than the constrained selector is reported as skipped rather than
 * failed, one marker per feature.
 */
final class ConstrainedQuantitySelectorTest extends WebIntegrationTestCase
{
    private const SELECTOR_TEMPLATE = 'components/Organisms/PseSelector/Base.html.twig';

    /** The list a lot is offered in, in place of a free stepper. */
    private const LOT_LIST_MARKER = 'quantityChoices';

    /** A quantity sold by measure puts its add-to-cart under the field. */
    private const MEASURE_MARKER = 'PseSelector-addToCart--stacked';

    private const CHECKOUT_CONTROLLER = 'src/Controller/CheckoutController.php';

    /** The identification page leaves a prefetched request alone, which the cart page already did. */
    private const GUEST_CHECKOUT_CONTROLLER = 'src/Controller/GuestCheckoutController.php';

    /** The cart page brings the lines of a cart read again to their rule. */
    private const RESTORED_LINES_MARKER = 'RestoredCartLines';

    private const LOT_URL = 'flexy-quantity-lot-test.html';

    private const WEIGHT_URL = 'flexy-quantity-weight-test.html';

    public function testALotOfSixIsOfferedInSixes(): void
    {
        $this->skipUnlessTheThemeCarries(self::SELECTOR_TEMPLATE, self::LOT_LIST_MARKER);
        $this->productSoldBy(new QuantityRule(minQuantity: 2, step: 6), self::LOT_URL, 'A case of six');

        $choices = $this->openTheSheet(self::LOT_URL)->filter('select[name$="[quantity]"] option')->each(
            static fn (Crawler $option): string => (string) $option->attr('value'),
        );

        self::assertSame(['6', '12', '18'], \array_slice($choices, 0, 3), 'a minimum of two in lots of six starts at six, then twelve');
    }

    public function testAQuantityOffTheStepIsRefusedWithTheReason(): void
    {
        $this->skipUnlessTheThemeCarries(self::SELECTOR_TEMPLATE, self::LOT_LIST_MARKER);
        $this->productSoldBy(new QuantityRule(minQuantity: 2, step: 6), self::LOT_URL, 'A case of six');
        $cart = $this->openASessionWithACart();

        $alert = $this->addToCart($this->openTheSheet(self::LOT_URL), '7')->filter('[role="alert"]');

        // The form refuses it as any invalid form is refused by a live component: a 422 that
        // re-renders the selector with the reason, which is the core's.
        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $alert, 'the selector tells the shopper why');
        self::assertStringContainsString('6, 12, 18', $alert->text(), 'the reason lists the quantities the shopper can order');
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count(), 'seven of a lot of six never reaches the cart');
    }

    public function testAWeightIsAddedWithItsDecimals(): void
    {
        $this->skipUnlessTheThemeCarries(self::SELECTOR_TEMPLATE, self::MEASURE_MARKER);
        $this->productSoldBy(
            new QuantityRule(minQuantity: 0.5, step: 0.25, decimals: 2, unitCode: UnitCode::KILOGRAM),
            self::WEIGHT_URL,
            'Coffee beans',
        );
        $cart = $this->openASessionWithACart();

        $alert = $this->addToCart($this->openTheSheet(self::WEIGHT_URL), '1.25')->filter('[role="alert"]');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $alert, 'nothing to refuse');
        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();
        self::assertNotNull($line, 'a quarter-kilogram step takes 1.25 kg');
        self::assertSame(1.25, (float) $line->getQuantity());
    }

    public function testTheCartPageAdjustsARestoredLineForAVisitorNobodyKnows(): void
    {
        $this->skipUnlessTheThemeCarries(self::CHECKOUT_CONTROLLER, self::RESTORED_LINES_MARKER);
        $product = $this->productSoldBy(new QuantityRule(minQuantity: 2, step: 6), self::LOT_URL, 'A case of six');
        $cart = $this->openASessionWithACart();

        // Written as an older cart holds it: seven, put there before the lot size was six.
        $line = $this->fixtures()->cartItem($cart, $product, $this->saleElementOf($product), ['quantity' => 7]);
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();

        $this->assertPageRenders('/checkout/cart');
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('The quantity of A case of six in your cart was adjusted.', html_entity_decode($content, \ENT_QUOTES));
        self::assertSame(6.0, (float) CartItemQuery::create()->findPk($line->getId())?->getQuantity(), 'seven is brought down to the lot below it');
    }

    public function testAPageFetchedAheadOfAClickNeitherAdjustsTheLineNorSpendsTheMessage(): void
    {
        $this->skipUnlessTheThemeCarries(self::GUEST_CHECKOUT_CONTROLLER, 'PrefetchRequest');
        $product = $this->productSoldBy(new QuantityRule(minQuantity: 2, step: 6), self::LOT_URL, 'A case of six');
        $cart = $this->openASessionWithACart();
        $line = $this->fixtures()->cartItem($cart, $product, $this->saleElementOf($product), ['quantity' => 7]);
        CartTableMap::clearInstancePool();
        CartTableMap::clearRelatedInstancePool();
        $prefetch = ['HTTP_X_SEC_PURPOSE' => 'prefetch'];

        $this->client->request('GET', '/checkout/identify', [], [], $prefetch);
        self::assertSame(7.0, (float) CartItemQuery::create()->findPk($line->getId())?->getQuantity(), 'a prefetched identification page leaves the line alone');

        $this->client->request('GET', '/checkout/cart', [], [], $prefetch);
        self::assertStringNotContainsString('was adjusted', html_entity_decode((string) $this->client->getResponse()->getContent(), \ENT_QUOTES));

        $this->client->request('GET', '/checkout/identify');
        CartTableMap::clearInstancePool();
        self::assertSame(6.0, (float) CartItemQuery::create()->findPk($line->getId())?->getQuantity(), 'the real visit brings it to the lot');

        $this->client->request('GET', '/checkout/cart', [], [], $prefetch);
        self::assertStringNotContainsString('was adjusted', html_entity_decode((string) $this->client->getResponse()->getContent(), \ENT_QUOTES), 'a prefetch tells nobody');

        $this->assertPageRenders('/checkout/cart');
        self::assertStringContainsString('The quantity of A case of six in your cart was adjusted.', html_entity_decode((string) $this->client->getResponse()->getContent(), \ENT_QUOTES), 'the page really opened still has its message');
    }

    private function productSoldBy(QuantityRule $rule, string $url, string $title): Product
    {
        $fixtures = $this->fixtures();
        $product = $fixtures->product($fixtures->category(), $fixtures->taxRule(), $fixtures->currency(), ['title' => $title, 'baseQuantity' => 100]);
        $product->setRewrittenUrl('en_US', $url);

        $saleElement = $this->saleElementOf($product);
        $rule->applyTo($saleElement);
        $saleElement->save($this->getPropelConnection());

        return $product;
    }

    private function saleElementOf(Product $product): ProductSaleElements
    {
        $saleElement = $product->getProductSaleElementss()->getFirst();
        \assert($saleElement instanceof ProductSaleElements);

        return $saleElement;
    }

    private function openTheSheet(string $url): Crawler
    {
        $this->assertPageRenders('/'.$url);

        return $this->client->getCrawler();
    }

    /**
     * Submits the selector the way its form does in the browser: the quantity travels as the
     * updated value of the form the component owns, and `save` runs on it.
     */
    private function addToCart(Crawler $sheet, string $quantity): Crawler
    {
        $component = $sheet->filter('form.PseSelector-form')->ancestors()->filter('[data-live-url-value]')->first();

        self::assertCount(1, $component, 'The selector must live inside a live component for the quantity to reach the server.');

        $props = json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR);
        $formName = $this->formHolding($props);

        $this->client->request(
            'POST',
            \sprintf('%s/save', $component->attr('data-live-url-value')),
            ['data' => json_encode([
                'props' => $props,
                'updated' => [$formName.'.quantity' => $quantity],
                'args' => new \stdClass(),
            ], \JSON_THROW_ON_ERROR)],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.live-component+html',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        );

        return $this->client->getCrawler();
    }

    /**
     * The prop the form values travel in is named after the form.
     *
     * @param array<string, mixed> $props
     */
    private function formHolding(array $props): string
    {
        foreach ($props as $name => $value) {
            if (\is_array($value) && \array_key_exists('quantity', $value) && \array_key_exists('product_sale_elements_id', $value)) {
                return $name;
            }
        }

        self::fail('The selector carries no form values.');
    }

    /**
     * Opens a real session holding an empty cart, the one the shop itself created for this
     * session, rather than a fixture the session knows nothing about.
     */
    private function openASessionWithACart(): Cart
    {
        $highestCartIdBefore = (int) CartQuery::create()->orderById(Criteria::DESC)->findOne()?->getId();

        $this->client->request('GET', '/checkout/cart');

        $cart = CartQuery::create()->filterById($highestCartIdBefore, Criteria::GREATER_THAN)->orderById(Criteria::DESC)->findOne();

        if (!$cart instanceof Cart) {
            self::fail('The cart page did not open a cart for this session.');
        }

        return $cart;
    }

    /**
     * A theme older than the constrained selector carries none of these markers. Reported as a
     * skip rather than a failure: the core ships with whichever theme version it is given.
     */
    private function skipUnlessTheThemeCarries(string $file, string $marker): void
    {
        $themeRoot = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate()->getAbsolutePath();
        $path = $themeRoot.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, $file);

        if (!file_exists($path) || !str_contains((string) file_get_contents($path), $marker)) {
            self::markTestSkipped(\sprintf('The installed front-office theme has no "%s" in %s.', $marker, $file));
        }
    }

    /**
     * Built without createFixtureFactory(): that helper pushes a synthetic request when the
     * stack is empty, and it would then be the "main" request of the page render below, the one
     * the session, and therefore the cart, is read from.
     */
    private function fixtures(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }
}

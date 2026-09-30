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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * Ordering by reference from the Flexy customer account.
 *
 * The component is driven the way the browser drives it, through its actions over HTTP,
 * with a customer put in the session: what is checked is what reaches the cart, not what
 * the core resolver computes, which its own tests cover.
 *
 * The page belongs to the theme, which ships on its own release cycle: a theme older than
 * this route is reported as skipped rather than failed.
 */
final class QuickOrderPageTest extends WebIntegrationTestCase
{
    use InteractsWithLiveComponents;

    private const ROUTE = 'account_quick_order';

    private const COMPONENT = 'Organisms:QuickOrderTable:Base';

    private ?CustomerSessionInjector $injector = null;

    private FixtureFactory $factory;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get(self::ROUTE)) {
            self::markTestSkipped('The installed front-office theme has no quick order page.');
        }

        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->currency = $this->factory->currency();
        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();

        parent::tearDown();
    }

    public function testThePageAnswersASignedInCustomer(): void
    {
        $this->injector?->setCustomer($this->customer());

        $crawler = $this->client->request('GET', $this->url());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.QuickOrderTable-table'));
    }

    public function testAVisitorIsSentToSignIn(): void
    {
        $this->client->request('GET', $this->url());

        self::assertResponseRedirects();
        self::assertStringContainsString('login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    /**
     * The page is behind the sign-in, the component's own endpoint is not: each action
     * checks the customer again. The refusal is read on the exception itself, before the
     * shop's error handling turns it into a page.
     */
    public function testAnActionOfAVisitorIsRefused(): void
    {
        $this->client->catchExceptions(false);

        $component = $this->table();
        $component->set('rows', [self::row('ANY', 1)]);

        $this->expectException(AccessDeniedHttpException::class);

        $component->call('check');
    }

    public function testThreeTypedReferencesReachTheCart(): void
    {
        $customer = $this->customer();
        $this->injector?->setCustomer($customer);
        $references = array_map(fn (): string => (string) $this->saleElementsOf($this->product())->getRef(), range(1, 3));

        $component = $this->table();
        $component->set('rows', array_map(static fn (string $reference): array => self::row($reference, 2), $references));
        $component->call('check');
        $component->call('addToCart');

        self::assertSame([2.0, 2.0, 2.0], $this->cartQuantitiesOf($customer));
        self::assertCount(1, $component->render()->crawler()->filter('.QuickOrderTable-messages a[href$="/checkout/cart"]'));
        self::assertSame(0, $this->component($component)->readyCount());
    }

    public function testSixPastedLinesSeparatedBySemicolonsAreRecognised(): void
    {
        $this->injector?->setCustomer($this->customer());
        $references = array_map(fn (): string => (string) $this->saleElementsOf($this->product())->getRef(), range(1, 6));

        $component = $this->table();
        $component->set('pastedText', implode("\n", array_map(static fn (string $reference): string => $reference.';1', $references)));
        $component->call('import');

        $table = $this->component($component);
        self::assertCount(6, $table->rows);
        self::assertSame(6, $table->readyCount());
    }

    public function testAPartlyInvalidImportNamesTheLinesItCouldNotRead(): void
    {
        $this->injector?->setCustomer($this->customer());
        $reference = (string) $this->saleElementsOf($this->product())->getRef();

        $component = $this->table();
        $component->set('pastedText', "{$reference};1\nNOT-A-LINE\n{$reference}-B;douze");
        $component->call('import');

        $table = $this->component($component);
        self::assertSame([2, 3], array_column($table->rejectedLines, 'line'));
        self::assertSame(1, $table->readyCount());
        self::assertStringContainsString('NOT-A-LINE', (string) $component->render());
    }

    public function testAnAmbiguousReferenceOffersItsVariantsWithTheDefaultOneSelected(): void
    {
        $this->injector?->setCustomer($this->customer());
        [$product, $default] = $this->productWithTwoSaleElementsSharingItsReference();

        $component = $this->table();
        $component->set('rows', [self::row((string) $product->getRef(), 1)]);
        $component->call('check');

        $table = $this->component($component);
        self::assertSame('ambiguous', $table->lineOf(0)['status'] ?? null);
        self::assertSame(0, $table->readyCount());
        self::assertSame(2, $component->render()->crawler()->filter('.CandidateSelect option:not([value=""])')->count());
        self::assertSame((string) $default->getId(), $component->render()->crawler()->filter('.CandidateSelect option[selected]')->attr('value'));

        $component->call('check');

        self::assertSame(1, $this->component($component)->readyCount());
    }

    public function testARowEditedAfterTheCheckDoesNotReachTheCart(): void
    {
        $customer = $this->customer();
        $this->injector?->setCustomer($customer);
        $reference = (string) $this->saleElementsOf($this->product())->getRef();

        $component = $this->table();
        $component->set('rows', [self::row($reference, 2)]);
        $component->call('check');
        $rows = $this->component($component)->rows;
        $rows[0]['quantity'] = '40';
        $component->set('rows', $rows);
        $component->call('addToCart');

        self::assertSame([], $this->cartQuantitiesOf($customer));
    }

    public function testAnInvalidQuantityIsLeftOutWithoutRefusingTheOtherRows(): void
    {
        $this->injector?->setCustomer($this->customer());
        $reference = (string) $this->saleElementsOf($this->product())->getRef();

        $component = $this->table();
        $component->set('rows', [self::row($reference, 1), self::row('OTHER', 'douze')]);
        $component->call('check');

        $table = $this->component($component);
        self::assertSame(1, $table->readyCount());
        self::assertTrue($table->hasInvalidQuantity(1));
    }

    private function table(): TestLiveComponent
    {
        return $this->createLiveComponent(self::COMPONENT, client: $this->client);
    }

    private function component(TestLiveComponent $component): \FlexyBundle\Components\Organisms\QuickOrderTable\Base
    {
        $table = $component->component();
        self::assertInstanceOf(\FlexyBundle\Components\Organisms\QuickOrderTable\Base::class, $table);

        return $table;
    }

    /**
     * @return array{key: string, reference: string, quantity: int|string, productSaleElementsId: null}
     */
    private static function row(string $reference, int|string $quantity): array
    {
        return ['key' => bin2hex(random_bytes(4)), 'reference' => $reference, 'quantity' => $quantity, 'productSaleElementsId' => null];
    }

    /**
     * @return list<float>
     */
    private function cartQuantitiesOf(Customer $customer): array
    {
        $cart = CartQuery::create()->filterByCustomerId($customer->getId())->orderById()->findOne();

        if (null === $cart) {
            return [];
        }

        return array_map(
            static fn ($item): float => (float) $item->getQuantity(),
            CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find()->getData(),
        );
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['baseQuantity' => 50]);
    }

    private function saleElementsOf(Product $product): ProductSaleElements
    {
        return $product->getProductSaleElementss()->getFirst()
            ?? throw new \LogicException('The product has no sale element.');
    }

    /**
     * @return array{Product, ProductSaleElements, ProductSaleElements}
     */
    private function productWithTwoSaleElementsSharingItsReference(): array
    {
        $product = $this->product();
        $default = $this->saleElementsOf($product);
        $default->setIsDefault(true)->save($this->getPropelConnection());
        $second = $this->factory->productSaleElement($product, ['ref' => $product->getRef(), 'quantity' => 50]);
        $this->factory->productPrice($second, $this->currency);

        return [$product, $default, $second];
    }

    private function url(): string
    {
        return $this->getService(RouterInterface::class)->generate(self::ROUTE);
    }
}

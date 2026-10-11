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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListItemQuery;
use Thelia\Model\CustomerListQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * "Save as a purchase list" from the cart, from a past order and from the quick order
 * table: into a new list, or added to a list the customer may change. The cart and the
 * order are posted as forms carrying the CSRF token of the list pages; the table is a
 * component action, posted with the props the quick order page rendered.
 *
 * The pages belong to the theme, which ships on its own release cycle: a theme older than
 * these routes is reported as skipped rather than failed.
 */
final class SaveToPurchaseListTest extends WebIntegrationTestCase
{
    private const FROM_CART_ROUTE = 'account_purchase_list_from_cart';

    private ?CustomerSessionInjector $injector = null;

    private FixtureFactory $factory;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get(self::FROM_CART_ROUTE)) {
            self::markTestSkipped('The installed front-office theme cannot save a cart as a purchase list.');
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

    public function testTheCartPageOffersToSaveTheCart(): void
    {
        $customer = $this->signedIn();
        $this->cartOf($customer, 2);

        $crawler = $this->client->request('GET', $this->url('checkout_cart'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(\sprintf('form.SaveToPurchaseList-form[action="%s"]', $this->url(self::FROM_CART_ROUTE))));
    }

    public function testTheCartIsSavedIntoANewList(): void
    {
        $customer = $this->signedIn();
        $reference = $this->cartOf($customer, 3);

        $this->client->request('POST', $this->url(self::FROM_CART_ROUTE), ['_token' => $this->token(), 'listId' => 'new', 'title' => 'From the cart']);

        $list = $this->onlyListOf($customer);
        self::assertSame('From the cart', $list->getTitle());
        self::assertSame([[$reference, 3]], $this->linesOf($list));
        self::assertResponseRedirects($this->url('account_purchase_list', ['listId' => $list->getId(), 'created' => 1]));
    }

    public function testTheCartIsAddedToAnExistingList(): void
    {
        $customer = $this->signedIn();
        $reference = $this->cartOf($customer, 3);
        $list = $this->lists()->create($customer, 'Existing', new ReferenceQuantityLines([new ReferenceQuantity('ALREADY-THERE', 1)]));

        $this->client->request('POST', $this->url(self::FROM_CART_ROUTE), ['_token' => $this->token(), 'listId' => (string) $list->getId(), 'title' => 'Ignored']);

        self::assertResponseRedirects($this->url('account_purchase_list', ['listId' => $list->getId(), 'appended' => 1]));
        self::assertSame(['Existing'], $this->titlesOf($customer));
        self::assertSame([['ALREADY-THERE', 1], [$reference, 3]], $this->linesOf($list));
    }

    public function testANewListWithoutANameSendsBackToTheCartWithAMessage(): void
    {
        $customer = $this->signedIn();
        $this->cartOf($customer, 1);

        $this->client->request('POST', $this->url(self::FROM_CART_ROUTE), ['_token' => $this->token(), 'listId' => 'new', 'title' => '  ']);

        self::assertResponseRedirects($this->url('checkout_cart', ['purchase_list_error' => 'title']));
        self::assertSame([], $this->titlesOf($customer));

        $crawler = $this->client->followRedirect();
        self::assertCount(1, $crawler->filter('.SaveToPurchaseList > .SnackBar--error'));
    }

    public function testAListOfAnotherAccountReceivesNothing(): void
    {
        $customer = $this->signedIn();
        $this->cartOf($customer, 1);
        $other = $this->customer();
        $list = $this->lists()->create($other, 'Not yours', new ReferenceQuantityLines([new ReferenceQuantity('THEIRS', 1)]));

        $this->client->request('POST', $this->url(self::FROM_CART_ROUTE), ['_token' => $this->token(), 'listId' => (string) $list->getId()]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame([['THEIRS', 1]], $this->linesOf($list));
    }

    public function testASaveWithoutAValidTokenChangesNothing(): void
    {
        $customer = $this->signedIn();
        $this->cartOf($customer, 1);
        $order = $this->orderOf($customer, 'FORGED-ORDER', 2);

        $this->client->request('POST', $this->url(self::FROM_CART_ROUTE), ['_token' => 'forged', 'listId' => 'new', 'title' => 'Forged']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $this->url('account_purchase_list_from_order', ['orderId' => $order->getId()]), ['_token' => 'forged', 'listId' => 'new', 'title' => 'Forged']);
        self::assertResponseStatusCodeSame(403);

        self::assertSame([], $this->titlesOf($customer));
    }

    public function testTheOrderPageOffersToSaveTheOrder(): void
    {
        $customer = $this->signedIn();
        $order = $this->orderOf($customer, 'SHOWN-ORDER', 1);

        $crawler = $this->client->request('GET', $this->url('account_order', ['orderId' => $order->getId()]));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(\sprintf('form.SaveToPurchaseList-form[action="%s"]', $this->url('account_purchase_list_from_order', ['orderId' => $order->getId()]))));
    }

    public function testAPastOrderIsSavedIntoANewList(): void
    {
        $customer = $this->signedIn();
        $order = $this->orderOf($customer, 'ORDERED-REF', 4);

        $this->client->request('POST', $this->url('account_purchase_list_from_order', ['orderId' => $order->getId()]), ['_token' => $this->token(), 'listId' => 'new', 'title' => 'From an order']);

        $list = $this->onlyListOf($customer);
        self::assertSame([['ORDERED-REF', 4]], $this->linesOf($list));
        self::assertResponseRedirects($this->url('account_purchase_list', ['listId' => $list->getId(), 'created' => 1]));
    }

    public function testTheOrderOfAnotherAccountAnswersAsAnOrderThatDoesNotExist(): void
    {
        $customer = $this->signedIn();
        $order = $this->orderOf($this->customer(), 'THEIR-ORDER', 1);

        $this->client->request('POST', $this->url('account_purchase_list_from_order', ['orderId' => $order->getId()]), ['_token' => $this->token(), 'listId' => 'new', 'title' => 'Not mine']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->titlesOf($customer));
    }

    public function testTheQuickOrderTableIsSavedIntoANewList(): void
    {
        $customer = $this->signedIn();
        $props = $this->propsOf($this->client->request('GET', $this->url('account_quick_order')));

        $this->callTable($props, 'saveAsList', [
            'rows' => [
                ['key' => 'a', 'reference' => 'TYPED-REF', 'quantity' => '6', 'productSaleElementsId' => null],
                ['key' => 'b', 'reference' => 'NOT-A-QUANTITY', 'quantity' => 'six', 'productSaleElementsId' => null],
            ],
            'saveTarget' => 'new',
            'saveTitle' => 'Typed in the table',
        ]);

        $list = $this->onlyListOf($customer);
        self::assertSame('Typed in the table', $list->getTitle());
        self::assertSame([['TYPED-REF', 6]], $this->linesOf($list));
        self::assertStringContainsString(
            $this->url('account_purchase_list', ['listId' => $list->getId(), 'created' => 1]),
            (string) $this->client->getResponse()->headers->get('Location'),
        );
    }

    public function testARefusedSaveFromTheTableSaysSoInTheDialog(): void
    {
        $customer = $this->signedIn();
        $props = $this->propsOf($this->client->request('GET', $this->url('account_quick_order')));

        $this->callTable($props, 'saveAsList', [
            'rows' => [['key' => 'a', 'reference' => 'TYPED-REF', 'quantity' => '6', 'productSaleElementsId' => null]],
            'saveTarget' => 'new',
            'saveTitle' => '   ',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, (new Crawler((string) $this->client->getResponse()->getContent()))->filter('dialog#quick-order-save-list .SnackBar--error'));
        self::assertSame([], $this->titlesOf($customer));
    }

    public function testTheQuickOrderTableIsAddedToAnExistingList(): void
    {
        $customer = $this->signedIn();
        $list = $this->lists()->create($customer, 'Existing', new ReferenceQuantityLines([new ReferenceQuantity('TYPED-REF', 1)]));
        $props = $this->propsOf($this->client->request('GET', $this->url('account_quick_order')));

        $this->callTable($props, 'saveAsList', [
            'rows' => [['key' => 'a', 'reference' => 'TYPED-REF', 'quantity' => '6', 'productSaleElementsId' => null]],
            'saveTarget' => (string) $list->getId(),
        ]);

        self::assertSame([['TYPED-REF', 7]], $this->linesOf($list));
    }

    public function testTheQuickOrderTableCannotFillAListOfAnotherAccount(): void
    {
        $this->signedIn();
        $list = $this->lists()->create($this->customer(), 'Not yours', new ReferenceQuantityLines([new ReferenceQuantity('THEIRS', 1)]));
        $props = $this->propsOf($this->client->request('GET', $this->url('account_quick_order')));

        $this->client->catchExceptions(false);

        try {
            $this->callTable($props, 'saveAsList', [
                'rows' => [['key' => 'a', 'reference' => 'TYPED-REF', 'quantity' => '6', 'productSaleElementsId' => null]],
                'saveTarget' => (string) $list->getId(),
            ]);
            self::fail('The list of another account was filled.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
        }

        self::assertSame([['THEIRS', 1]], $this->linesOf($list));
    }

    private function signedIn(): Customer
    {
        $customer = $this->customer();
        $this->injector?->setCustomer($customer);

        return $customer;
    }

    /**
     * A cart of the customer with one line, pointed at by the session.
     */
    private function cartOf(Customer $customer, int $quantity): string
    {
        $saleElements = $this->saleElements();
        $cart = $this->factory->cart($customer, ['currencyId' => $this->currency->getId()]);
        $this->factory->cartItem($cart, $saleElements->getProduct(), $saleElements, ['quantity' => (float) $quantity]);
        $this->injector?->setCart($cart);

        return (string) $saleElements->getRef();
    }

    private function orderOf(Customer $customer, string $reference, int $quantity): Order
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef($reference)
            ->setProductSaleElementsRef($reference)
            ->setTitle('An ordered line')
            ->setQuantity((float) $quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('0.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setVirtual(0)
            ->save($this->getPropelConnection());

        return $order;
    }

    /**
     * The token the list pages put in their forms, read from the page of the lists.
     */
    private function token(): string
    {
        $crawler = $this->client->request('GET', $this->url('account_purchase_lists'));

        return (string) $crawler->filter('.AccountPurchaseLists-create input[name="_token"]')->attr('value');
    }

    /**
     * @return array<string, mixed>
     */
    private function propsOf(Crawler $crawler): array
    {
        $node = $crawler->filter('.QuickOrderTable[data-live-props-value]');
        self::assertCount(1, $node);

        return json_decode((string) $node->attr('data-live-props-value'), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $updated
     */
    private function callTable(array $props, string $action, array $updated): void
    {
        $this->client->request(
            'POST',
            $this->getService(RouterInterface::class)->generate('ux_live_component', [
                '_live_component' => 'Organisms:QuickOrderTable:Base',
                '_live_action' => $action,
            ]),
            ['data' => json_encode(['props' => $props, 'updated' => $updated, 'args' => []], \JSON_THROW_ON_ERROR)],
            server: ['HTTP_ACCEPT' => 'application/vnd.live-component+html', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
    }

    private function onlyListOf(Customer $customer): CustomerList
    {
        $lists = CustomerListQuery::create()->filterByCustomerId($customer->getId())->find();
        self::assertCount(1, $lists);

        return $lists->getFirst() ?? throw new \LogicException('No list.');
    }

    /**
     * @return list<string>
     */
    private function titlesOf(Customer $customer): array
    {
        return array_map(
            static fn (CustomerList $list): string => (string) $list->getTitle(),
            CustomerListQuery::create()->filterByCustomerId($customer->getId())->orderById()->find()->getData(),
        );
    }

    /**
     * @return list<array{string, int}>
     */
    private function linesOf(CustomerList $list): array
    {
        $lines = [];

        foreach (CustomerListItemQuery::create()->filterByCustomerListId($list->getId())->orderByPosition()->find() as $item) {
            $lines[] = [(string) $item->getRef(), (int) $item->getQuantity()];
        }

        return $lines;
    }

    private function lists(): PurchaseListFacade
    {
        return $this->getService(PurchaseListFacade::class);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function saleElements(): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['baseQuantity' => 50]);

        return $product->getProductSaleElementss()->getFirst()
            ?? throw new \LogicException('The product has no sale element.');
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function url(string $route, array $parameters = []): string
    {
        return $this->getService(RouterInterface::class)->generate($route, $parameters);
    }
}

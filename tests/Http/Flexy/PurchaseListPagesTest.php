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
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListItemQuery;
use Thelia\Model\CustomerListQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * The purchase lists of the Flexy customer account: the page that lists them, the writes
 * on a whole list (each a POST with a CSRF token), and the detail page, which opens the
 * list in the quick order table and saves its lines back.
 *
 * The component action is posted the way the browser posts it, with the props the detail
 * page rendered: the component needs the customer of the session to mount on a list, which
 * only a real request carries.
 *
 * The pages belong to the theme, which ships on its own release cycle: a theme older than
 * these routes is reported as skipped rather than failed.
 */
final class PurchaseListPagesTest extends WebIntegrationTestCase
{
    private const INDEX_ROUTE = 'account_purchase_lists';

    private ?CustomerSessionInjector $injector = null;

    private FixtureFactory $factory;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get(self::INDEX_ROUTE)) {
            self::markTestSkipped('The installed front-office theme has no purchase list pages.');
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

    public function testThePageShowsTheListsOfTheCustomerOnly(): void
    {
        $customer = $this->signedIn();
        $this->lists()->create($customer, 'Monday restock');
        $this->lists()->create($this->customer(), 'Someone else');

        $crawler = $this->client->request('GET', $this->url(self::INDEX_ROUTE));

        self::assertResponseIsSuccessful();
        self::assertSame(['Monday restock'], $crawler->filter('.AccountPurchaseLists-title')->each(static fn (Crawler $node): string => trim($node->text())));
    }

    public function testAVisitorIsSentToSignIn(): void
    {
        $this->client->request('GET', $this->url(self::INDEX_ROUTE));

        self::assertResponseRedirects();
    }

    public function testAListOfAnotherAccountAnswersAsAListThatDoesNotExist(): void
    {
        $this->signedIn();
        $list = $this->lists()->create($this->customer(), 'Not yours');

        $this->client->request('GET', $this->url('account_purchase_list', ['listId' => $list->getId()]));

        self::assertResponseStatusCodeSame(404);
    }

    public function testAListIsCreatedAndOpened(): void
    {
        $customer = $this->signedIn();

        $this->client->request('POST', $this->url('account_purchase_list_create'), ['_token' => $this->token(), 'title' => 'Workshop']);

        $list = CustomerListQuery::create()->filterByCustomerId($customer->getId())->findOne();
        self::assertInstanceOf(CustomerList::class, $list);
        self::assertSame('Workshop', $list->getTitle());
        self::assertResponseRedirects($this->url('account_purchase_list', ['listId' => $list->getId(), 'created' => 1]));
    }

    public function testAWriteWithoutAValidTokenChangesNothing(): void
    {
        $customer = $this->signedIn();
        $list = $this->lists()->create($customer, 'Kept');

        $this->client->request('POST', $this->url('account_purchase_list_create'), ['_token' => 'forged', 'title' => 'Forged']);
        $this->client->request('POST', $this->url('account_purchase_list_delete', ['listId' => $list->getId()]), ['_token' => 'forged']);

        self::assertSame(['Kept'], $this->titlesOf($customer));
    }

    public function testAListIsRenamedDuplicatedAndDeleted(): void
    {
        $customer = $this->signedIn();
        $list = $this->lists()->create($customer, 'Before', new ReferenceQuantityLines([new ReferenceQuantity('VIS-M6', 3)]));

        $this->client->request('POST', $this->url('account_purchase_list_rename', ['listId' => $list->getId()]), ['_token' => $this->token(), 'title' => 'After']);
        self::assertSame(['After'], $this->titlesOf($customer));

        $this->client->request('POST', $this->url('account_purchase_list_duplicate', ['listId' => $list->getId()]), ['_token' => $this->token()]);
        self::assertSame(['After', 'After'], $this->titlesOf($customer));
        self::assertSame(2, CustomerListItemQuery::create()->useCustomerListQuery()->filterByCustomerId($customer->getId())->endUse()->count());

        $this->client->request('POST', $this->url('account_purchase_list_delete', ['listId' => $list->getId()]), ['_token' => $this->token()]);
        self::assertResponseRedirects($this->url(self::INDEX_ROUTE, ['deleted' => 1]));
        self::assertSame(['After'], $this->titlesOf($customer));
    }

    public function testAListOfAnotherAccountCannotBeDeleted(): void
    {
        $this->signedIn();
        $other = $this->customer();
        $list = $this->lists()->create($other, 'Not yours');

        $this->client->request('POST', $this->url('account_purchase_list_delete', ['listId' => $list->getId()]), ['_token' => $this->token()]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['Not yours'], $this->titlesOf($other));
    }

    public function testTheHundredAndFirstListIsRefusedWithAMessage(): void
    {
        $customer = $this->signedIn();

        for ($n = 1; $n <= PurchaseListFacade::MAX_LISTS_PER_CUSTOMER; ++$n) {
            $this->lists()->create($customer, 'List '.$n);
        }

        $this->client->request('POST', $this->url('account_purchase_list_create'), ['_token' => $this->token(), 'title' => 'One too many']);

        self::assertResponseRedirects($this->url(self::INDEX_ROUTE, ['error' => 'create']));
        self::assertCount(PurchaseListFacade::MAX_LISTS_PER_CUSTOMER, $this->titlesOf($customer));
    }

    public function testTheDetailOpensTheListCheckedInTheTable(): void
    {
        $customer = $this->signedIn();
        $reference = (string) $this->saleElements()->getRef();
        $list = $this->lists()->create($customer, 'Restock', new ReferenceQuantityLines([new ReferenceQuantity($reference, 2), new ReferenceQuantity('GONE-404', 1)]));

        $crawler = $this->client->request('GET', $this->url('account_purchase_list', ['listId' => $list->getId()]));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.QuickOrderTable-row--resolved'));
        self::assertCount(1, $crawler->filter('.QuickOrderTable-row--unknown'));
    }

    public function testSavingTheTableReplacesTheLinesOfTheList(): void
    {
        $customer = $this->signedIn();
        $reference = (string) $this->saleElements()->getRef();
        $list = $this->lists()->create($customer, 'Restock', new ReferenceQuantityLines([new ReferenceQuantity($reference, 2)]));
        $crawler = $this->client->request('GET', $this->url('account_purchase_list', ['listId' => $list->getId()]));
        $props = $this->propsOf($crawler);

        $rows = $props['rows'];
        $rows[0]['quantity'] = '5';
        $rows[] = ['key' => 'new', 'reference' => 'NOT-YET-IN-THE-SHOP', 'quantity' => '1', 'productSaleElementsId' => null];
        $this->callTable($props, 'saveList', ['rows' => $rows]);

        self::assertResponseIsSuccessful();
        self::assertSame([[$reference, 5], ['NOT-YET-IN-THE-SHOP', 1]], $this->linesOf($list));
    }

    public function testASaleElementTheCheckDidNotOfferIsNotSaved(): void
    {
        $customer = $this->signedIn();
        $reference = (string) $this->saleElements()->getRef();
        $foreign = $this->saleElements();
        $list = $this->lists()->create($customer, 'Restock', new ReferenceQuantityLines([new ReferenceQuantity($reference, 2)]));
        $props = $this->propsOf($this->client->request('GET', $this->url('account_purchase_list', ['listId' => $list->getId()])));

        $rows = $props['rows'];
        $rows[0]['productSaleElementsId'] = (string) $foreign->getId();
        $this->callTable($props, 'saveList', ['rows' => $rows]);

        $item = CustomerListItemQuery::create()->filterByCustomerListId($list->getId())->findOne();
        self::assertNotNull($item);
        self::assertNotSame((int) $foreign->getId(), (int) $item->getProductSaleElementsId());
    }

    private function signedIn(): Customer
    {
        $customer = $this->customer();
        $this->injector?->setCustomer($customer);

        return $customer;
    }

    /**
     * The token the pages put in their forms, read from the page of the lists.
     */
    private function token(): string
    {
        $crawler = $this->client->request('GET', $this->url(self::INDEX_ROUTE));

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

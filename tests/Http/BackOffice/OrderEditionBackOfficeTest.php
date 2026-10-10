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

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusActionQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The lines of an order changed from the back office: the sheet offers it or says why
 * not, the edit page previews the totals before anything is written, and saving applies
 * the edit or explains why it cannot.
 */
final class OrderEditionBackOfficeTest extends WebIntegrationTestCase
{
    private ?AdminSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        // A skip rather than a failure: the core ships with whichever back-office theme it
        // is given, and one that predates the order edition has no such page.
        if (!class_exists('BackOfficeDefaultTwigBundle\\Controller\\Order\\OrderEditionController')) {
            self::markTestSkipped('The installed back-office theme predates the order edition.');
        }

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();
        parent::tearDown();
    }

    public function testTheSheetOffersToEditAnOrderNotSentYet(): void
    {
        $this->loginAs($this->factory()->admin());
        $order = $this->order(OrderStatus::CODE_PAID, [[$this->product(50.0), 1]]);

        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());

        self::assertCount(1, $crawler->filter('[data-testid="order-edit-lines-btn"]'));
    }

    public function testTheSheetSaysWhyAnInvoicedOrderCannotBeEdited(): void
    {
        $this->loginAs($this->factory()->admin());
        $order = $this->order(OrderStatus::CODE_PAID, [[$this->product(50.0), 1]]);
        $order->setInvoiceRef('2026-000999')->save($this->getPropelConnection());

        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());

        self::assertCount(0, $crawler->filter('[data-testid="order-edit-lines-btn"]'));
        self::assertStringContainsString('credit note', $crawler->filter('[data-testid="order-edit-lines-refusal"]')->text(''));
    }

    public function testAPreviewShowsTheNewTotalAndChangesNothing(): void
    {
        $this->loginAs($this->factory()->admin());
        $order = $this->order(OrderStatus::CODE_PAID, [[$this->product(50.0), 1]]);
        $line = $this->lines($order)[0];

        $crawler = $this->submit($order, ['lines' => [$line->getId() => ['quantity' => '3']]], 'preview');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('180.00', $crawler->filter('[data-testid="order-edit-total-after"]')->text());
        self::assertSame(1.0, (float) OrderProductQuery::create()->findPk($line->getId())->getQuantity());
    }

    public function testSavingAppliesTheEditAndSaysWhatToRefund(): void
    {
        $this->loginAs($this->factory()->admin());
        $kept = $this->product(50.0);
        $order = $this->order(OrderStatus::CODE_PAID, [[$kept, 2], [$this->product(20.0), 1]]);
        $lines = $this->lines($order);

        $this->submit($order, ['lines' => [
            $lines[0]->getId() => ['quantity' => '2'],
            $lines[1]->getId() => ['quantity' => '1', 'remove' => '1'],
        ]], 'save');

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $crawler = $this->client->followRedirect();
        self::assertCount(1, $this->lines($order));
        self::assertStringContainsString('24.00', $crawler->filter('[data-testid="bo-flash-warning"]')->text(''), 'The 20 + 20% removed from a paid order is to be refunded.');
    }

    public function testALineIsAddedByTheReferenceOfItsProduct(): void
    {
        $this->loginAs($this->factory()->admin());
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0), 1]]);
        $added = $this->product(30.0);
        $line = $this->lines($order)[0];

        $this->submit($order, [
            'lines' => [$line->getId() => ['quantity' => '1']],
            'new' => [0 => ['reference' => $this->pseOf($added)->getRef(), 'quantity' => '2']],
        ], 'save');

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertNotNull(OrderProductQuery::create()->filterByOrderId($order->getId())->filterByProductRef($added->getRef())->findOne());
    }

    public function testAnEditComposedOnAStateThatChangedIsRefused(): void
    {
        $this->loginAs($this->factory()->admin());
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0), 1]]);
        $line = $this->lines($order)[0];
        $crawler = $this->client->request('GET', '/admin/order/'.$order->getId().'/edit-lines');
        $form = $crawler->filter('[data-testid="order-edit-save"]')->form();
        $values = $form->getPhpValues();
        OrderProductQuery::create()->findPk($line->getId())->setQuantity(5)->save($this->getPropelConnection());
        $values['lines'][$line->getId()]['quantity'] = '2';
        $values['save'] = '1';

        $crawler = $this->client->request('POST', $form->getUri(), $values);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('changed since', $crawler->filter('[data-testid="order-edit-error"]')->text(''));
        self::assertSame(5.0, (float) OrderProductQuery::create()->findPk($line->getId())->getQuantity());
    }

    public function testAFormThatLostALineOnTheWayRemovesNothing(): void
    {
        $this->loginAs($this->factory()->admin());
        $order = $this->order(OrderStatus::CODE_NOT_PAID, [[$this->product(50.0), 1], [$this->product(20.0), 1]]);
        $lines = $this->lines($order);
        $crawler = $this->client->request('GET', '/admin/order/'.$order->getId().'/edit-lines');
        $form = $crawler->filter('[data-testid="order-edit-save"]')->form();
        $values = $form->getPhpValues();
        // What a request cut short by an input limit sends: the second line never arrives.
        unset($values['lines'][$lines[1]->getId()], $values['preview']);
        $values['save'] = '1';

        $crawler = $this->client->request('POST', $form->getUri(), $values);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertNotSame('', $crawler->filter('[data-testid="order-edit-error"]')->text(''));
        self::assertCount(2, $this->lines($order));
    }

    public function testWithoutTheEditionRightThereIsNoButtonAndNoPage(): void
    {
        $this->loginAs($this->factory()->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW, AccessManager::UPDATE]]));
        $order = $this->order(OrderStatus::CODE_PAID, [[$this->product(50.0), 1]]);

        $crawler = $this->client->request('GET', '/admin/order/update/'.$order->getId());
        self::assertCount(0, $crawler->filter('[data-testid="order-edit-lines-btn"]'));

        $this->client->request('GET', '/admin/order/'.$order->getId().'/edit-lines');
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTheShopChoosesAnEmailToSendWhenAnOrderInAStatusIsEdited(): void
    {
        $this->loginAs($this->factory()->admin());
        $paidId = (int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)->getId();
        $crawler = $this->client->request('GET', '/admin/configuration/order-status/update/'.$paidId);
        self::assertCount(1, $crawler->filter('input[name="trigger"][value="edit"]'));
        $token = (string) $crawler->filter('form[action$="/actions/'.$paidId.'/create"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/configuration/order-status/actions/'.$paidId.'/create', [
            '_token' => $token,
            'trigger' => 'edit',
            'action_type' => 'send_customer_email',
            'payload' => ['send_customer_email' => ['message_code' => 'order_edited']],
        ]);
        $this->client->request('POST', '/admin/configuration/order-status/actions/'.$paidId.'/create', [
            '_token' => $token,
            'trigger' => 'edit',
            'action_type' => 'adjust_stock',
            'payload' => ['adjust_stock' => ['operation' => 'decrease']],
        ]);

        $actions = OrderStatusActionQuery::create()->filterByToStatusId($paidId)->filterByTriggerType('edit')->find();
        self::assertCount(1, $actions, 'Only an e-mail runs when an order is edited.');
        self::assertSame('send_customer_email', $actions->getFirst()->getActionType());
        $crawler = $this->client->request('GET', '/admin/configuration/order-status/update/'.$paidId);
        self::assertStringContainsString('When an order in this status is edited', (string) $this->client->getResponse()->getContent());
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function submit(Order $order, array $fields, string $button): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/order/'.$order->getId().'/edit-lines');
        $form = $crawler->filter('[data-testid="order-edit-save"]')->form();
        $values = array_replace_recursive($form->getPhpValues(), $fields);
        // The form read from the save button carries it: the button pressed is set here.
        unset($values['save'], $values['preview']);
        $values[$button] = '1';

        return $this->client->request('POST', $form->getUri(), $values);
    }

    /**
     * @return list<OrderProduct>
     */
    private function lines(Order $order): array
    {
        return iterator_to_array(OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find(), false);
    }

    private function product(float $price): Product
    {
        $factory = $this->factory();
        $product = $factory->product($factory->category(), TaxRuleQuery::create()->findOneByIsDefault(1), CurrencyQuery::create()->findOneByByDefault(1), ['baseQuantity' => 10, 'title' => 'Edited product']);
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->pseOf($product)->getId())->update(['Price' => $price, 'PromoPrice' => $price], $this->getPropelConnection());

        return $product;
    }

    private function pseOf(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne($this->getPropelConnection()) ?? throw new \LogicException('No sale element.');
    }

    /**
     * @param list<array{Product, int}> $lines
     */
    private function order(string $statusCode, array $lines): Order
    {
        $connection = $this->getPropelConnection();
        $order = $this->factory()->order(null, ['statusCode' => $statusCode]);
        $order->setCurrencyId((int) CurrencyQuery::create()->findOneByByDefault(1)->getId())->save($connection);
        OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId(), $connection)
            ->setCountryId((int) CountryQuery::create()->findOneByIsoalpha2('FR')->getId())
            ->save($connection);

        foreach ($lines as [$product, $quantity]) {
            $pse = $this->pseOf($product);
            $price = (float) ProductPriceQuery::create()->findOneByProductSaleElementsId($pse->getId(), $connection)->getPrice();
            $line = (new OrderProduct())
                ->setOrderId($order->getId())
                ->setProductRef($product->getRef())
                ->setProductSaleElementsRef($pse->getRef())
                ->setProductSaleElementsId($pse->getId())
                ->setTitle('Edited product')
                ->setQuantity($quantity)
                ->setPrice((string) $price)
                ->setPromoPrice((string) $price)
                ->setWasNew(0)
                ->setWasInPromo(0);
            $line->save($connection);
            (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT 20%')->setAmount((string) round($price * 0.2, 2))->setPromoAmount((string) round($price * 0.2, 2))->save($connection);
        }

        return $order;
    }

    private function factory(): FixtureFactory
    {
        // Deliberately not createFixtureFactory(): see TagConfigurationScreenTest.
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector?->setAdmin($admin);
    }
}

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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * The return form of an order that holds a bundle: the pack is what a customer returns, its components are not
 * offered on their own, and a request that names one anyway comes back to the form with the sentence of the shop
 * (the core states it in English, the theme translates it).
 *
 * The pages belong to the theme, which ships as its own package on its own release cycle: a theme older than the
 * bundles is reported as skipped rather than failed.
 */
final class AccountOrderReturnBundleTest extends WebIntegrationTestCase
{
    private const FORM_ROUTE = 'account_order_return_new';

    private ?CustomerSessionInjector $injector = null;

    private ?string $previousEnabled = null;

    private ?string $previousWindow = null;

    protected function setUp(): void
    {
        if (!class_exists(\FlexyBundle\Components\Organisms\BundleComposition\Base::class)) {
            self::markTestSkipped('The installed front-office theme does not know the product bundles.');
        }

        parent::setUp();

        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get(self::FORM_ROUTE)) {
            self::markTestSkipped('The installed front-office theme has no order return pages.');
        }

        $this->previousEnabled = ConfigQuery::read(ReturnEligibilityChecker::ENABLED_CONFIG_KEY);
        $this->previousWindow = ConfigQuery::read(ReturnEligibilityChecker::WINDOW_CONFIG_KEY);
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
        ConfigQuery::write(ReturnEligibilityChecker::WINDOW_CONFIG_KEY, '14');

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, (string) $this->previousEnabled);
        ConfigQuery::write(ReturnEligibilityChecker::WINDOW_CONFIG_KEY, (string) $this->previousWindow);

        parent::tearDown();
    }

    public function testTheFormOffersThePackAndNotItsComponents(): void
    {
        [$customer, $order, $pack] = $this->orderOfAPack();

        $crawler = $this->openForm($customer, $order);

        self::assertCount(1, $crawler->filter('input[name="lines['.$pack->getId().'][selected]"]'));
        self::assertCount(1, $crawler->filter('input[type="checkbox"][name^="lines["]'), 'one line offered: the pack');
    }

    public function testAComponentNamedAnywayIsRefusedWithTheSentenceOfTheShop(): void
    {
        [$customer, $order, , $component] = $this->orderOfAPack();
        $crawler = $this->openForm($customer, $order);
        $form = $crawler->filter('form[method="POST"]')->form();

        // The browser is made to ask for a line the form does not offer.
        $values = $form->getPhpValues();
        $values['lines'][$component->getId()] = ['selected' => '1', 'quantity' => '1'];
        $values['resolution'] = 'refund';
        $this->client->request('POST', $form->getUri(), $values);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertNull(OrderReturnQuery::create()->filterByOrderId($order->getId())->findOne(), 'nothing is written');

        $this->client->followRedirect();

        self::assertStringContainsString(
            'This item is part of a bundle and cannot be returned on its own.',
            (string) $this->client->getResponse()->getContent(),
            'the form says why, in the words of the theme catalogue',
        );
    }

    private function openForm(Customer $customer, Order $order): Crawler
    {
        $this->injector?->setCustomer($customer);
        $crawler = $this->client->request('GET', $this->getService(RouterInterface::class)->generate(self::FORM_ROUTE, ['orderId' => $order->getId()]));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }

    /**
     * @return array{0: Customer, 1: Order, 2: OrderProduct, 3: OrderProduct}
     */
    private function orderOfAPack(): array
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $customer = $factory->customer($factory->customerTitle());
        $order = $factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setCreatedAt(new \DateTime('-1 days'));
        $order->save($this->getPropelConnection());

        $pack = $factory->orderProduct($order, ['quantity' => 1.0, 'title' => 'Bike pack to send back', 'price' => '100.000000', 'lineType' => OrderProduct::LINE_TYPE_BUNDLE]);
        $component = $factory->orderProduct($order, ['quantity' => 1.0, 'title' => 'Bike of the pack', 'price' => '0', 'promoPrice' => '0', 'lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT]);
        $component->setParent($pack->getId())->save($this->getPropelConnection());

        return [$customer, $order, $pack, $component];
    }
}

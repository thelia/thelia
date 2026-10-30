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
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * The order of a customer who bought a bundle, on the account pages of the theme: the components are listed under the
 * line of the pack, with no price of their own, and are not counted as articles bought. The order list counts the
 * pack once.
 *
 * The pages belong to the theme, which ships as its own package on its own release cycle: a theme older than the
 * bundles is reported as skipped rather than failed.
 */
final class AccountOrderBundleLinesTest extends WebIntegrationTestCase
{
    private ?CustomerSessionInjector $injector = null;

    protected function setUp(): void
    {
        if (!class_exists(\FlexyBundle\Components\Organisms\BundleComposition\Base::class)) {
            self::markTestSkipped('The installed front-office theme does not know the product bundles.');
        }

        parent::setUp();

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
    }

    public function testTheComponentsHangUnderTheLineOfTheirPackWithoutAPrice(): void
    {
        [$customer, $order] = $this->orderOfTwoPacksAndAMug();

        $crawler = $this->open($customer, $this->url('account_order', ['orderId' => $order->getId()]));

        self::assertCount(2, $crawler->filter('.ProductCard--order'), 'a card for the pack and one for the mug, none for the components');
        $included = $crawler->filter('[data-testid="bundle-included"]');
        self::assertCount(1, $included);
        self::assertStringContainsString('Included in the bundle', $included->text());
        $items = $included->filter('[data-testid="bundle-included-item"]');
        self::assertCount(2, $items);
        // Two packs bought, each holds 1 bike and 2 helmets: the order stored 2 and 4.
        self::assertSame('1 × Bike of the pack', preg_replace('/\s+/', ' ', trim($items->eq(0)->text())));
        self::assertSame('2 × Helmet of the pack', preg_replace('/\s+/', ' ', trim($items->eq(1)->text())));
        self::assertDoesNotMatchRegularExpression('/\d\.\d\d|[€$£]/', $included->text(), 'no price on a component');
    }

    public function testTheComponentsAreNotArticlesBought(): void
    {
        [$customer, $order] = $this->orderOfTwoPacksAndAMug();

        $crawler = $this->open($customer, $this->url('account_order', ['orderId' => $order->getId()]));

        $line = $crawler->filter('.Summary-line')->reduce(static fn (Crawler $node): bool => str_contains($node->text(), 'Number of articles'));
        self::assertCount(1, $line);
        self::assertSame('3', trim($line->filter('div')->last()->text()), 'two packs and a mug, not the 6 components more');
    }

    public function testTheOrderListCountsThePackOnce(): void
    {
        [$customer] = $this->orderOfTwoPacksAndAMug();

        $crawler = $this->open($customer, $this->url('account_orders'));

        self::assertStringContainsString('2 items', preg_replace('/\s+/', ' ', $crawler->filter('main')->text()));
    }

    /**
     * @return array{0: Customer, 1: Order}
     */
    private function orderOfTwoPacksAndAMug(): array
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $customer = $factory->customer($factory->customerTitle());
        $order = $factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        $pack = $factory->orderProduct($order, ['quantity' => 2.0, 'title' => 'Bike pack of the order', 'price' => '600.000000', 'promoPrice' => '540.000000', 'lineType' => OrderProduct::LINE_TYPE_BUNDLE]);
        $this->component($factory, $order, $pack, 'Bike of the pack', 2.0);
        $this->component($factory, $order, $pack, 'Helmet of the pack', 4.0);
        $factory->orderProduct($order, ['quantity' => 1.0, 'title' => 'Plain mug']);

        return [$customer, $order];
    }

    private function component(FixtureFactory $factory, Order $order, OrderProduct $pack, string $title, float $quantity): void
    {
        $factory->orderProduct($order, ['quantity' => $quantity, 'title' => $title, 'price' => '0', 'promoPrice' => '0', 'lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT])
            ->setParent($pack->getId())
            ->save($this->getPropelConnection());
    }

    private function open(Customer $customer, string $url): Crawler
    {
        $this->injector?->setCustomer($customer);
        $crawler = $this->client->request('GET', $url);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function url(string $route, array $parameters = []): string
    {
        return $this->getService(RouterInterface::class)->generate($route, $parameters);
    }
}

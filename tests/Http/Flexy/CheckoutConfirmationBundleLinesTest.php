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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\GuestOrderTokenInjector;

/**
 * The confirmation page after an order holding a bundle: the components are listed under the line of the pack with
 * their quantity and no price, as on the account pages.
 *
 * The page belongs to the theme, which ships as its own package on its own release cycle: a theme older than the
 * bundles is reported as skipped rather than failed.
 */
final class CheckoutConfirmationBundleLinesTest extends WebIntegrationTestCase
{
    private GuestOrderTokenInjector $tokenInjector;

    /** @var EventSubscriberInterface&object{orderId: int|null} */
    private object $placedOrderInjector;

    protected function setUp(): void
    {
        if (!class_exists(\FlexyBundle\Components\Molecules\BundleIncluded\Base::class)) {
            self::markTestSkipped('The installed front-office theme does not know the product bundles.');
        }

        parent::setUp();

        $this->tokenInjector = new GuestOrderTokenInjector();
        $this->placedOrderInjector = new class implements EventSubscriberInterface {
            public ?int $orderId = null;

            public function onKernelRequest(RequestEvent $event): void
            {
                if (null === $this->orderId || !$event->getRequest()->hasSession(true)) {
                    return;
                }

                $session = $event->getRequest()->getSession();

                if (!$session->isStarted()) {
                    $session->start();
                }

                // What the checkout remembers when it places an order.
                $session->set('flexy.placed_order_id', $this->orderId);
                $this->orderId = null;
            }

            public static function getSubscribedEvents(): array
            {
                return [KernelEvents::REQUEST => ['onKernelRequest', 200]];
            }
        };

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->tokenInjector);
        $dispatcher->addSubscriber($this->placedOrderInjector);
    }

    protected function tearDown(): void
    {
        if (isset($this->tokenInjector)) {
            $this->tokenInjector->clear();
            $dispatcher = $this->getService(EventDispatcherInterface::class);
            $dispatcher->removeSubscriber($this->tokenInjector);
            $dispatcher->removeSubscriber($this->placedOrderInjector);
        }

        parent::tearDown();
    }

    public function testTheConfirmationListsTheComponentsUnderThePackWithTheirQuantityAndNoPrice(): void
    {
        $order = $this->placedOrderOfTwoPacksAndAMug();

        $crawler = $this->client->request('GET', '/checkout/confirm');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('[data-testid="order-recap"]'), 'the recap of the placed order is on the page');
        $included = $crawler->filter('[data-testid="order-recap"] [data-testid="bundle-included"]');
        self::assertCount(1, $included, \sprintf('one block of components for the pack of order %s', $order->getRef()));
        self::assertStringContainsString('Included in the bundle', $included->text());
        $items = $included->filter('[data-testid="bundle-included-item"]');
        self::assertCount(2, $items);
        // Two packs bought, each holds 1 bike and 2 helmets: the order stored 2 and 4.
        self::assertSame('1 × Bike of the pack', preg_replace('/\s+/', ' ', trim($items->eq(0)->text())));
        self::assertSame('2 × Helmet of the pack', preg_replace('/\s+/', ' ', trim($items->eq(1)->text())));
        self::assertDoesNotMatchRegularExpression('/\d\.\d\d|[€$£]/', $included->text(), 'no price on a component');
    }

    private function placedOrderOfTwoPacksAndAMug(): Order
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()), ['statusCode' => OrderStatus::CODE_NOT_PAID]);

        $pack = $factory->orderProduct($order, ['quantity' => 2.0, 'title' => 'Bike pack of the order', 'price' => '600.000000', 'promoPrice' => '540.000000', 'lineType' => OrderProduct::LINE_TYPE_BUNDLE]);
        $this->component($factory, $order, $pack, 'Bike of the pack', 2.0);
        $this->component($factory, $order, $pack, 'Helmet of the pack', 4.0);
        $factory->orderProduct($order, ['quantity' => 1.0, 'title' => 'Plain mug']);

        // The guest retires from the session once the order exists: the token stands in for them.
        $this->tokenInjector->setToken($this->getService(GuestOrderAccessService::class)->createToken($order));
        $this->placedOrderInjector->orderId = $order->getId();
        $this->client->request('GET', '/');

        return $order;
    }

    private function component(FixtureFactory $factory, Order $order, OrderProduct $pack, string $title, float $quantity): void
    {
        $factory->orderProduct($order, ['quantity' => $quantity, 'title' => $title, 'price' => '0', 'promoPrice' => '0', 'lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT])
            ->setParent($pack->getId())
            ->save($this->getPropelConnection());
    }
}

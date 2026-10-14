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

namespace Thelia\Tests\Integration\Domain\Webhook;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Webhook\EventListener\PublishWebhookEventsListener;
use Thelia\Domain\Webhook\ProductChangeFingerprint;
use Thelia\Domain\Webhook\WebhookEventCatalog;
use Thelia\Domain\Webhook\WebhookPayloadBuilder;
use Thelia\Domain\Webhook\WebhookPublisher;
use Thelia\Domain\Webhook\WebhookSigner;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\WebhookDeliveryQuery;
use Thelia\Model\WebhookSubscription;
use Thelia\Model\WebhookSubscriptionQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class WebhookPublishingTest extends IntegrationTestCase
{
    private const SECRET = 'whsec_integration';

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string}> */
    private array $requests = [];

    private int $answerCode = 200;
    private string $answerBody = 'ok';
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WebhookSubscriptionQuery::create()->deleteAll($this->getPropelConnection());
        $this->requests = [];
        $this->answerCode = 200;
        $this->answerBody = 'ok';
        $this->factory = $this->createFixtureFactory();

        static::getContainer()->set('thelia.webhook.http_client', new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $headers = [];
            foreach ($options['headers'] as $line) {
                [$name, $value] = explode(': ', (string) $line, 2);
                $headers[$name] = $value;
            }
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => (string) $options['body']];

            return new MockResponse($this->answerBody, ['http_code' => $this->answerCode]);
        }));
    }

    public function testAnActiveSubscriptionReceivesASignedRequestWithTheOrderAsTheAdminApiShowsIt(): void
    {
        $this->subscribe(['order.created']);
        $order = $this->order();

        $this->listener()->onOrderPlaced(new OrderEvent($order));

        self::assertCount(1, $this->requests);
        $request = $this->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://hooks.example.com/in', $request['url']);

        $body = json_decode($request['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('order.created', $body['type']);
        self::assertSame($request['headers'][WebhookSigner::HEADER_ID], $body['id']);
        self::assertSame($order->getId(), $body['data']['id']);
        self::assertSame($order->getRef(), $body['data']['ref']);

        $timestamp = (int) $request['headers'][WebhookSigner::HEADER_TIMESTAMP];
        self::assertTrue((new WebhookSigner())->verify($request['body'], self::SECRET, $timestamp, $request['headers'][WebhookSigner::HEADER_SIGNATURE], time()));
        self::assertFalse((new WebhookSigner())->verify($request['body'].' ', self::SECRET, $timestamp, $request['headers'][WebhookSigner::HEADER_SIGNATURE], time()));
    }

    public function testAnInactiveSubscriptionAndOneOnAnotherEventReceiveNothing(): void
    {
        $this->subscribe(['order.created'], status: WebhookStatus::SUBSCRIPTION_INACTIVE);
        $this->subscribe(['customer.created']);

        $this->listener()->onOrderPlaced(new OrderEvent($this->order()));

        self::assertSame([], $this->requests);
        self::assertSame(0, WebhookDeliveryQuery::create()->count($this->getPropelConnection()));
    }

    public function testTwoSubscriptionsGetTheirOwnDeliveryIdentifierAndSignature(): void
    {
        $this->subscribe(['order.created'], url: 'https://one.example.com/in');
        $this->subscribe(['order.created'], url: 'https://two.example.com/in');

        $this->listener()->onOrderPlaced(new OrderEvent($this->order()));

        self::assertCount(2, $this->requests);
        self::assertNotSame($this->requests[0]['headers'][WebhookSigner::HEADER_ID], $this->requests[1]['headers'][WebhookSigner::HEADER_ID]);
    }

    public function testASubscriptionThatAsksForIdentifiersOnlyGetsTheIdentifier(): void
    {
        $this->subscribe(['order.created'], payloadMode: WebhookStatus::PAYLOAD_IDENTIFIERS);
        $order = $this->order();

        $this->listener()->onOrderPlaced(new OrderEvent($order));

        $body = json_decode($this->requests[0]['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['id' => $order->getId()], $body['data']);
    }

    public function testAFailingReceiverLeavesAFailedDeliveryWithoutTheSecretInTheLog(): void
    {
        $subscription = $this->subscribe(['order.created']);
        $this->answerCode = 500;
        $this->answerBody = 'bad key '.self::SECRET;

        $this->listener()->onOrderPlaced(new OrderEvent($this->order()));

        $delivery = WebhookDeliveryQuery::create()->findOneByWebhookSubscription($subscription, $this->getPropelConnection());
        self::assertNotNull($delivery);
        self::assertSame(WebhookStatus::DELIVERY_FAILED, $delivery->getStatus());
        self::assertSame(500, $delivery->getResponseCode());
        self::assertSame(1, $delivery->getAttempts());
        self::assertStringNotContainsString(self::SECRET, (string) $delivery->getResponseBody());
        self::assertStringContainsString('[redacted]', (string) $delivery->getResponseBody());
    }

    public function testASuccessfulDeliveryIsRecordedWithItsAnswerAndDuration(): void
    {
        $subscription = $this->subscribe(['order.created']);

        $this->listener()->onOrderPlaced(new OrderEvent($this->order()));

        $delivery = WebhookDeliveryQuery::create()->findOneByWebhookSubscription($subscription, $this->getPropelConnection());
        self::assertNotNull($delivery);
        self::assertSame(WebhookStatus::DELIVERY_SUCCEEDED, $delivery->getStatus());
        self::assertSame(200, $delivery->getResponseCode());
        self::assertNotNull($delivery->getDurationMs());
        self::assertNotNull($delivery->getDeliveredAt());
    }

    public function testAnAddressOnTheShopNetworkIsNeverCalled(): void
    {
        $subscription = $this->subscribe(['order.created'], url: 'https://10.0.0.5/in');

        $this->listener()->onOrderPlaced(new OrderEvent($this->order()));

        self::assertSame([], $this->requests);
        $delivery = WebhookDeliveryQuery::create()->findOneByWebhookSubscription($subscription, $this->getPropelConnection());
        self::assertSame(WebhookStatus::DELIVERY_FAILED, $delivery?->getStatus());
        self::assertNull($delivery->getResponseCode());
    }

    public function testPublishingNeverBreaksWhatProducedTheEvent(): void
    {
        $this->subscribe(['order.created']);
        $publisher = static::getContainer()->get(WebhookPublisher::class);

        $publisher->publish('order.created', new \Thelia\Model\Address());

        $this->addToAssertionCount(1);
    }

    public function testAQueueThatRefusesOneDeliveryDoesNotStopTheOthersNorBreakTheEvent(): void
    {
        $first = $this->subscribe(['order.created'], url: 'https://one.example.com/in');
        $this->subscribe(['order.created'], url: 'https://two.example.com/in');
        $dispatched = [];
        $bus = new class($dispatched) implements MessageBusInterface {
            private int $calls = 0;

            public function __construct(private array &$dispatched)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                if (1 === ++$this->calls) {
                    throw new \RuntimeException('transport down');
                }

                return $this->dispatched[] = Envelope::wrap($message, $stamps);
            }
        };
        $container = static::getContainer();
        $publisher = new WebhookPublisher($container->get(WebhookEventCatalog::class), $container->get(WebhookPayloadBuilder::class), $bus);

        $publisher->publish('order.created', $this->order());

        self::assertCount(1, $dispatched);
        self::assertSame(1, WebhookDeliveryQuery::create()->filterByStatus(WebhookStatus::DELIVERY_FAILED)->count($this->getPropelConnection()));
        self::assertSame(1, WebhookDeliveryQuery::create()->filterByStatus(WebhookStatus::DELIVERY_PENDING)->count($this->getPropelConnection()));
        self::assertNotNull($first);
    }

    public function testASubscriptionWithUnreadableEventsDoesNotStopTheOthers(): void
    {
        $broken = $this->subscribe(['order.created']);
        $broken->setEvents('{not json')->save($this->getPropelConnection());
        $this->subscribe(['order.created'], url: 'https://ok.example.com/in');

        $this->listener()->onOrderPlaced(new OrderEvent($this->order()));

        self::assertCount(1, $this->requests);
        self::assertSame('https://ok.example.com/in', $this->requests[0]['url']);
    }

    public function testAListenerThatFailsNeverReachesTheCodeThatRaisedTheEvent(): void
    {
        $event = new class($this->order()) extends OrderEvent {
            public function getPreviousStatusId(): ?int
            {
                throw new \RuntimeException('database gone');
            }
        };

        $this->listener()->onOrderStatusUpdated($event);

        $this->addToAssertionCount(1);
    }

    public function testAPaidTransitionPublishesTheStatusChangeAndThePayment(): void
    {
        $this->subscribe(['order.status_changed', 'order.paid']);
        $order = $this->order();
        $paid = OrderStatusQuery::create()->findOneByCode('paid', $this->getPropelConnection());
        self::assertNotNull($paid);

        $this->dispatcher()->dispatch((new OrderEvent($order))->setStatus($paid->getId()), TheliaEvents::ORDER_UPDATE_STATUS);

        $types = array_map(static fn (array $request): string => json_decode($request['body'], true, 512, \JSON_THROW_ON_ERROR)['type'], $this->requests);
        sort($types);
        self::assertSame(['order.paid', 'order.status_changed'], $types);
    }

    public function testAnOrderPlacedFromTheBackOfficePublishesOrderCreated(): void
    {
        $this->subscribe(['order.created']);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer);
        $event = new OrderManualEvent(new Order(), $this->factory->currency(), $this->factory->lang(), $this->factory->cart($customer), $customer);
        $event->setPlacedOrder($order);

        $this->listener()->onOrderCreatedManually($event);

        self::assertCount(1, $this->requests);
        $body = json_decode($this->requests[0]['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('order.created', $body['type']);
        self::assertSame($order->getId(), $body['data']['id']);
    }

    public function testAManualOrderThatWasNotPlacedPublishesNothing(): void
    {
        $this->subscribe(['order.created']);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $event = new OrderManualEvent(new Order(), $this->factory->currency(), $this->factory->lang(), $this->factory->cart($customer), $customer);

        $this->listener()->onOrderCreatedManually($event);

        self::assertSame([], $this->requests);
    }

    public function testTheCoreEventsAreWiredToTheListener(): void
    {
        foreach ([TheliaEvents::ORDER_BEFORE_PAYMENT, TheliaEvents::ORDER_CREATE_MANUAL, TheliaEvents::ORDER_UPDATE_STATUS, TheliaEvents::CUSTOMER_CREATEACCOUNT, TheliaEvents::PRODUCT_UPDATE] as $eventName) {
            $listeners = array_filter(
                $this->dispatcher()->getListeners($eventName),
                static fn ($listener): bool => \is_array($listener) && $listener[0] instanceof PublishWebhookEventsListener,
            );
            self::assertNotEmpty($listeners, $eventName.' reaches the webhook listener.');
        }
    }

    public function testAProductUpdateThatChangesNothingPublishesNothing(): void
    {
        $product = $this->product();
        $this->dispatcher()->dispatch($this->updateEvent($product, 'Same title'), TheliaEvents::PRODUCT_UPDATE);
        $this->subscribe(['product.updated']);

        $this->dispatcher()->dispatch($this->updateEvent($product, 'Same title'), TheliaEvents::PRODUCT_UPDATE);

        self::assertSame([], $this->requests);
    }

    public function testAProductUpdateThatChangesATitleOrTheVisibilityIsPublished(): void
    {
        $product = $this->product();
        $this->dispatcher()->dispatch($this->updateEvent($product, 'Before'), TheliaEvents::PRODUCT_UPDATE);
        $this->subscribe(['product.updated']);

        $this->dispatcher()->dispatch($this->updateEvent($product, 'After'), TheliaEvents::PRODUCT_UPDATE);
        $this->dispatcher()->dispatch($this->updateEvent($product, 'After', visible: false), TheliaEvents::PRODUCT_UPDATE);

        self::assertCount(2, $this->requests);
        $body = json_decode($this->requests[0]['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('product.updated', $body['type']);
        self::assertSame($product->getId(), $body['data']['id']);
    }

    private function product(): \Thelia\Model\Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 5]);
    }

    private function updateEvent(\Thelia\Model\Product $product, string $title, bool $visible = true): ProductUpdateEvent
    {
        return (new ProductUpdateEvent($product->getId()))
            ->setRef($product->getRef())
            ->setLocale('en_US')
            ->setTitle($title)
            ->setVisible($visible)
            ->setVirtual(false)
            ->setDefaultCategory($product->getDefaultCategoryId());
    }

    /**
     * @param list<string> $events
     */
    private function subscribe(array $events, string $url = 'https://hooks.example.com/in', string $status = WebhookStatus::SUBSCRIPTION_ACTIVE, string $payloadMode = WebhookStatus::PAYLOAD_FULL): WebhookSubscription
    {
        $subscription = (new WebhookSubscription())
            ->setTitle('Integration')
            ->setUrl($url)
            ->setSecret(self::SECRET)
            ->setEvents(json_encode($events, \JSON_THROW_ON_ERROR))
            ->setPayloadMode($payloadMode)
            ->setStatus($status);
        $subscription->save($this->getPropelConnection());

        return $subscription;
    }

    private function order(): Order
    {
        return $this->factory->order($this->factory->customer($this->factory->customerTitle()));
    }

    private function listener(): PublishWebhookEventsListener
    {
        return new PublishWebhookEventsListener(static::getContainer()->get(WebhookPublisher::class), new ProductChangeFingerprint());
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}

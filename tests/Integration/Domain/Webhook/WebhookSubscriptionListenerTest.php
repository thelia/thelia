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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Webhook\Event\WebhookDeliveryEvent;
use Thelia\Domain\Webhook\Event\WebhookSubscriptionEvent;
use Thelia\Domain\Webhook\EventListener\WebhookSubscriptionListener;
use Thelia\Domain\Webhook\Exception\InvalidWebhookUrlException;
use Thelia\Domain\Webhook\Message\DeliverWebhook;
use Thelia\Domain\Webhook\WebhookDeliverer;
use Thelia\Domain\Webhook\WebhookEventCatalog;
use Thelia\Domain\Webhook\WebhookPayloadBuilder;
use Thelia\Domain\Webhook\WebhookSigner;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\LangQuery;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookDeliveryQuery;
use Thelia\Model\WebhookSubscription;
use Thelia\Model\WebhookSubscriptionQuery;
use Thelia\Test\IntegrationTestCase;

final class WebhookSubscriptionListenerTest extends IntegrationTestCase
{
    private const URL = 'https://hooks.example.com/in';

    /** @var list<Envelope> */
    private array $dispatched = [];

    /** @var list<array{url: string, headers: array<string, string>, body: string}> */
    private array $requests = [];
    private int $answerCode = 200;
    private bool $busRefuses = false;

    protected function setUp(): void
    {
        parent::setUp();
        WebhookSubscriptionQuery::create()->deleteAll($this->getPropelConnection());
        $this->dispatched = [];
        $this->requests = [];
        $this->answerCode = 200;
        $this->busRefuses = false;
    }

    public function testCreatingASubscriptionStoresItAndHandsTheGeneratedSecretToTheEvent(): void
    {
        $event = $this->creation();

        $this->listener()->create($event);

        $secret = $event->getGeneratedSecret();
        self::assertNotNull($secret);
        self::assertSame(64, \strlen($secret));
        $stored = WebhookSubscriptionQuery::create()->findPk($event->getSubscription()?->getId());
        self::assertNotNull($stored);
        self::assertSame($secret, $stored->getSecret());
        self::assertSame(self::URL, $stored->getUrl());
        self::assertSame(['order.created', 'order.paid'], json_decode($stored->getEvents(), true));
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $stored->getStatus());
    }

    public function testAnAddressOnAPrivateNetworkIsRefusedAndNothingIsStored(): void
    {
        $event = $this->creation()->setUrl('https://10.0.0.5/in');

        try {
            $this->listener()->create($event);
            self::fail('The private address was accepted.');
        } catch (InvalidWebhookUrlException) {
        }

        self::assertSame(0, WebhookSubscriptionQuery::create()->count());
    }

    public function testAnUnknownEventAnEmptyListAndAnUnknownStateAreRefused(): void
    {
        foreach ([
            $this->creation()->setEvents(['order.exploded']),
            $this->creation()->setEvents([]),
            $this->creation()->setStatus(WebhookStatus::SUBSCRIPTION_ERROR),
            $this->creation()->setPayloadMode('everything'),
            $this->creation()->setTitle('  '),
        ] as $event) {
            try {
                $this->listener()->create($event);
                self::fail('An invalid subscription was stored.');
            } catch (\InvalidArgumentException) {
            }
        }

        self::assertSame(0, WebhookSubscriptionQuery::create()->count());
    }

    public function testUpdatingKeepsTheSecretAndEnablingAnErrorSubscriptionClearsItsFailure(): void
    {
        $subscription = $this->subscription(WebhookStatus::SUBSCRIPTION_ERROR);
        $secret = $subscription->getSecret();

        $this->listener()->update(
            $this->creation()->setSubscription($subscription)->setTitle('Renamed')->setEvents(['customer.created'])->setStatus(WebhookStatus::SUBSCRIPTION_ACTIVE),
        );

        $stored = $this->reread($subscription);
        self::assertSame('Renamed', $stored->getTitle());
        self::assertSame($secret, $stored->getSecret());
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $stored->getStatus());
        self::assertNull($stored->getFailureReason());
        self::assertNull($stored->getFailedAt());
    }

    public function testSavingAnErrorSubscriptionWithoutChangingItsStateKeepsTheError(): void
    {
        $subscription = $this->subscription(WebhookStatus::SUBSCRIPTION_ERROR);

        $this->listener()->update($this->creation()->setSubscription($subscription)->setStatus(WebhookStatus::SUBSCRIPTION_ERROR));

        $stored = $this->reread($subscription);
        self::assertSame(WebhookStatus::SUBSCRIPTION_ERROR, $stored->getStatus());
        self::assertNotNull($stored->getFailureReason());
    }

    public function testAnUpdateThatSetsNeitherTheModeNorTheStateLeavesThemAlone(): void
    {
        $subscription = $this->subscription(WebhookStatus::SUBSCRIPTION_INACTIVE);
        $subscription->setPayloadMode(WebhookStatus::PAYLOAD_IDENTIFIERS)->save();

        $this->listener()->update(
            (new WebhookSubscriptionEvent())->setSubscription($subscription)->setTitle('  Renamed  ')->setUrl(self::URL)->setEvents(['order.created']),
        );

        $stored = $this->reread($subscription);
        self::assertSame('Renamed', $stored->getTitle());
        self::assertSame(WebhookStatus::PAYLOAD_IDENTIFIERS, $stored->getPayloadMode());
        self::assertSame(WebhookStatus::SUBSCRIPTION_INACTIVE, $stored->getStatus());
    }

    public function testAnEventNameThatIsNotAStringIsRefusedWithAnExplicitError(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->listener()->create($this->creation()->setEvents([42]));
    }

    public function testRotatingTheSecretChangesItAndHandsTheNewOneToTheEvent(): void
    {
        $subscription = $this->subscription();
        $before = $subscription->getSecret();
        $event = (new WebhookSubscriptionEvent())->setSubscription($subscription);

        $this->listener()->rotateSecret($event);

        $after = $this->reread($subscription)->getSecret();
        self::assertNotSame($before, $after);
        self::assertSame($after, $event->getGeneratedSecret());
    }

    public function testDeletingASubscriptionDeletesItsDeliveries(): void
    {
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);

        $this->listener()->delete((new WebhookSubscriptionEvent())->setSubscription($subscription));

        self::assertNull(WebhookSubscriptionQuery::create()->findPk($subscription->getId()));
        self::assertNull(WebhookDeliveryQuery::create()->findPk($delivery->getId()));
    }

    public function testTheTestRequestIsSignedLoggedAndSettledOnTheAnswer(): void
    {
        $subscription = $this->subscription();
        $event = (new WebhookSubscriptionEvent())->setSubscription($subscription);

        $this->listener()->test($event);

        $delivery = $event->getTestDelivery();
        self::assertNotNull($delivery);
        self::assertSame(WebhookStatus::DELIVERY_SUCCEEDED, $this->rereadDelivery($delivery)->getStatus());
        self::assertSame('webhook.test', $delivery->getEvent());
        self::assertCount(1, $this->requests);
        $request = $this->requests[0];
        self::assertSame(self::URL, $request['url']);
        self::assertSame($delivery->getPayload(), $request['body']);
        self::assertSame('webhook.test', json_decode($request['body'], true)['type']);
        self::assertTrue((new WebhookSigner())->verify(
            $request['body'],
            $subscription->getSecret(),
            (int) $request['headers']['x-thelia-webhook-timestamp'],
            $request['headers']['x-thelia-webhook-signature'],
            time(),
        ));
        self::assertSame([], $this->dispatched);
    }

    public function testAFailedTestRequestIsLoggedAsFailedNotLeftPending(): void
    {
        $this->answerCode = 500;
        $event = (new WebhookSubscriptionEvent())->setSubscription($this->subscription());

        $this->listener()->test($event);

        $delivery = $this->rereadDelivery($event->getTestDelivery());
        self::assertSame(WebhookStatus::DELIVERY_FAILED, $delivery->getStatus());
        self::assertSame(500, $delivery->getResponseCode());
    }

    public function testReplayingAFailedDeliveryRestartsItAndQueuesIt(): void
    {
        $delivery = $this->delivery($this->subscription(), WebhookStatus::DELIVERY_FAILED, attempts: 5);

        $this->listener()->replay((new WebhookDeliveryEvent())->setDelivery($delivery));

        $stored = $this->rereadDelivery($delivery);
        self::assertSame(WebhookStatus::DELIVERY_PENDING, $stored->getStatus());
        self::assertSame(5, $stored->getAttempts(), 'The attempts already made stay counted.');
        self::assertNotNull($stored->getReplayedAt());
        self::assertCount(1, $this->dispatched);
        self::assertEquals(new DeliverWebhook($delivery->getId()), $this->dispatched[0]->getMessage());
    }

    public function testAReplayThatLostTheClaimToAnotherQueuesNothing(): void
    {
        $delivery = $this->delivery($this->subscription(), WebhookStatus::DELIVERY_FAILED, attempts: 5);
        $staleCopy = WebhookDeliveryQuery::create()->findPk($delivery->getId());
        self::assertNotNull($staleCopy);

        $this->listener()->replay((new WebhookDeliveryEvent())->setDelivery($delivery));
        self::assertCount(1, $this->dispatched);

        try {
            $this->listener()->replay((new WebhookDeliveryEvent())->setDelivery($staleCopy));
            self::fail('A delivery already claimed by a replay was replayed twice.');
        } catch (\LogicException) {
        }

        self::assertCount(1, $this->dispatched, 'The second replay queued nothing.');
        self::assertSame(WebhookStatus::DELIVERY_PENDING, $this->rereadDelivery($delivery)->getStatus());
    }

    public function testTheMessagesOfARefusedSubscriptionAreTranslated(): void
    {
        $session = $this->getService('request_stack')->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $french = LangQuery::create()->findOneByLocale('fr_FR');
        self::assertNotNull($french, 'The test shop has the French language.');
        $previous = $session->getLang(false);
        $session->setLang($french);

        try {
            try {
                $this->listener()->create($this->creation()->setUrl('http://hooks.example.com/in'));
                self::fail('A plain http address was accepted.');
            } catch (InvalidWebhookUrlException $exception) {
                self::assertSame("L'adresse doit utiliser https.", $exception->getMessage());
                self::assertInstanceOf(InvalidWebhookUrlException::class, $exception->getPrevious());
            }

            try {
                $this->listener()->create($this->creation()->setEvents([]));
                self::fail('A subscription without an event was accepted.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('Choisissez au moins un événement.', $exception->getMessage());
            }
        } finally {
            null === $previous ? $session->remove('thelia.current.lang') : $session->setLang($previous);
        }
    }

    public function testOnlyAFailedDeliveryCanBeReplayed(): void
    {
        foreach ([WebhookStatus::DELIVERY_SUCCEEDED, WebhookStatus::DELIVERY_PENDING] as $status) {
            $delivery = $this->delivery($this->subscription(), $status);

            try {
                $this->listener()->replay((new WebhookDeliveryEvent())->setDelivery($delivery));
                self::fail('A '.$status.' delivery was replayed.');
            } catch (\LogicException) {
            }

            self::assertSame($status, $this->rereadDelivery($delivery)->getStatus());
        }

        self::assertSame([], $this->dispatched);
    }

    public function testADeliveryOfASubscriptionThatIsNotActiveIsNotReplayed(): void
    {
        foreach ([WebhookStatus::SUBSCRIPTION_ERROR, WebhookStatus::SUBSCRIPTION_INACTIVE] as $status) {
            $delivery = $this->delivery($this->subscription($status), WebhookStatus::DELIVERY_FAILED);

            try {
                $this->listener()->replay((new WebhookDeliveryEvent())->setDelivery($delivery));
                self::fail('A delivery of a '.$status.' subscription was replayed.');
            } catch (\LogicException) {
            }

            self::assertSame(WebhookStatus::DELIVERY_FAILED, $this->rereadDelivery($delivery)->getStatus());
        }

        self::assertSame([], $this->dispatched);
    }

    public function testAReplayTheQueueRefusesLeavesTheDeliveryFailed(): void
    {
        $this->busRefuses = true;
        $delivery = $this->delivery($this->subscription(), WebhookStatus::DELIVERY_FAILED);

        try {
            $this->listener()->replay((new WebhookDeliveryEvent())->setDelivery($delivery));
            self::fail('The refusal of the queue was swallowed.');
        } catch (\RuntimeException) {
        }

        self::assertSame(WebhookStatus::DELIVERY_FAILED, $this->rereadDelivery($delivery)->getStatus());
    }

    private function listener(): WebhookSubscriptionListener
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $headers = [];
            foreach ($options['headers'] as $line) {
                [$name, $value] = explode(': ', $line, 2);
                $headers[strtolower($name)] = $value;
            }
            $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => (string) $options['body']];

            return new MockResponse('answer', ['http_code' => $this->answerCode]);
        });

        $bus = new class($this) implements MessageBusInterface {
            public function __construct(private readonly WebhookSubscriptionListenerTest $test)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->test->record($message, $stamps);
            }
        };

        return new WebhookSubscriptionListener(
            new WebhookUrlValidator(),
            new WebhookEventCatalog(),
            $this->getService(WebhookPayloadBuilder::class),
            new WebhookDeliverer($client, new WebhookSigner(), new WebhookUrlValidator()),
            $bus,
        );
    }

    /**
     * @param list<StampInterface> $stamps
     */
    public function record(object $message, array $stamps): Envelope
    {
        if ($this->busRefuses) {
            throw new \RuntimeException('The queue is down.');
        }

        return $this->dispatched[] = new Envelope($message, $stamps);
    }

    private function creation(): WebhookSubscriptionEvent
    {
        return (new WebhookSubscriptionEvent())
            ->setTitle('ERP')
            ->setUrl(self::URL)
            ->setEvents(['order.created', 'order.paid'])
            ->setPayloadMode(WebhookStatus::PAYLOAD_FULL)
            ->setStatus(WebhookStatus::SUBSCRIPTION_ACTIVE);
    }

    private function subscription(string $status = WebhookStatus::SUBSCRIPTION_ACTIVE): WebhookSubscription
    {
        $subscription = (new WebhookSubscription())
            ->setTitle('Existing')
            ->setUrl(self::URL)
            ->setSecret(bin2hex(random_bytes(32)))
            ->setEvents('["order.created"]')
            ->setPayloadMode(WebhookStatus::PAYLOAD_FULL)
            ->setStatus($status);

        if (WebhookStatus::SUBSCRIPTION_ERROR === $status) {
            $subscription->setFailureReason('Delivery x failed after 5 attempts.')->setFailedAt(new \DateTimeImmutable());
        }

        $subscription->save();

        return $subscription;
    }

    private function delivery(WebhookSubscription $subscription, string $status, int $attempts = 1): WebhookDelivery
    {
        $delivery = (new WebhookDelivery())
            ->setWebhookSubscription($subscription)
            ->setEvent('order.created')
            ->setDeliveryUuid(bin2hex(random_bytes(18)))
            ->setPayload('{"id":"x"}')
            ->setStatus($status)
            ->setAttempts($attempts);
        $delivery->save();

        return $delivery;
    }

    private function reread(WebhookSubscription $subscription): WebhookSubscription
    {
        return WebhookSubscriptionQuery::create()->findPk($subscription->getId()) ?? throw new \LogicException('The subscription is gone.');
    }

    private function rereadDelivery(?WebhookDelivery $delivery): WebhookDelivery
    {
        return WebhookDeliveryQuery::create()->findPk($delivery?->getId()) ?? throw new \LogicException('The delivery is gone.');
    }
}

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

use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Thelia\Domain\Webhook\Message\DeliverWebhook;
use Thelia\Domain\Webhook\Message\DeliverWebhookHandler;
use Thelia\Domain\Webhook\WebhookDeliverer;
use Thelia\Domain\Webhook\WebhookRetryPolicy;
use Thelia\Domain\Webhook\WebhookSigner;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookDeliveryQuery;
use Thelia\Model\WebhookSubscription;
use Thelia\Model\WebhookSubscriptionQuery;
use Thelia\Test\IntegrationTestCase;

final class DeliverWebhookHandlerTest extends IntegrationTestCase
{
    private const SECRET = 'whsec_handler';

    /** @var list<Envelope> */
    private array $dispatched = [];
    private int $requests = 0;
    private int $answerCode = 500;
    private string $answerBody = 'answer';
    private ?\Throwable $throwOnRequest = null;
    private int $chunksServed = 0;
    private bool $endlessClient = false;

    protected function setUp(): void
    {
        parent::setUp();
        WebhookSubscriptionQuery::create()->deleteAll($this->getPropelConnection());
        $this->dispatched = [];
        $this->requests = 0;
        $this->answerCode = 500;
    }

    public function testAFailureOnAQueueIsRetriedLaterAndTheDeliveryStaysPending(): void
    {
        [$subscription, $delivery] = $this->delivery();

        $this->handler(inline: false)(new DeliverWebhook($delivery->getId()));

        $reread = $this->reread($delivery);
        self::assertSame(WebhookStatus::DELIVERY_PENDING, $reread->getStatus());
        self::assertSame(1, $reread->getAttempts());
        self::assertCount(1, $this->dispatched);
        self::assertEquals(new DeliverWebhook($delivery->getId()), $this->dispatched[0]->getMessage());
        self::assertSame(60000, $this->dispatched[0]->last(DelayStamp::class)?->getDelay());
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $this->subscription($subscription)->getStatus());
    }

    public function testTheDelayGrowsWithTheAttempt(): void
    {
        [, $delivery] = $this->delivery(attempts: 2);

        $this->handler(inline: false)(new DeliverWebhook($delivery->getId()));

        self::assertSame(1800000, $this->dispatched[0]->last(DelayStamp::class)?->getDelay());
    }

    public function testTheLastFailedAttemptFailsTheDeliveryAndPutsTheSubscriptionInError(): void
    {
        [$subscription, $delivery] = $this->delivery(attempts: 4);

        $this->handler(inline: false)(new DeliverWebhook($delivery->getId()));

        $reread = $this->reread($delivery);
        self::assertSame(WebhookStatus::DELIVERY_FAILED, $reread->getStatus());
        self::assertSame(5, $reread->getAttempts());
        self::assertSame([], $this->dispatched);
        $subscription = $this->subscription($subscription);
        self::assertSame(WebhookStatus::SUBSCRIPTION_ERROR, $subscription->getStatus());
        self::assertNotNull($subscription->getFailedAt());
        self::assertStringNotContainsString(self::SECRET, (string) $subscription->getFailureReason());
    }

    public function testTheLastFailedAttemptOfAReplayedDeliveryLeavesTheSubscriptionActive(): void
    {
        [$subscription, $delivery] = $this->delivery(attempts: 4);
        $delivery->setReplayedAt(new \DateTimeImmutable())->save($this->getPropelConnection());

        $this->handler(inline: false)(new DeliverWebhook($delivery->getId()));

        self::assertSame(WebhookStatus::DELIVERY_FAILED, $this->reread($delivery)->getStatus());
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $this->subscription($subscription)->getStatus());
    }

    public function testWithoutAQueueAFailureIsNotRetriedAndDoesNotSilenceTheSubscription(): void
    {
        [$subscription, $delivery] = $this->delivery();

        $this->handler(inline: true)(new DeliverWebhook($delivery->getId()));

        self::assertSame(WebhookStatus::DELIVERY_FAILED, $this->reread($delivery)->getStatus());
        self::assertSame([], $this->dispatched);
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $this->subscription($subscription)->getStatus());
    }

    public function testASuccessfulRetrySettlesTheDelivery(): void
    {
        [, $delivery] = $this->delivery(attempts: 2);
        $this->answerCode = 204;

        $this->handler(inline: false)(new DeliverWebhook($delivery->getId()));

        $reread = $this->reread($delivery);
        self::assertSame(WebhookStatus::DELIVERY_SUCCEEDED, $reread->getStatus());
        self::assertSame(3, $reread->getAttempts());
        self::assertSame([], $this->dispatched);
    }

    public function testAMessageDeliveredTwiceSendsOnce(): void
    {
        [, $delivery] = $this->delivery();
        $this->answerCode = 200;
        $handler = $this->handler(inline: false);

        $handler(new DeliverWebhook($delivery->getId()));
        $handler(new DeliverWebhook($delivery->getId()));

        self::assertSame(1, $this->requests);
    }

    public function testALateMessageForAnAlreadyFailedDeliveryIsNotSent(): void
    {
        [, $delivery] = $this->delivery(attempts: 5);
        $delivery->setStatus(WebhookStatus::DELIVERY_FAILED)->save($this->getPropelConnection());

        $this->handler(inline: false)(new DeliverWebhook($delivery->getId()));

        self::assertSame(0, $this->requests);
        self::assertSame(5, $this->reread($delivery)->getAttempts());
    }

    public function testAnUnexpectedErrorWhileSendingIsRecordedWithoutItsMessage(): void
    {
        [$subscription, $delivery] = $this->delivery();
        $subscription->setUrl('https://hooks.example.com/in')->save($this->getPropelConnection());
        $this->throwOnRequest = new \ValueError('secret '.self::SECRET);

        $this->handler(inline: true)(new DeliverWebhook($delivery->getId()));

        $reread = $this->reread($delivery);
        self::assertSame(1, $reread->getAttempts());
        self::assertSame(\ValueError::class, $reread->getResponseBody());
    }

    public function testAnEndlessAnswerIsCutShort(): void
    {
        [, $delivery] = $this->delivery();
        $this->answerCode = 200;
        $this->endlessClient = true;

        $this->handler(inline: true)(new DeliverWebhook($delivery->getId()));

        $body = (string) $this->reread($delivery)->getResponseBody();
        self::assertSame(str_repeat('a', 1000), $body);
        self::assertLessThan(10, $this->chunksServed, 'The rest of the answer is not read.');
    }

    public function testASubscriptionTurnedOffMeanwhileIsNotCalled(): void
    {
        [$subscription, $delivery] = $this->delivery();
        $subscription->setStatus(WebhookStatus::SUBSCRIPTION_INACTIVE)->save($this->getPropelConnection());
        $this->answerCode = 200;

        $this->handler(inline: false)(new DeliverWebhook($delivery->getId()));

        self::assertSame(0, $this->requests);
        self::assertSame(WebhookStatus::DELIVERY_FAILED, $this->reread($delivery)->getStatus());
    }

    public function testADeliveryNotYetVisibleOnAQueueIsLookedForAgainLater(): void
    {
        $this->handler(inline: false)(new DeliverWebhook(987654321));

        self::assertCount(1, $this->dispatched);
        self::assertEquals(new DeliverWebhook(987654321, 1), $this->dispatched[0]->getMessage());
        $delay = $this->dispatched[0]->last(DelayStamp::class);
        self::assertGreaterThan(0, $delay?->getDelay());
        self::assertSame(0, $this->requests);
    }

    public function testTheLookupsStopAfterAFewTries(): void
    {
        $this->handler(inline: false)(new DeliverWebhook(987654321, 5));

        self::assertSame([], $this->dispatched);
    }

    public function testWithoutAQueueAMissingDeliveryIsNotLookedForAgain(): void
    {
        $this->handler(inline: true)(new DeliverWebhook(987654321));

        self::assertSame([], $this->dispatched);
    }

    public function testAnUnknownDeliveryIsIgnored(): void
    {
        $this->handler(inline: false)(new DeliverWebhook(987654321));

        self::assertSame(0, $this->requests);
    }

    /**
     * @return array{0: WebhookSubscription, 1: WebhookDelivery}
     */
    private function delivery(int $attempts = 0): array
    {
        $subscription = (new WebhookSubscription())
            ->setTitle('Handler')
            ->setUrl('https://hooks.example.com/in')
            ->setSecret(self::SECRET)
            ->setEvents('["order.created"]')
            ->setStatus(WebhookStatus::SUBSCRIPTION_ACTIVE);
        $subscription->save($this->getPropelConnection());

        $delivery = (new WebhookDelivery())
            ->setWebhookSubscription($subscription)
            ->setEvent('order.created')
            ->setDeliveryUuid(bin2hex(random_bytes(16)))
            ->setPayload('{"type":"order.created"}')
            ->setAttempts($attempts)
            ->setStatus(WebhookStatus::DELIVERY_PENDING);
        $delivery->save($this->getPropelConnection());

        return [$subscription, $delivery];
    }

    private function handler(bool $inline): DeliverWebhookHandler
    {
        $client = $this->endlessClient ? $this->endlessAnswerClient() : new MockHttpClient(function (): MockResponse {
            ++$this->requests;

            if (null !== $this->throwOnRequest) {
                throw $this->throwOnRequest;
            }

            return new MockResponse($this->answerBody, ['http_code' => $this->answerCode]);
        });

        $bus = new class($this->dispatched) implements MessageBusInterface {
            /** @param list<Envelope> $dispatched */
            public function __construct(private array &$dispatched)
            {
            }

            /** @param array<StampInterface> $stamps */
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->dispatched[] = Envelope::wrap($message, $stamps);
            }
        };

        return new DeliverWebhookHandler(
            new WebhookDeliverer($client, new WebhookSigner(), new WebhookUrlValidator()),
            new WebhookRetryPolicy(),
            $bus,
            $inline ? 'sync://' : 'doctrine://default',
        );
    }

    private function reread(WebhookDelivery $delivery): WebhookDelivery
    {
        return WebhookDeliveryQuery::create()->findPk($delivery->getId(), $this->getPropelConnection());
    }

    private function subscription(WebhookSubscription $subscription): WebhookSubscription
    {
        return WebhookSubscriptionQuery::create()->findPk($subscription->getId(), $this->getPropelConnection());
    }

    private function endlessAnswerClient(): HttpClientInterface
    {
        $served = &$this->chunksServed;

        return new class($served) implements HttpClientInterface {
            public function __construct(private int &$served)
            {
            }

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return new class implements ResponseInterface {
                    public function getStatusCode(): int
                    {
                        return 200;
                    }

                    public function getHeaders(bool $throw = true): array
                    {
                        return [];
                    }

                    public function getContent(bool $throw = true): string
                    {
                        throw new \LogicException('The whole answer must not be read.');
                    }

                    public function toArray(bool $throw = true): array
                    {
                        return [];
                    }

                    public function cancel(): void
                    {
                    }

                    public function getInfo(?string $type = null): mixed
                    {
                        return null;
                    }
                };
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                $response = is_iterable($responses) ? $responses[0] : $responses;

                return new ResponseStream((function () use ($response): \Generator {
                    for ($chunk = 0; $chunk < 1000; ++$chunk) {
                        ++$this->served;

                        yield $response => new DataChunk($chunk, str_repeat('a', 600));
                    }
                })());
            }

            public function withOptions(array $options): static
            {
                return $this;
            }
        };
    }
}

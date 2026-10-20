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

namespace Thelia\Domain\Webhook\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Webhook\Event\WebhookDeliveryEvent;
use Thelia\Domain\Webhook\Event\WebhookSubscriptionEvent;
use Thelia\Domain\Webhook\Exception\InvalidWebhookUrlException;
use Thelia\Domain\Webhook\Message\DeliverWebhook;
use Thelia\Domain\Webhook\WebhookDeliverer;
use Thelia\Domain\Webhook\WebhookEventCatalog;
use Thelia\Domain\Webhook\WebhookPayloadBuilder;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookDeliveryQuery;
use Thelia\Model\WebhookSubscription;

final readonly class WebhookSubscriptionListener
{
    public const string TEST_EVENT = 'webhook.test';

    public function __construct(
        private WebhookUrlValidator $urlValidator,
        private WebhookEventCatalog $catalog,
        private WebhookPayloadBuilder $payloadBuilder,
        private WebhookDeliverer $deliverer,
        private MessageBusInterface $bus,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::WEBHOOK_SUBSCRIPTION_CREATE)]
    public function create(WebhookSubscriptionEvent $event): void
    {
        $this->assertValid($event, null);

        $secret = $this->newSecret();
        $subscription = (new WebhookSubscription())
            ->setTitle(trim($event->getTitle()))
            ->setUrl($event->getUrl())
            ->setEvents($this->encodeEvents($event->getEvents()))
            ->setPayloadMode($event->getPayloadMode() ?? WebhookStatus::PAYLOAD_FULL)
            ->setStatus($event->getStatus() ?? WebhookStatus::SUBSCRIPTION_ACTIVE)
            ->setSecret($secret);
        $subscription->save();

        $event->setSubscription($subscription)->setGeneratedSecret($secret);
    }

    #[AsEventListener(event: TheliaEvents::WEBHOOK_SUBSCRIPTION_UPDATE)]
    public function update(WebhookSubscriptionEvent $event): void
    {
        $subscription = $this->subscriptionOf($event);
        $this->assertValid($event, $subscription);

        $subscription
            ->setTitle(trim($event->getTitle()))
            ->setUrl($event->getUrl())
            ->setEvents($this->encodeEvents($event->getEvents()));

        if (null !== $event->getPayloadMode()) {
            $subscription->setPayloadMode($event->getPayloadMode());
        }

        if (null !== $event->getStatus() && $event->getStatus() !== $subscription->getStatus()) {
            $subscription->setStatus($event->getStatus());

            if (WebhookStatus::SUBSCRIPTION_ACTIVE === $event->getStatus()) {
                $subscription->setFailureReason(null)->setFailedAt(null);
            }
        }

        $subscription->save();
    }

    #[AsEventListener(event: TheliaEvents::WEBHOOK_SUBSCRIPTION_DELETE)]
    public function delete(WebhookSubscriptionEvent $event): void
    {
        $this->subscriptionOf($event)->delete();
    }

    #[AsEventListener(event: TheliaEvents::WEBHOOK_SUBSCRIPTION_ROTATE_SECRET)]
    public function rotateSecret(WebhookSubscriptionEvent $event): void
    {
        $subscription = $this->subscriptionOf($event);
        $secret = $this->newSecret();

        $subscription->setSecret($secret)->save();

        $event->setGeneratedSecret($secret);
    }

    #[AsEventListener(event: TheliaEvents::WEBHOOK_SUBSCRIPTION_TEST)]
    public function test(WebhookSubscriptionEvent $event): void
    {
        $subscription = $this->subscriptionOf($event);
        $uuid = $this->uuid();

        $delivery = (new WebhookDelivery())
            ->setWebhookSubscription($subscription)
            ->setEvent(self::TEST_EVENT)
            ->setDeliveryUuid($uuid)
            ->setPayload($this->payloadBuilder->envelope(
                self::TEST_EVENT,
                $uuid,
                ['subscription_id' => $subscription->getId(), 'message' => 'A test request sent from the back office.'],
                new \DateTimeImmutable(),
            ))
            ->setStatus(WebhookStatus::DELIVERY_PENDING);
        $delivery->save();

        if (!$this->deliverer->deliver($delivery, $subscription)) {
            $delivery->setStatus(WebhookStatus::DELIVERY_FAILED)->save();
        }

        $event->setTestDelivery($delivery);
    }

    #[AsEventListener(event: TheliaEvents::WEBHOOK_DELIVERY_REPLAY)]
    public function replay(WebhookDeliveryEvent $event): void
    {
        $delivery = $event->getDelivery() ?? throw new \LogicException($this->translate('The delivery to replay is missing.'));

        if (WebhookStatus::DELIVERY_FAILED !== $delivery->getStatus()) {
            throw new \LogicException($this->translate('Only a failed delivery can be replayed.'));
        }

        if (WebhookStatus::SUBSCRIPTION_ACTIVE !== $delivery->getWebhookSubscription()?->getStatus()) {
            throw new \LogicException($this->translate('The subscription is not active: enable it before replaying a delivery.'));
        }

        $claimed = WebhookDeliveryQuery::create()
            ->filterById($delivery->getId())
            ->filterByStatus(WebhookStatus::DELIVERY_FAILED)
            ->update(['Status' => WebhookStatus::DELIVERY_PENDING, 'Attempts' => 0, 'ReplayedAt' => date('Y-m-d H:i:s')]);

        if (0 === $claimed) {
            throw new \LogicException($this->translate('This delivery is already being replayed.'));
        }

        try {
            $this->bus->dispatch(new DeliverWebhook($delivery->getId()));
        } catch (\Throwable $exception) {
            WebhookDeliveryQuery::create()
                ->filterById($delivery->getId())
                ->filterByStatus(WebhookStatus::DELIVERY_PENDING)
                ->update(['Status' => WebhookStatus::DELIVERY_FAILED]);

            throw $exception;
        }
    }

    private function subscriptionOf(WebhookSubscriptionEvent $event): WebhookSubscription
    {
        return $event->getSubscription() ?? throw new \LogicException($this->translate('The webhook subscription is missing.'));
    }

    private function assertValid(WebhookSubscriptionEvent $event, ?WebhookSubscription $current): void
    {
        $title = trim($event->getTitle());

        if ('' === $title || mb_strlen($title) > 255) {
            throw new \InvalidArgumentException($this->translate('The title is required and holds 255 characters at most.'));
        }

        if (mb_strlen($event->getUrl()) > 2048) {
            throw new \InvalidArgumentException($this->translate('The address holds 2048 characters at most.'));
        }

        try {
            $this->urlValidator->validate($event->getUrl());
        } catch (InvalidWebhookUrlException $exception) {
            throw new InvalidWebhookUrlException($this->translate($exception->getMessage()), previous: $exception);
        }

        if ([] === $event->getEvents()) {
            throw new \InvalidArgumentException($this->translate('Pick at least one event.'));
        }

        foreach ($event->getEvents() as $name) {
            if (!\is_string($name) || !$this->catalog->has($name)) {
                throw new \InvalidArgumentException($this->translate('"%name%" is not a published webhook event.', ['%name%' => \is_string($name) ? $name : \gettype($name)]));
            }
        }

        if (null !== $event->getPayloadMode() && !\in_array($event->getPayloadMode(), [WebhookStatus::PAYLOAD_FULL, WebhookStatus::PAYLOAD_IDENTIFIERS], true)) {
            throw new \InvalidArgumentException($this->translate('The payload mode is unknown.'));
        }

        $unchangedError = WebhookStatus::SUBSCRIPTION_ERROR === $event->getStatus()
            && WebhookStatus::SUBSCRIPTION_ERROR === $current?->getStatus();

        if (null !== $event->getStatus() && !$unchangedError && !\in_array($event->getStatus(), [WebhookStatus::SUBSCRIPTION_ACTIVE, WebhookStatus::SUBSCRIPTION_INACTIVE], true)) {
            throw new \InvalidArgumentException($this->translate('The state is unknown.'));
        }
    }

    /**
     * @param list<string> $events
     */
    private function encodeEvents(array $events): string
    {
        return json_encode(array_values(array_unique($events)), \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function translate(string $message, array $parameters = []): string
    {
        return Translator::getInstance()->trans($message, $parameters, 'core');
    }

    private function newSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

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

namespace Thelia\Domain\Webhook\Message;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Thelia\Domain\Webhook\WebhookDeliverer;
use Thelia\Domain\Webhook\WebhookDeliveryTransport;
use Thelia\Domain\Webhook\WebhookRetryPolicy;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Model\WebhookDeliveryQuery;

#[AsMessageHandler]
final readonly class DeliverWebhookHandler
{
    private const int MAX_LOOKUPS = 5;
    private const int LOOKUP_DELAY_SECONDS = 2;

    private bool $runsInline;

    public function __construct(
        private WebhookDeliverer $deliverer,
        private WebhookRetryPolicy $retryPolicy,
        private MessageBusInterface $bus,
        #[Autowire('%env(default:thelia.messenger.inline_transport_dsn:MESSENGER_TRANSPORT_DSN)%')]
        #[\SensitiveParameter]
        string $jobDsn,
    ) {
        $this->runsInline = WebhookDeliveryTransport::isInline($jobDsn);
    }

    public function __invoke(DeliverWebhook $message): void
    {
        $delivery = WebhookDeliveryQuery::create()->findPk($message->deliveryId);

        if (null === $delivery) {
            $this->lookAgainLater($message);

            return;
        }

        if (WebhookStatus::DELIVERY_PENDING !== $delivery->getStatus()) {
            return;
        }

        $subscription = $delivery->getWebhookSubscription();

        if (null === $subscription || WebhookStatus::SUBSCRIPTION_ACTIVE !== $subscription->getStatus()) {
            $delivery
                ->setStatus(WebhookStatus::DELIVERY_CANCELLED)
                ->setResponseBody(WebhookStatus::SUBSCRIPTION_ERROR === $subscription?->getStatus() ? 'Not sent: the subscription is in error.' : 'Not sent: the subscription is not active.')
                ->save();

            return;
        }

        if ($this->deliverer->deliver($delivery, $subscription)) {
            return;
        }

        $attempt = $delivery->getAttempts();

        if (!$this->runsInline && null === $delivery->getReplayedAt() && $this->retryPolicy->shouldRetry($attempt)) {
            $this->bus->dispatch(new DeliverWebhook($message->deliveryId), [new DelayStamp(($this->retryPolicy->delayAfter($attempt) ?? 0) * 1000)]);

            return;
        }

        $delivery->setStatus(WebhookStatus::DELIVERY_FAILED)->save();

        if (!$this->runsInline && null === $delivery->getReplayedAt()) {
            $subscription
                ->setStatus(WebhookStatus::SUBSCRIPTION_ERROR)
                ->setFailureReason(\sprintf('Delivery %s failed after %d attempts.', $delivery->getDeliveryUuid(), $attempt))
                ->setFailedAt(new \DateTimeImmutable())
                ->save();
        }
    }

    private function lookAgainLater(DeliverWebhook $message): void
    {
        if ($this->runsInline || $message->lookups >= self::MAX_LOOKUPS) {
            return;
        }

        $this->bus->dispatch(
            new DeliverWebhook($message->deliveryId, $message->lookups + 1),
            [new DelayStamp(self::LOOKUP_DELAY_SECONDS * ($message->lookups + 1) * 1000)],
        );
    }
}

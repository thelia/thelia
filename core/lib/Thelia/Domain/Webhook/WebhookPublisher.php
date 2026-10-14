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

namespace Thelia\Domain\Webhook;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Propel;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Domain\Webhook\Message\DeliverWebhook;
use Thelia\Log\Tlog;
use Thelia\Model\Map\WebhookDeliveryTableMap;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookSubscription;
use Thelia\Model\WebhookSubscriptionQuery;

final readonly class WebhookPublisher
{
    public function __construct(
        private WebhookEventCatalog $catalog,
        private WebhookPayloadBuilder $payloadBuilder,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Never throws: a webhook must not get in the way of what produced the event.
     */
    public function publish(string $eventName, ActiveRecordInterface $subject): void
    {
        try {
            $this->doPublish($eventName, $subject);
        } catch (\Throwable $exception) {
            Tlog::getInstance()->error(\sprintf('Webhook "%s" was not published: %s', $eventName, $exception::class));
        }
    }

    private function doPublish(string $eventName, ActiveRecordInterface $subject): void
    {
        if (!$this->catalog->has($eventName)) {
            return;
        }

        $subscriptions = array_filter(
            iterator_to_array(WebhookSubscriptionQuery::create()->filterByStatus(WebhookStatus::SUBSCRIPTION_ACTIVE)->find()),
            fn (WebhookSubscription $subscription): bool => $this->listensTo($subscription, $eventName),
        );

        if ([] === $subscriptions) {
            return;
        }

        $now = new \DateTimeImmutable();
        $dataByMode = [];
        $deliveries = [];

        foreach ($subscriptions as $subscription) {
            $mode = $subscription->getPayloadMode();
            $dataByMode[$mode] ??= $this->payloadBuilder->data($eventName, $subject, $mode);
            $uuid = $this->uuid();

            $deliveries[] = (new WebhookDelivery())
                ->setWebhookSubscription($subscription)
                ->setEvent($eventName)
                ->setDeliveryUuid($uuid)
                ->setPayload($this->payloadBuilder->envelope($eventName, $uuid, $dataByMode[$mode], $now))
                ->setStatus(WebhookStatus::DELIVERY_PENDING);
        }

        $connection = Propel::getConnection(WebhookDeliveryTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            foreach ($deliveries as $delivery) {
                $delivery->save($connection);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        foreach ($deliveries as $delivery) {
            try {
                $this->bus->dispatch(new DeliverWebhook($delivery->getId()));
            } catch (\Throwable $exception) {
                $delivery->setStatus(WebhookStatus::DELIVERY_FAILED)->setResponseBody('The delivery could not be queued: '.$exception::class)->save();
                Tlog::getInstance()->error(\sprintf('Webhook "%s" could not be queued: %s', $eventName, $exception::class));
            }
        }
    }

    private function listensTo(WebhookSubscription $subscription, string $eventName): bool
    {
        try {
            return \in_array($eventName, json_decode($subscription->getEvents(), true, 512, \JSON_THROW_ON_ERROR) ?: [], true);
        } catch (\JsonException) {
            return false;
        }
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

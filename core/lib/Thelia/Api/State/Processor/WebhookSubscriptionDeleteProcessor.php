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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Webhook\Event\WebhookSubscriptionEvent;
use Thelia\Model\WebhookSubscriptionQuery;

/**
 * Deletes a subscription, and with it its delivery log (the foreign key cascades).
 */
final readonly class WebhookSubscriptionDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $subscription = WebhookSubscriptionQuery::create()->findPk((int) ($uriVariables['id'] ?? 0));

        if (null === $subscription) {
            throw new NotFoundHttpException('Webhook subscription not found.');
        }

        $this->eventDispatcher->dispatch(
            (new WebhookSubscriptionEvent())->setSubscription($subscription),
            TheliaEvents::WEBHOOK_SUBSCRIPTION_DELETE,
        );

        return null;
    }
}

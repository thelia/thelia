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
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\WebhookSubscription as WebhookSubscriptionResource;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Webhook\Event\WebhookSubscriptionEvent;
use Thelia\Model\WebhookSubscriptionQuery;

/**
 * Creates or updates a subscription through the events the back office uses, so the
 * address and the event names are checked by one rule. A refusal is a 422.
 *
 * An update writes what the resource holds after the body was applied to it: a
 * field the client left out keeps the value it had.
 */
final readonly class WebhookSubscriptionWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof WebhookSubscriptionResource) {
            throw new UnprocessableEntityHttpException('A webhook subscription is expected.');
        }

        $event = (new WebhookSubscriptionEvent())
            ->setTitle((string) $data->getTitle())
            ->setUrl((string) $data->getUrl())
            ->setEvents($data->getEvents());

        if (null !== $data->getPayloadMode()) {
            $event->setPayloadMode($data->getPayloadMode());
        }

        if (null !== $data->getStatus()) {
            $event->setStatus($data->getStatus());
        }

        $creates = $operation instanceof Post;

        if (!$creates) {
            $subscription = WebhookSubscriptionQuery::create()->findPk((int) ($uriVariables['id'] ?? 0));

            if (null === $subscription) {
                throw new NotFoundHttpException('Webhook subscription not found.');
            }

            $event->setSubscription($subscription);
        }

        try {
            $this->eventDispatcher->dispatch($event, $creates ? TheliaEvents::WEBHOOK_SUBSCRIPTION_CREATE : TheliaEvents::WEBHOOK_SUBSCRIPTION_UPDATE);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->transformer->modelToResource(
            resourceClass: WebhookSubscriptionResource::class,
            propelModel: $event->getSubscription() ?? throw new \LogicException('The subscription was not saved.'),
            context: $context,
        );
    }
}

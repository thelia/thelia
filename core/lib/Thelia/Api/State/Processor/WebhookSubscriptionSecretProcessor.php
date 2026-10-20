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
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\WebhookSubscription as WebhookSubscriptionResource;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Webhook\Event\WebhookSubscriptionEvent;
use Thelia\Model\WebhookSubscriptionQuery;

/**
 * Replaces the signing secret and answers with the new one, the only time an
 * operation but the creation hands it over. The old one stops signing at once.
 */
final readonly class WebhookSubscriptionSecretProcessor implements ProcessorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ApiResourcePropelTransformerService $transformer,
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
            TheliaEvents::WEBHOOK_SUBSCRIPTION_ROTATE_SECRET,
        );

        return $this->transformer->modelToResource(
            resourceClass: WebhookSubscriptionResource::class,
            propelModel: $subscription,
            context: $context,
        );
    }
}

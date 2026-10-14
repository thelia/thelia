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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;

final readonly class WebhookPayloadBuilder
{
    public function __construct(
        private WebhookEventCatalog $catalog,
        private ApiResourcePropelTransformerService $transformer,
        private NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function data(string $eventName, ActiveRecordInterface $subject, string $mode): array
    {
        if (WebhookStatus::PAYLOAD_IDENTIFIERS === $mode) {
            return ['id' => $subject->getPrimaryKey()];
        }

        $resourceClass = $this->catalog->resource($eventName);
        $context = ['groups' => $this->catalog->groups($eventName), 'resource_class' => $resourceClass];

        $resource = $this->transformer->modelToResource($resourceClass, $subject, $context);

        return $this->normalizer->normalize($resource, 'json', $context);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function envelope(string $eventName, string $deliveryUuid, array $data, \DateTimeImmutable $at): string
    {
        return json_encode(
            [
                'id' => $deliveryUuid,
                'type' => $eventName,
                'created_at' => $at->format(\DateTimeInterface::ATOM),
                'data' => $data,
            ],
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );
    }
}

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

namespace Thelia\Api\Serializer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Thelia\Api\Resource\OrderReturnLine;

/**
 * Refuses the admin PATCH of a return line that names its `orderProduct` or its
 * `orderReturn`: a line stays on its order line and in its return.
 *
 * Neither relation is in the write group of the PATCH, so the serializer would
 * ignore them, and the request would answer 200 as if the line had moved. The
 * refusal is raised on the raw body, before an IRI is resolved or a line is
 * read: later, the processor only sees what the write group let through.
 *
 * Every other key that is not writable (`@id`, `refundAmount`, the keys of a
 * line read by a GET) is still ignored, so a client may send back what it read.
 *
 * Only a line denormalized with its own admin write group is concerned: a line
 * nested in the return being created takes the group of the return, and both
 * relations are written there.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => 10])]
final class AdminOrderReturnLinePatchDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public const FIXED_RELATIONS = ['orderProduct', 'orderReturn'];

    private const GUARD = self::class;

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return OrderReturnLine::class === $type
            && \is_array($data)
            && \in_array(OrderReturnLine::GROUP_ADMIN_WRITE, (array) ($context['groups'] ?? []), true)
            && !isset($context[self::GUARD]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [OrderReturnLine::class => false];
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        foreach (self::FIXED_RELATIONS as $relation) {
            if (\is_array($data) && \array_key_exists($relation, $data)) {
                throw new BadRequestHttpException(\sprintf('The "%s" of a return line cannot be changed: a line stays on its order line and in its return.', $relation));
            }
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context + [self::GUARD => true]);
    }
}

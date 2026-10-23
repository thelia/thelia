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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Thelia\Api\Resource\OrderReturn;
use Thelia\Api\Resource\OrderReturnLine;

/**
 * Keeps the lines of a return opened by a customer new.
 *
 * API Platform reads a line given as an IRI, as an object carrying an `@id`
 * (JSON-LD) or an `id` (JSON), from the database and writes the body onto it:
 * the return being opened would take over that line, whichever return and
 * customer it belonged to. Such a line is refused here, before anything is
 * read, with the same answer whether the line exists or not.
 *
 * Only the customer's write group is concerned, and only a line that names an
 * existing one is taken in: a new line goes on to the denormalizers that handle
 * it as before.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => -10])]
final class FrontOrderReturnLineDenormalizer implements DenormalizerInterface
{
    public const EXISTING_LINE_MESSAGE = 'A return line must be a new line, not an existing one.';

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return OrderReturnLine::class === $type
            && \in_array(OrderReturn::GROUP_FRONT_WRITE, (array) ($context['groups'] ?? []), true)
            && self::namesAnExistingLine($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [OrderReturnLine::class => false];
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): never
    {
        throw new UnprocessableEntityHttpException(self::EXISTING_LINE_MESSAGE);
    }

    /**
     * Whether a line of the body points at a line already written rather than
     * describing a new one. The value is never looked up; a key set to null
     * names nothing, for API Platform as here.
     */
    private static function namesAnExistingLine(mixed $data): bool
    {
        return !\is_array($data) || isset($data['@id']) || isset($data['id']);
    }
}

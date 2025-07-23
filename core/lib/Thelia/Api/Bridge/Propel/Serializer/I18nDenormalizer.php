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

namespace Thelia\Api\Bridge\Propel\Serializer;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Thelia\Api\Resource\I18n;

class I18nDenormalizer extends AbstractItemNormalizer
{
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return false;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return I18n::class === $type;
    }

    public function denormalize(mixed $data, string $class, ?string $format = null, array $context = []): mixed
    {
        // Avoid issues with proxies if we populated the object
        if (isset($data['@id']) && !isset($context[self::OBJECT_TO_POPULATE])) {
            if (true !== ($context['api_allow_update'] ?? true)) {
                throw new NotNormalizableValueException('Update is not allowed for this operation.');
            }

            $context[self::OBJECT_TO_POPULATE] = $this->iriConverter->getResourceFromIri($data['@id'], $context + ['fetch_data' => true]);
        }

        $operation = $context['operation'] ?? null;

        if ($operation instanceof HttpOperation) {
            $modelClass = $operation->getClass();

            if (method_exists($modelClass, 'getI18nResourceClass')) {
                $context['resource_class'] = $class = $modelClass::getI18nResourceClass();
            }
        }

        return parent::denormalize($data, $class, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            I18n::class => false,
        ];
    }
}

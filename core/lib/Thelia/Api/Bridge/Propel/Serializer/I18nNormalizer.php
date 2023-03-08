<?php

namespace Thelia\Api\Bridge\Propel\Serializer;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Thelia\Api\Resource\I18n;

class I18nNormalizer extends AbstractItemNormalizer
{
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === I18n::class;
    }

    public function denormalize(mixed $data, string $class, string $format = null, array $context = []): mixed
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
}

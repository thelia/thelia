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

use ReflectionProperty;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionType;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\ResourceClassResolverInterface;
use Propel\Runtime\Collection\Collection;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

class PlainIdentifierDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public function __construct(
        private IriConverterInterface $iriConverter,
        private ResourceClassResolverInterface $resourceClassResolver
    ) {
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        if (!\is_array($data) || !\in_array($format, ['json', 'jsonld'], true) || !class_exists($type)) {
            return false;
        }

        return $this->getNeedConvertProperties($data, $type) !== [];
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return false;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        if (!\is_array($data)) {
            $data = [$data];
        }

        $needConvertProperties = $this->getNeedConvertProperties($data, $type);

        /* @var \ReflectionProperty $needConvertProperty */
        foreach ($needConvertProperties as $needConvertProperty) {
            $data[$needConvertProperty->getName()] = $this->transformData($data, $needConvertProperty);
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context + [self::class => true]);
    }

    private function transformData(mixed $data, ReflectionProperty $property)
    {
        if (\is_string($data[$property->getName()]) || \is_int($data[$property->getName()])) {
            return $this->iriConverter->getIriFromResource(
                resource: $property->getType()->getName(),
                context: ['uri_variables' => ['id' => $data[$property->getName()]]]);
        }

        if (\is_array($data[$property->getName()])) {
            $propelAttributes = array_filter(
                $property->getAttributes(),
                fn(ReflectionAttribute $attribute): bool => $attribute->getName() === Relation::class
            );

            $resource = null;
            foreach ($propelAttributes as $propelAttribute) {
                if (isset($propelAttribute->getArguments()['targetResource'])) {
                    $resource = $propelAttribute->getArguments()['targetResource'];
                }
            }

            if (null !== $resource) {
                return array_map(
                    fn($value): ?string => $this->iriConverter->getIriFromResource(
                        resource: $resource,
                        context: ['uri_variables' => ['id' => $value]]),
                    $data[$property->getName()]
                );
            }
        }

        return $data;
    }

    private function getNeedConvertProperties(array $data, string $type): array
    {
        $resourceReflection = new ReflectionClass($type);
        $properties = $resourceReflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        return array_filter(
            $properties,
            fn(ReflectionProperty $property): bool => $property->getType() instanceof ReflectionType
                && isset($data[$property->getName()])
                && (
                    (
                        \is_array($data[$property->getName()])
                        && array_filter($data[$property->getName()], fn ($value): bool => (\is_string($value) || \is_int($value)) && !str_contains($value, '/'))
                        && Collection::class === $property->getType()->getName()
                    )
                    || (
                        (\is_string($data[$property->getName()]) || \is_int($data[$property->getName()]))
                        && !str_contains($data[$property->getName()], '/')
                        && $this->resourceClassResolver->isResourceClass($property->getType()->getName())
                    )
                )
        );
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false,
        ];
    }
}

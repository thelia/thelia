<?php

namespace Thelia\Api\Bridge\Propel\Serializer;

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

    public function supportsDenormalization(mixed $data, string $type, string $format = null)
    {
        if (!\is_array($data) || !\in_array($format, ['json', 'jsonld'], true) || !class_exists($type)) {
            return false;
        }

        return \count($this->getNeedConvertProperties($data, $type)) > 0;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = [])
    {
        if (!\is_array($data)) {
            $data = [$data];
        }

        $needConvertProperties = $this->getNeedConvertProperties($data, $type);

        /* @var \ReflectionProperty $needConvertProperty */
        foreach ($needConvertProperties as $needConvertProperty) {
            $data[$needConvertProperty->getName()] = $this->transformData($data, $needConvertProperty);
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context + [__CLASS__ => true]);
    }

    private function transformData(mixed $data, \ReflectionProperty $property)
    {
        if (\is_string($data[$property->getName()]) || is_int($data[$property->getName()])) {
            return $this->iriConverter->getIriFromResource(
                resource: $property->getType()->getName(),
                context: ['uri_variables' => ['id' => $data[$property->getName()]]]);
        }

        if (\is_array($data[$property->getName()])) {
            $propelAttributes = array_filter(
                $property->getAttributes(),
                function (\ReflectionAttribute $attribute) {
                    return \in_array(
                        $attribute->getName(),
                        [
                            Relation::class
                        ]
                    );
                }
            );

            $resource = null;
            foreach ($propelAttributes as $propelAttribute) {
                if (isset($propelAttribute->getArguments()['targetResource'])) {
                    $resource = $propelAttribute->getArguments()['targetResource'];
                }
            }

            if (null !== $resource) {
                return array_map(
                    function ($value) use ($resource) {
                        return $this->iriConverter->getIriFromResource(
                            resource: $resource,
                            context: ['uri_variables' => ['id' => $value]]);
                    },
                    $data[$property->getName()]
                );
            }
        }

        return $data;
    }

    private function getNeedConvertProperties(array $data, string $type): array
    {
        $resourceReflection = new \ReflectionClass($type);
        $properties = $resourceReflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);

        return array_filter(
            $properties,
            function (\ReflectionProperty $property) use ($data) {
                return null !== $property->getType()
                    && isset($data[$property->getName()])
                    &&
                    (
                        (
                            \is_array($data[$property->getName()])
                            && array_filter($data[$property->getName()], fn ($value) => (\is_string($value) || is_int($value)) && !str_contains($value, '/'))
                            && Collection::class === $property->getType()->getName()
                        )
                        ||
                        (
                            (\is_string($data[$property->getName()]) || is_int($data[$property->getName()]))
                            && !str_contains($data[$property->getName()], '/')
                            && $this->resourceClassResolver->isResourceClass($property->getType()->getName())
                        )
                    );
            }
        );
    }
}

<?php

namespace Thelia\Api\Bridge\Propel\Loader;

use ApiPlatform\Util\ClassInfoTrait;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

#[AsDecorator(decorates: 'api_platform.serializer.mapping.class_metadata_factory')]
class ClassMetaDataFactory implements ClassMetadataFactoryInterface
{
    use ClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private ClassMetadataFactoryInterface $inner,
        private array $apiResourceExtends
    ) {
    }

    public function getMetadataFor($value): ClassMetadataInterface
    {
        $metadata =  $this->inner->getMetadataFor(\is_object($value) ? $this->getObjectClass($value) : $this->getRealClassName($value));
        $resourceExtends = $this->apiResourceExtends[$metadata->getName()] ?? [];

        if (empty($resourceExtends)) {
            return $metadata;
        }

        $additionalDataGroups = [];
        foreach ($resourceExtends as $resourceExtend) {
            $extendMetadata = $this->inner->getMetadataFor($resourceExtend);
            $additionalDataGroups = array_merge(
                $additionalDataGroups,
                array_map(
                    function (AttributeMetadata $attributeMetadata) {
                        return $attributeMetadata->getGroups();
                    },
                    array_values($extendMetadata->getAttributesMetadata())
                )
            );
        }

        $additionalDataGroups = array_unique(array_merge_recursive(...$additionalDataGroups));
        $additionalDataMetadata = $metadata->getAttributesMetadata()['additionalData'];
        foreach ($additionalDataGroups as $additionalDataGroup) {
            $additionalDataMetadata->addGroup($additionalDataGroup);
        }

        return $metadata;
    }

    public function hasMetadataFor(mixed $value): bool
    {
        return $this->inner->hasMetadataFor(\is_object($value) ? $this->getObjectClass($value) : $this->getRealClassName($value));
    }
}

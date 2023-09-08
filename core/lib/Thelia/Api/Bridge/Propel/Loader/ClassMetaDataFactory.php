<?php

namespace Thelia\Api\Bridge\Propel\Loader;

use ApiPlatform\Util\ClassInfoTrait;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourceService;

#[AsDecorator(decorates: 'api_platform.serializer.mapping.class_metadata_factory')]
class ClassMetaDataFactory implements ClassMetadataFactoryInterface
{
    use ClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private ClassMetadataFactoryInterface $inner,
        private ApiResourceService $apiResourceService
    ) {
    }

    public function getMetadataFor($value): ClassMetadataInterface
    {
        $metadata =  $this->inner->getMetadataFor(\is_object($value) ? $this->getObjectClass($value) : $this->getRealClassName($value));
        $resourceAddons = $this->apiResourceService->getResourceAddonDefinitions($metadata->getName());

        if (empty($resourceAddons)) {
            return $metadata;
        }

        foreach ($resourceAddons as  $addonShortName => $addonClass) {
            $addonMetadata = $this->inner->getMetadataFor($addonClass);
            // Create an attribute with the addon name and set groups of all of his own attributes
            $addonAttributeMetadata = new AttributeMetadata($addonShortName);
            foreach ($addonMetadata->getAttributesMetadata() as $attributeMetadata) {
                foreach ($attributeMetadata->getGroups() as $attributeMetadataGroup) {
                    $addonAttributeMetadata->addGroup($attributeMetadataGroup);
                }
            }

            $metadata->addAttributeMetadata($addonAttributeMetadata);
        }


        return $metadata;
    }

    public function hasMetadataFor(mixed $value): bool
    {
        return $this->inner->hasMetadataFor(\is_object($value) ? $this->getObjectClass($value) : $this->getRealClassName($value));
    }
}

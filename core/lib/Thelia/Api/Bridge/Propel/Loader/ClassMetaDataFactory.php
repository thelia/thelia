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

namespace Thelia\Api\Bridge\Propel\Loader;

use ApiPlatform\Metadata\Util\ClassInfoTrait;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;

#[AsDecorator(decorates: 'api_platform.serializer.mapping.class_metadata_factory')]
class ClassMetaDataFactory implements ClassMetadataFactoryInterface
{
    use ClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private ClassMetadataFactoryInterface $inner,
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
    ) {
    }

    public function getMetadataFor($value): ClassMetadataInterface
    {
        $metadata = $this->inner->getMetadataFor(\is_object($value) ? $this->getObjectClass($value) : $this->getRealClassName($value));
        $resourceAddons = $this->apiResourcePropelTransformerService->getResourceAddonDefinitions($metadata->getName());

        if ([] === $resourceAddons) {
            return $metadata;
        }

        foreach ($resourceAddons as $addonShortName => $addonClass) {
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

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

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;

/**
 * Which resources a module extends is known at runtime, from the modules that are
 * active, so the addon attributes cannot be part of what a cache warmer writes to
 * disk. The decoration priority puts this factory in front of
 * `api_platform.serializer.mapping.cache_class_metadata_factory` (-2): behind it,
 * a warmed metadata cache answers before the addons are aggregated and the
 * serializer drops them from the payload for lack of a group.
 */
#[AsDecorator(decorates: 'api_platform.serializer.mapping.class_metadata_factory', priority: -3)]
class ClassMetaDataFactory implements ClassMetadataFactoryInterface
{
    /**
     * @var array<string, ClassMetadataInterface>
     */
    private array $aggregatedMetadata = [];

    public function __construct(
        #[AutowireDecorated]
        private ClassMetadataFactoryInterface $inner,
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
    ) {
    }

    public function getMetadataFor($value): ClassMetadataInterface
    {
        $className = \is_object($value) ? $this->getObjectClass($value) : $this->getRealClassName($value);

        if (isset($this->aggregatedMetadata[$className])) {
            return $this->aggregatedMetadata[$className];
        }

        $metadata = $this->inner->getMetadataFor($className);
        $resourceAddons = $this->apiResourcePropelTransformerService->getResourceAddonDefinitions($metadata->getName());

        if ([] === $resourceAddons) {
            return $this->aggregatedMetadata[$className] = $metadata;
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

        return $this->aggregatedMetadata[$className] = $metadata;
    }

    public function hasMetadataFor(mixed $value): bool
    {
        return $this->inner->hasMetadataFor(\is_object($value) ? $this->getObjectClass($value) : $this->getRealClassName($value));
    }

    private function getObjectClass(object $object): string
    {
        return $this->getRealClassName($object::class);
    }

    private function getRealClassName(string $className): string
    {
        // Strip Doctrine proxy prefixes (__CG__ for ORM < 3, __PM__ for Ocramius).
        // Propel does not use proxies, but we keep this for robustness.
        $positionCg = strrpos($className, '\\__CG__\\');
        $positionPm = strrpos($className, '\\__PM__\\');

        if (false === $positionCg && false === $positionPm) {
            return $className;
        }

        if (false !== $positionCg) {
            return substr($className, $positionCg + 8);
        }

        $className = ltrim($className, '\\');

        return substr(
            $className,
            8 + $positionPm,
            strrpos($className, '\\') - ($positionPm + 8),
        );
    }
}

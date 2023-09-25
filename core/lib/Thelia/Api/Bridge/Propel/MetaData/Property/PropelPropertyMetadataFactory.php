<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Bridge\Propel\MetaData\Property;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\PropertyInfo\Type;
use Thelia\Api\Bridge\Propel\Service\ApiResourceService;
use Thelia\Api\Resource\TranslatableResourceInterface;

#[AsDecorator(decorates: 'api_platform.metadata.property.metadata_factory')]
class PropelPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly PropertyMetadataFactoryInterface $decorated,
        private ApiResourceService $apiResourceService
    ) {
    }

    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);
        $resourceAddonDefinitions = $this->apiResourceService->getResourceAddonDefinitions($resourceClass);

        if (!empty($resourceAddonDefinitions) && isset($resourceAddonDefinitions[$property])) {
            $propertyMetadata = $propertyMetadata->withBuiltinTypes(
                [new Type(builtinType: 'object', class: $resourceAddonDefinitions[$property])]
            );
            $propertyMetadata = $propertyMetadata->withReadable(true);
            $propertyMetadata = $propertyMetadata->withReadableLink(true);
            $propertyMetadata = $propertyMetadata->withWritable(true);
            $propertyMetadata = $propertyMetadata->withWritableLink(true);
        }

        if ('i18ns' === $property && is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            $i18nReflect = new \ReflectionClass($resourceClass::getI18nResourceClass());

            // Todo check Groups to better fit example with reality
            $propertyMetadata = $propertyMetadata->withOpenapiContext([
                'type' => 'object',
                'example' => [
                    'en_US' => array_reduce(
                        $i18nReflect->getProperties(),
                        function (array $carry, \ReflectionProperty $property) {
                            if ('id' === $property->getName()) {
                                return $carry;
                            }

                            $carry[$property->getName()] = 'en_US '.$property->getName();

                            return $carry;
                        },
                        []
                    ),
                    'fr_FR' => array_reduce(
                        $i18nReflect->getProperties(),
                        function (array $carry, \ReflectionProperty $property) {
                            if ('id' === $property->getName()) {
                                return $carry;
                            }

                            $carry[$property->getName()] = 'fr_FR '.$property->getName();

                            return $carry;
                        },
                        []
                    ),
                ],
            ]);
        }

        return $propertyMetadata;
    }
}

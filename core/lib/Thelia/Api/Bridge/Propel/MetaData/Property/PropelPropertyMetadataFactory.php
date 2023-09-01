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
use Thelia\Api\Resource\Address;
use Thelia\Api\Resource\TranslatableResourceInterface;

class PropelPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(private readonly PropertyMetadataFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        if ($resourceClass === Address::class && $property === "customerTitle") {
//            dump($resourceClass, $property, $propertyMetadata);
        }

        if ('additionalData' === $property) {
//            $propertyMetadata = $propertyMetadata->withOpenapiContext([
//                'type' => 'object',
//
//            ]);

            $propertyMetadata =  $propertyMetadata->withSchema([                                'type' => 'object',
                'properties' => [
                    'refresh_token' => [
                        'type' => 'string',
                    ],
                ]]);
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

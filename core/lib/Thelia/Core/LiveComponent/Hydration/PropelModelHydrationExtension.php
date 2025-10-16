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

namespace Thelia\Core\LiveComponent\Hydration;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\UX\LiveComponent\Hydration\HydrationExtensionInterface;

#[AutoconfigureTag('live_component.hydration_extension')]
class PropelModelHydrationExtension implements HydrationExtensionInterface
{
    public function supports(string $className): bool
    {
        return is_subclass_of($className, ActiveRecordInterface::class);
    }

    public function hydrate(mixed $value, string $className): ?object
    {
        if ($value instanceof ActiveRecordInterface) {
            return $value;
        }

        if (\is_array($value) && isset($value['__propel_class'], $value['__id']) && \is_string($value['__propel_class'])) {
            $fqcn = $value['__propel_class'];
            $id = $value['__id'];
            $queryClass = $fqcn.'Query';
            if (class_exists($queryClass) && method_exists($queryClass, 'create')) {
                /** @var ModelCriteria $query */
                $query = $queryClass::create();

                return $query->findPk($id);
            }

            return null;
        }

        if (\is_scalar($value)) {
            $queryClass = $className.'Query';
            if (class_exists($queryClass) && method_exists($queryClass, 'create')) {
                /** @var ModelCriteria $query */
                $query = $queryClass::create();

                return $query->findPk($value);
            }

            return null;
        }

        return null;
    }

    public function dehydrate(object $object): array|object
    {
        if (!$object instanceof ActiveRecordInterface) {
            return $object;
        }

        $class = $object::class;
        $pk = method_exists($object, 'getPrimaryKey') ? $object->getPrimaryKey() : null;
        if ($pk === null && method_exists($object, 'getId')) {
            $pk = $object->getId();
        }

        return [
            '__propel_class' => $class,
            '__id' => $pk,
        ];
    }
}

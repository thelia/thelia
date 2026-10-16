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

namespace Thelia\Model;

use Propel\Runtime\Map\TableMap;
use Thelia\Model\Base\CustomerPaymentMethod as BaseCustomerPaymentMethod;
use Thelia\Model\Map\CustomerPaymentMethodTableMap;

/**
 * A payment method a customer asked the shop to keep, as the opaque reference the
 * provider handed back.
 *
 * The token reaches the provider and nothing else: the array, export, string and debug
 * forms of the row leave it out, so a log line, an export or a dump that picks the
 * object up does not carry it, and neither does its serialized form. getToken() is the
 * one way to read it.
 */
class CustomerPaymentMethod extends BaseCustomerPaymentMethod
{
    /**
     * The expiry is the last day the provider accepts the method: on that very day the
     * method still pays.
     */
    public function isExpiredOn(\DateTimeInterface $day): bool
    {
        $lastDay = $this->getExpiresAt('Y-m-d');

        return \is_string($lastDay) && $lastDay < $day->format('Y-m-d');
    }

    /**
     * @param array<string, array<string, bool>> $alreadyDumpedObjects
     *
     * @return array<string, mixed>
     */
    public function toArray(
        string $keyType = TableMap::TYPE_PHPNAME,
        bool $includeLazyLoadColumns = true,
        array $alreadyDumpedObjects = [],
        bool $includeForeignObjects = false,
    ): array {
        $row = parent::toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, $includeForeignObjects);

        unset($row[CustomerPaymentMethodTableMap::translateFieldName('Token', TableMap::TYPE_PHPNAME, $keyType)]);

        return $row;
    }

    public function __toString(): string
    {
        return \sprintf(
            'Stored payment method #%s of customer #%s (module #%s, "%s")',
            (string) $this->getId(),
            (string) $this->getCustomerId(),
            (string) $this->getPaymentModuleId(),
            (string) $this->getLabel(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->getId(),
            'customerId' => $this->getCustomerId(),
            'paymentModuleId' => $this->getPaymentModuleId(),
            'label' => $this->getLabel(),
            'expiresAt' => $this->getExpiresAt(),
            'isDefault' => $this->getIsDefault(),
        ];
    }

    /**
     * Whatever carries the row through serialize(), a customer and its methods kept in a
     * session for instance, carries it without the token: the copy it gets back cannot
     * pay nor be forgotten, only read.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $properties = [];

        foreach ($this->__sleep() as $propertyName) {
            $properties[$propertyName] = $this->{$propertyName};
        }

        $properties['token'] = null;
        unset($properties['modifiedColumns'][CustomerPaymentMethodTableMap::COL_TOKEN]);

        return $properties;
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function __unserialize(array $properties): void
    {
        foreach ($properties as $propertyName => $value) {
            if (property_exists($this, $propertyName)) {
                $this->{$propertyName} = $value;
            }
        }
    }
}

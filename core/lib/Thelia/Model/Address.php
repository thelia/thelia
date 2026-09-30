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

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\Address as BaseAddress;
use Thelia\Model\Map\AddressTableMap;

class Address extends BaseAddress
{
    /**
     * put the the current address as default one.
     */
    public function makeItDefault(): void
    {
        AddressQuery::create()->filterByCustomerId($this->getCustomerId())
            ->update(['IsDefault' => '0']);

        $this->setIsDefault(1);
        $this->save();
    }

    /**
     * Code to be run before deleting the object in database.
     */
    public function preDelete(?ConnectionInterface $con = null): bool
    {
        parent::preDelete($con);

        return !$this->getIsDefault();
    }

    public function preUpdate(?ConnectionInterface $con = null): bool
    {
        $this->dropVatVerificationWhenItsSubjectChanges();

        return parent::preUpdate($con);
    }

    public function getVatVerificationValid(): bool
    {
        $verifiedAt = $this->getVatVerifiedAt();

        if (null === $verifiedAt) {
            return false;
        }

        $expiresAt = \DateTimeImmutable::createFromInterface($verifiedAt)
            ->modify(\sprintf('+%d days', ConfigQuery::getVatVerificationLifetimeDays()));

        return $expiresAt >= new \DateTimeImmutable();
    }

    private function dropVatVerificationWhenItsSubjectChanges(): void
    {
        $subjectChanged = $this->isColumnModified(AddressTableMap::COL_VAT_NUMBER)
            || $this->isColumnModified(AddressTableMap::COL_COUNTRY_ID);

        if (!$subjectChanged || $this->isColumnModified(AddressTableMap::COL_VAT_VERIFIED_AT)) {
            return;
        }

        $this
            ->setVatVerifiedAt(null)
            ->setVatVerifiedName(null);
    }
}

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
    private const array COLUMNS_COPIED_TO_CARTS = [
        AddressTableMap::COL_TITLE_ID,
        AddressTableMap::COL_COMPANY,
        AddressTableMap::COL_SIRET,
        AddressTableMap::COL_VAT_NUMBER,
        AddressTableMap::COL_VAT_VERIFIED_AT,
        AddressTableMap::COL_VAT_VERIFIED_NAME,
        AddressTableMap::COL_FIRSTNAME,
        AddressTableMap::COL_LASTNAME,
        AddressTableMap::COL_ADDRESS1,
        AddressTableMap::COL_ADDRESS2,
        AddressTableMap::COL_ADDRESS3,
        AddressTableMap::COL_ZIPCODE,
        AddressTableMap::COL_CITY,
        AddressTableMap::COL_PHONE,
        AddressTableMap::COL_CELLPHONE,
        AddressTableMap::COL_COUNTRY_ID,
        AddressTableMap::COL_STATE_ID,
    ];

    private bool $cartCopiesAreStale = false;

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
        $this->dropVatVerificationWhenItsSubjectChanges($con);
        $this->cartCopiesAreStale = [] !== array_intersect($this->getModifiedColumns(), self::COLUMNS_COPIED_TO_CARTS);

        return parent::preUpdate($con);
    }

    public function postUpdate(?ConnectionInterface $con = null): void
    {
        if ($this->cartCopiesAreStale) {
            $this->cartCopiesAreStale = false;

            $invoiceCopyIds = CartQuery::create()
                ->useCartAddressRelatedByAddressInvoiceIdQuery()
                    ->filterByAddressId($this->getId())
                ->endUse()
                ->where('NOT EXISTS (SELECT 1 FROM `order` WHERE `order`.`cart_id` = `cart`.`id`)')
                ->select('AddressInvoiceId')
                ->find($con)
                ->getData();

            foreach (CartAddressQuery::create()->filterById($invoiceCopyIds)->find($con) as $cartAddress) {
                $cartAddress->copyFrom($this)->save($con);
            }
        }

        parent::postUpdate($con);
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

    private function dropVatVerificationWhenItsSubjectChanges(?ConnectionInterface $con): void
    {
        $subjectChanged = $this->isColumnModified(AddressTableMap::COL_VAT_NUMBER)
            || $this->isColumnModified(AddressTableMap::COL_COUNTRY_ID);

        if (!$subjectChanged || $this->isColumnModified(AddressTableMap::COL_VAT_VERIFIED_AT)) {
            return;
        }

        if (null === $this->getVatVerifiedAt() && !$this->storedVerificationCoversAnotherSubject($con)) {
            return;
        }

        $this
            ->setVatVerifiedAt(null)
            ->setVatVerifiedName(null);

        // An address rebuilt from an API payload (PUT) never read its verification: both
        // columns are already null in memory, so the setters above change nothing. Marking
        // them modified is what makes the update blank the verification stored in the row.
        $this->modifiedColumns[AddressTableMap::COL_VAT_VERIFIED_AT] = true;
        $this->modifiedColumns[AddressTableMap::COL_VAT_VERIFIED_NAME] = true;
    }

    /**
     * Whether the row holds a verification that was made for another number or country
     * than the ones about to be written. Only asked when the verification is not in memory.
     */
    private function storedVerificationCoversAnotherSubject(?ConnectionInterface $con): bool
    {
        $stored = AddressQuery::create()
            ->filterById($this->getId())
            ->select(['VatNumber', 'CountryId', 'VatVerifiedAt'])
            ->findOne($con);

        if (null === $stored || null === $stored['VatVerifiedAt']) {
            return false;
        }

        return $stored['VatNumber'] !== $this->getVatNumber()
            || (int) $stored['CountryId'] !== (int) $this->getCountryId();
    }
}

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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Legal\VatNumberVerifiedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Legal\Enum\VatVerificationStatus;
use Thelia\Model\AddressQuery;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CartAddressTableMap;

/**
 * Records on the address what a verification service answered about its VAT number.
 *
 * Only this listener writes those columns. A refusal and an unanswered check
 * both clear the state rather than leaving the previous answer standing: a
 * number that no longer verifies must stop exempting, and a service that could
 * not be reached has told us nothing about it either.
 */
class VatVerification extends BaseAction implements EventSubscriberInterface
{
    public function record(VatNumberVerifiedEvent $event): void
    {
        $result = $event->getResult();
        $verified = VatVerificationStatus::VERIFIED === $result->status;

        $address = $event->getAddress();
        $verifiedAt = $verified ? $result->verifiedAt : null;
        $verifiedName = $verified ? $result->verifiedName : null;

        if (!$this->addressStillCarrying($event)->exists()) {
            return;
        }

        $values = [
            'VatVerifiedAt' => $verifiedAt?->format('Y-m-d H:i:s'),
            'VatVerifiedName' => $verifiedName,
        ];

        $this->addressStillCarrying($event)->update($values);

        CartAddressQuery::create()
            ->filterByAddressId($address->getId())
            ->filterByVatNumber($event->getVerifiedVatNumber())
            ->filterByCountryId($event->getVerifiedCountryId())
            ->update($values);
        CartAddressTableMap::clearInstancePool();

        $address
            ->setVatVerifiedAt($verifiedAt)
            ->setVatVerifiedName($verifiedName);
        $address->resetModified(AddressTableMap::COL_VAT_VERIFIED_AT);
        $address->resetModified(AddressTableMap::COL_VAT_VERIFIED_NAME);
    }

    private function addressStillCarrying(VatNumberVerifiedEvent $event): AddressQuery
    {
        return AddressQuery::create()
            ->filterById($event->getAddress()->getId())
            ->filterByVatNumber($event->getVerifiedVatNumber())
            ->filterByCountryId($event->getVerifiedCountryId());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::VAT_NUMBER_VERIFIED => ['record', 128],
        ];
    }
}

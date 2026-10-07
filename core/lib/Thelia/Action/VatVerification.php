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

class VatVerification extends BaseAction implements EventSubscriberInterface
{
    public function record(VatNumberVerifiedEvent $event): void
    {
        $result = $event->getResult();

        if (VatVerificationStatus::UNDETERMINED === $result->status) {
            return;
        }

        if ('' === trim((string) $event->getVerifiedVatNumber())) {
            return;
        }

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

        $copies = CartAddressQuery::create()
            ->filterByAddressId($address->getId())
            ->filterByVatNumber($event->getVerifiedVatNumber())
            ->filterByCountryId($event->getVerifiedCountryId())
            ->find();

        foreach ($copies as $copy) {
            $copy
                ->setVatVerifiedAt($verifiedAt)
                ->setVatVerifiedName($verifiedName)
                ->save();
        }

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

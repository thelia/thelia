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

namespace Thelia\Action;

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Address as AddressModel;
use Thelia\Model\Event\AddressEvent;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Service\Model\CustomerService;

/**
 * Class Address.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Address extends BaseAction implements EventSubscriberInterface
{
    public function __construct(private readonly CustomerService $customerService)
    {
    }

    public function create(AddressCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $address = new AddressModel();
        $address->setCustomer($event->getCustomer());
        $this->createOrUpdate($address, $event, $dispatcher);
    }

    public function update(AddressCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $addressModel = $event->getAddress();

        $this->createOrUpdate($addressModel, $event, $dispatcher);
    }

    public function delete(AddressEvent $event): void
    {
        $address = $event->getModel();

        $address->delete();
    }

    public function useDefault(AddressEvent $event): void
    {
        $address = $event->getModel();

        $address->makeItDefault();
    }

    protected function createOrUpdate(AddressModel $addressModel, AddressCreateOrUpdateEvent $event, $dispatcher): void
    {
        $con = Propel::getWriteConnection(AddressTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $addressModel
                ->setLabel($event->getLabel())
                ->setTitleId($event->getTitle() ?? $this->customerService->getDefaultCustomerTitle()->getId())
                ->setFirstname($event->getFirstname())
                ->setLastname($event->getLastname())
                ->setAddress1($event->getAddress1())
                ->setAddress2($event->getAddress2())
                ->setAddress3($event->getAddress3())
                ->setZipcode($event->getZipcode())
                ->setCity($event->getCity())
                ->setCountryId($event->getCountry())
                ->setStateId($event->getState())
                ->setCellphone($event->getCellphone())
                ->setPhone($event->getPhone())
                ->setCompany($event->getCompany())
                ->save();

            if ($event->getIsDefault() && !$addressModel->getIsDefault()) {
                $addressModel->makeItDefault();
            }

            $event->setAddress($addressModel);
            $con->commit();
        } catch (PropelException $e) {
            $con->rollback();
            throw $e;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ADDRESS_CREATE => ['create', 128],
            TheliaEvents::ADDRESS_UPDATE => ['update', 128],
            TheliaEvents::ADDRESS_DELETE => ['delete', 128],
            TheliaEvents::ADDRESS_DEFAULT => ['useDefault', 128],
        ];
    }
}

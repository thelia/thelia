<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Action;

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\Address\AddressEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Address as AddressModel;
use Thelia\Model\Map\AddressTableMap;

/**
 * Class Address
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Address extends BaseAction implements EventSubscriberInterface
{
    public function create(AddressCreateOrUpdateEvent $event)
    {
        $address = new AddressModel();
        $address->setCustomer($event->getCustomer());
        $this->createOrUpdate($address, $event);
    }

    public function update(AddressCreateOrUpdateEvent $event)
    {
        $addressModel = $event->getAddress();

        $this->createOrUpdate($addressModel, $event);
    }

    public function delete(AddressEvent $event)
    {
        $address = $event->getAddress();

        $address->delete();
    }

    public function useDefault(AddressEvent $event)
    {
        $address = $event->getAddress();

        $address->makeItDefault();
    }

    protected function createOrUpdate(AddressModel $addressModel, AddressCreateOrUpdateEvent $event)
    {
        $addressModel->setDispatcher($event->getDispatcher());
        $con = Propel::getWriteConnection(AddressTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $addressModel
                ->setLabel($event->getLabel())
                ->setTitleId($event->getTitle())
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
                ->save()
            ;

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

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ADDRESS_CREATE => array("create", 128),
            TheliaEvents::ADDRESS_UPDATE => array("update", 128),
            TheliaEvents::ADDRESS_DELETE => array("delete", 128),
            TheliaEvents::ADDRESS_DEFAULT => array('useDefault', 128),
        );
    }
}

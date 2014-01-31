<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Address implements EventSubscriberInterface
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
                ->setCellphone($event->getCellphone())
                ->setPhone($event->getPhone())
                ->setCompany($event->getCompany())
                ->save()
            ;

            if ($event->getIsDefault()) {
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

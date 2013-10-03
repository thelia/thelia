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

namespace Thelia\Controller\Admin;
use Thelia\Core\Event\Address\AddressEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AddressQuery;


/**
 * Class AddressController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AddressController extends BaseAdminController
{
    public function deleteAddressAction()
    {
        if (null !== $response = $this->checkAuth("admin.customer.update")) return $response;

        $address_id = $this->getRequest()->request->get('address_id');

        try {
            $address = AddressQuery::create()->findPk($address_id);

            if (null === $address) {
                throw new \InvalidArgumentException(sprintf('%d address does not exists', $address_id));
            }

            $addressEvent = new AddressEvent($address);

            $this->dispatch(TheliaEvents::ADDRESS_DELETE, $addressEvent);

            $this->adminLogAppend(sprintf("address %d for customer %d removal", $address_id, $address->getCustomerId()));
        } catch(\Exception $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("error during address removal with message %s", $e->getMessage()));
        }

        $this->redirectToRoute('admin.customer.update.view', array(), array('customer_id' => $address->getCustomerId()));
    }

    public function useAddressAction()
    {
        if (null !== $response = $this->checkAuth("admin.customer.update")) return $response;

        $address_id = $this->getRequest()->request->get('address_id');

        try {
            $address = AddressQuery::create()->findPk($address_id);

            if (null === $address) {
                throw new \InvalidArgumentException(sprintf('%d address does not exists', $address_id));
            }

            $addressEvent = new AddressEvent($address);

            $this->dispatch(TheliaEvents::ADDRESS_DEFAULT, $addressEvent);

            $this->adminLogAppend(sprintf("address %d for customer %d removal", $address_id, $address->getCustomerId()));
        } catch(\Exception $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("error during address removal with message %s", $e->getMessage()));
        }

        $this->redirectToRoute('admin.customer.update.view', array(), array('customer_id' => $address->getCustomerId()));
    }
}
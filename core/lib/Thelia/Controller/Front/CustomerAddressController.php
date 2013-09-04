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

namespace Thelia\Controller\Front;
use Thelia\Core\Event\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\AddressForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Customer;
use Thelia\Tools\URL;


/**
 * Class CustomerAddressController
 * @package Thelia\Controller\Front
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerAddressController extends BaseFrontController
{

    public function createAction()
    {
        if ($this->getSecurityContext()->hasCustomerUser() === false) {
            $this->redirect(URL::getIndexPage());
        }

        $addressCreate = new AddressForm($this->getRequest());

        try {
            $customer = $this->getSecurityContext()->getCustomerUser();

            $form = $this->validateForm($addressCreate, "post");
            $event = $this->createAddressEvent($form->getData());
            $event->setCustomer($customer);

            $this->dispatch(TheliaEvents::ADDRESS_CREATE, $event);
            $this->redirectSuccess($addressCreate);

        }catch (FormValidationException $e) {
            $message = sprintf("Please check your input: %s", $e->getMessage());
        }
        catch (\Exception $e) {
            $message = sprintf("Sorry, an error occured: %s", $e->getMessage());
        }

        if ($message !== false) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("Error during address creation process : %s", $message));

            $addressCreate->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($addressCreate)
                ->setGeneralError($message)
            ;
        }
    }

    public function updateAction()
    {

    }

    protected function createAddressEvent($data)
    {
        return new AddressCreateOrUpdateEvent(
            $data["label"],
            $data["title"],
            $data["firstname"],
            $data["lastname"],
            $data["address1"],
            $data["address2"],
            $data["address3"],
            $data["zipcode"],
            $data["city"],
            $data["country"],
            $data["cellpone"],
            $data["phone"],
            $data["company"]
        );
    }
}
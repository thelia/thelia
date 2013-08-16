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

use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Event\CustomerCreateEvent;
use Thelia\Core\Event\CustomerEvent;
use Thelia\Core\Security\SecurityContext;
use Thelia\Form\CustomerCreation;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Customer;
use Thelia\Core\Event\TheliaEvents;

class CustomerController extends BaseFrontController
{
    /**
     * create a new Customer. Retrieve data in form and dispatch a action.createCustomer event
     *
     * if error occurs, message is set in the parserContext
     */
    public function createAction()
    {
        $request = $this->getRequest();
        $customerCreation = new CustomerCreation($$request);
        try {
            $form = $this->validateForm($customerCreation, "post");

            $data = $form->getData();

            $customerCreateEvent = new CustomerCreateEvent(
                $data["title"],
                $data["firstname"],
                $data["lastname"],
                $data["address1"],
                $data["address2"],
                $data["address3"],
                $data["phone"],
                $data["cellphone"],
                $data["zipcode"],
                $data["city"],
                $data["country"],
                $data["email"],
                $data["password"],
                $request->getSession()->getLang(),
                $data["reseller"],
                $data["sponsor"],
                $data["discount"]
            );

            $this->getDispatcher()->dispatch(TheliaEvents::CUSTOMER_CREATEACCOUNT, $customerCreateEvent);

            $this->processLogin($customerCreateEvent->getCustomer());

            $this->redirectSuccess();

        } catch (FormValidationException $e) {
            $customerCreation->setErrorMessage($e->getMessage());
            $this->getParserContext()->setErrorForm($customerCreation);
        } catch (PropelException $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("error during customer creation process in front context with message : %s", $e->getMessage()));
            $this->getParserContext()->setGeneralError($e->getMessage());
        }


    }

    public function loginAction()
    {
        $event = $this->dispatchEvent("loginCustomer");

        $customerEvent = new CustomerEvent($event->getCustomer());

        $this->processLogin($event->getCustomer(), $customerEvent, true);
    }

    public function processLogin(Customer $customer,$event = null, $sendLogin = false)
    {
        $this->getSecurityContext(SecurityContext::CONTEXT_FRONT_OFFICE)->setUser($customer);

        if($sendLogin) $this->dispatch(TheliaEvents::CUSTOMER_LOGIN, $event);
    }

}

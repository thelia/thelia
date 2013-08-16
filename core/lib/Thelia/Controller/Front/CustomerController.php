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

use Thelia\Core\Event\CustomerEvent;
use Thelia\Core\Security\SecurityContext;
use Thelia\Form\CustomerCreation;
use Thelia\Model\Customer;
use Thelia\Core\Event\TheliaEvents;

class CustomerController extends BaseFrontController
{
    public function createAction()
    {
        $request = $this->getRequest();
        $customerCreation = new CustomerCreation($$request);

        $form = $this->validateForm($customerCreation, "post");

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

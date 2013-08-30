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
use Symfony\Component\Validator\Exception\ValidatorException;
use Thelia\Core\Event\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\CustomerLoginEvent;
use Thelia\Core\Security\Authentication\CustomerUsernamePasswordFormAuthenticator;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\UsernameNotFoundException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Form\CustomerCreation;
use Thelia\Form\CustomerLogin;
use Thelia\Form\CustomerModification;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Customer;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\CustomerEvent;

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
        $customerCreation = new CustomerCreation($request);
        try {
            $form = $this->validateForm($customerCreation, "post");

            $customerCreateEvent = $this->createEventInstance($form->getData());

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

    public function updateAction()
    {
        $request = $this->getRequest();
        $customerModification = new CustomerModification($request);

        try {

            $customer = $this->getSecurityContext()->getCustomerUser();

            $form = $this->validateForm($customerModification, "post");

            $customerChangeEvent = $this->createEventInstance($form->getData());
            $customerChangeEvent->setCustomer($customer);

            $this->getDispatcher()->dispatch(TheliaEvents::CUSTOMER_UPDATEACCOUNT, $customerChangeEvent);

            $this->processLogin($customerChangeEvent->getCustomer());

            $this->redirectSuccess();

        } catch (FormValidationException $e) {
            $customerModification->setErrorMessage($e->getMessage());
            $this->getParserContext()->setErrorForm($customerModification);
        } catch (PropelException $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("error during updating customer in front context with message : %s", $e->getMessage()));
            $this->getParserContext()->setGeneralError($e->getMessage());
        }
    }

    /**
     * Perform user login. On a successful login, the user is redirected to the URL
     * found in the success_url form parameter, or / if none was found.
     *
     * If login is not successfull, the same view is dispolyed again.
     *
     */
    public function loginAction()
    {
        $request = $this->getRequest();

        $customerLoginForm = new CustomerLogin($request);

        $authenticator = new CustomerUsernamePasswordFormAuthenticator($request, $customerLoginForm);

        try {
            $customer = $authenticator->getAuthentifiedUser();

            $this->processLogin($customer);

            $this->redirectSuccess();
        } catch (ValidatorException $e) {

        } catch(UsernameNotFoundException $e) {

        } catch(AuthenticationException $e) {

        } catch (\Exception $e) {

        }
    }

    public function processLogin(Customer $customer)
    {
        $this->getSecurityContext()->setCustomerUser($customer);

        if($event) $this->dispatch(TheliaEvents::CUSTOMER_LOGIN, new CustomerLoginEvent($customer));
    }

    /**
     * @param $data
     * @return CustomerCreateOrUpdateEvent
     */
    private function createEventInstance($data)
    {
        $customerCreateEvent = new CustomerCreateOrUpdateEvent(
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
            isset($data["email"])?$data["email"]:null,
            isset($data["password"]) ? $data["password"]:null,
            $this->getRequest()->getSession()->getLang(),
            isset($data["reseller"])?$data["reseller"]:null,
            isset($data["sponsor"])?$data["sponsor"]:null,
            isset($data["discount"])?$data["discount"]:nullsch
        );

        return $customerCreateEvent;
    }

}

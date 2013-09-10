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
use Thelia\Core\Factory\ActionEventFactory;
use Thelia\Tools\URL;
use Thelia\Log\Tlog;
use Thelia\Core\Security\Exception\WrongPasswordException;
use Symfony\Component\Routing\Router;

/**
 * Class CustomerController
 * @package Thelia\Controller\Front
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerController extends BaseFrontController
{
    /**
     * Create a new customer.
     * On success, redirect to success_url if exists, otherwise, display the same view again.
     */
    public function createAction()
    {
        if (! $this->getSecurityContext()->hasCustomerUser()) {

            $message = false;

            $customerCreation = new CustomerCreation($this->getRequest());

            try {
                $form = $this->validateForm($customerCreation, "post");

                $customerCreateEvent = $this->createEventInstance($form->getData());

                $this->dispatch(TheliaEvents::CUSTOMER_CREATEACCOUNT, $customerCreateEvent);

                $this->processLogin($customerCreateEvent->getCustomer());

                $this->redirectSuccess($customerCreation);
            }
            catch (FormValidationException $e) {
                $message = sprintf("Please check your input: %s", $e->getMessage());
            }
            catch (\Exception $e) {
                $message = sprintf("Sorry, an error occured: %s", $e->getMessage());
            }

            if ($message !== false) {
                Tlog::getInstance()->error(sprintf("Error during customer creation process : %s. Exception was %s", $message, $e->getMessage()));

                $customerCreation->setErrorMessage($message);

                $this->getParserContext()
                    ->addForm($customerCreation)
                    ->setGeneralError($message)
                ;
            }
        }
    }

    /**
     * Update customer data. On success, redirect to success_url if exists.
     * Otherwise, display the same view again.
     */
    public function updateAction()
    {
        if ($this->getSecurityContext()->hasCustomerUser()) {

            $message = false;

            $customerModification = new CustomerModification($this->getRequest());

            try {

                $customer = $this->getSecurityContext()->getCustomerUser();

                $form = $this->validateForm($customerModification, "post");

                $customerChangeEvent = $this->createEventInstance($form->getData());
                $customerChangeEvent->setCustomer($customer);

                $this->dispatch(TheliaEvents::CUSTOMER_UPDATEACCOUNT, $customerChangeEvent);

                $this->processLogin($customerChangeEvent->getCustomer());

                $this->redirectSuccess($customerModification);

            }
            catch (FormValidationException $e) {
                $message = sprintf("Please check your input: %s", $e->getMessage());
            }
            catch (\Exception $e) {
                $message = sprintf("Sorry, an error occured: %s", $e->getMessage());
            }

            if ($message !== false) {
                Tlog::getInstance()->error(sprintf("Error during customer modification process : %s.", $message));

                $customerModification->setErrorMessage($message);

                $this->getParserContext()
                    ->addForm($customerModification)
                    ->setGeneralError($message)
                ;
            }
        }
    }

    /**
     * Perform user login. On a successful login, the user is redirected to the URL
     * found in the success_url form parameter, or / if none was found.
     *
     * If login is not successfull, the same view is displayed again.
     *
     */
    public function loginAction()
    {
        if (! $this->getSecurityContext()->hasCustomerUser()) {
            $message = false;

            $request = $this->getRequest();
            $customerLoginForm = new CustomerLogin($request);

            try {

                $form = $this->validateForm($customerLoginForm, "post");

                $authenticator = new CustomerUsernamePasswordFormAuthenticator($request, $customerLoginForm);

                $customer = $authenticator->getAuthentifiedUser();

                $this->processLogin($customer);

                $this->redirectSuccess($customerLoginForm);

            }
            catch (FormValidationException $e) {

                if ($request->request->has("account")) {
                    $account = $request->request->get("account");
                    $form = $customerLoginForm->getForm();
                    if($account == 0 && $form->get("email")->getData() !== null) {
                        $this->redirectToRoute("customer.create.view", array("email" => $form->get("email")->getData()));
                    }
                }

                $message = sprintf("Please check your input: %s", $e->getMessage());
            }
            catch(UsernameNotFoundException $e) {
                $message = "Wrong email or password. Please try again";
            }
            catch (WrongPasswordException $e) {
                $message = "Wrong email or password. Please try again";
            }
            catch(AuthenticationException $e) {
                $message = "Wrong email or password. Please try again";
            }
            catch (\Exception $e) {
                $message = sprintf("Sorry, an error occured: %s", $e->getMessage());
            }

            if ($message !== false) {
                Tlog::getInstance()->error(sprintf("Error during customer login process : %s. Exception was %s", $message, $e->getMessage()));

                $customerLoginForm->setErrorMessage($message);

                $this->getParserContext()->addForm($customerLoginForm);
            }
        }
    }

    /**
     * Perform customer logout.
     */
    public function logoutAction()
    {
        if ($this->getSecurityContext()->hasCustomerUser()) {
            $this->dispatch(TheliaEvents::CUSTOMER_LOGOUT);
        }

        // Redirect to home page
        $this->redirect(URL::getInstance()->getIndexPage());
    }

    /**
     * Dispatch event for customer login action
     *
     * @param Customer $customer
     */
    protected function processLogin(Customer $customer)
    {
        $this->dispatch(TheliaEvents::CUSTOMER_LOGIN, new CustomerLoginEvent($customer));
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
            isset($data["discount"])?$data["discount"]:null
        );

        return $customerCreateEvent;
    }
}

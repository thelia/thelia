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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\CustomerEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\BaseForm;
use Thelia\Form\CustomerCreation;
use Thelia\Form\CustomerModification;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Log\Tlog;
use Thelia\Model\CustomerQuery;
use Thelia\Form\CustomerLogin;
use Thelia\Core\Security\Authentication\CustomerUsernamePasswordFormAuthenticator;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\ConfigQuery;
use Symfony\Component\Validator\Exception\ValidatorException;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\UsernameNotFoundException;
use Propel\Runtime\Exception\PropelException;


class Customer extends BaseAction implements EventSubscriberInterface
{
	/**
	 * @var Thelia\Core\Security\SecurityContext
	 */
	protected $securityContext;

	public function __construct(SecurityContext $securityContext) {
		$this->securityContext = $securityContext;
	}

    public function create(ActionEvent $event)
    {
        $request = $event->getRequest();

        $customerCreationForm = new CustomerCreation($request);

        $form = $customerCreationForm->getForm();

        if ($request->isMethod("post")) {

            $form->bind($request);

            if ($form->isValid()) {
                $data = $form->getData();
                $customer = new CustomerModel();

                try {
        			$event->getDispatcher()->dispatch(TheliaEvents::BEFORE_CREATECUSTOMER, $event);

                	$customer->createOrUpdate(
                        $data["title"],
                        $data["firstname"],
                        $data["lastname"],
                        $data["address1"],
                        $data["address2"],
                        $data["address3"],
                        $data["phone"],
                        $data["cellphone"],
                        $data["zipcode"],
                        $data["country"],
                        $data["email"],
                        $data["password"],
                        $request->getSession()->getLang()
                    );
                    $customerEvent = new CustomerEvent($customer);
                    $event->getDispatcher()->dispatch(TheliaEvents::AFTER_CREATECUSTOMER, $customerEvent);

                	// Connect the newly created user,and redirect to the success URL
                	$this->processSuccessfulLogin($event, $customer, $customerCreationForm, true);

                } catch (PropelException $e) {
                    Tlog::getInstance()->error(sprintf('error during creating customer on action/createCustomer with message "%s"', $e->getMessage()));

                    $message = "Failed to create your account, please try again.";
                }
            }
            else {
            	$message = "Missing or invalid data";
            }
        }
        else {
        	$message = "Wrong form method !";
        }

        // The form has an error
        $customerCreationForm->setError(true);
        $customerCreationForm->setErrorMessage($message);

        // Store the form in the parser context
        $event->setErrorForm($customerCreationForm);

        // Stop event propagation
        $event->stopPropagation();
    }

    public function modify(ActionEvent $event)
    {
        $request = $event->getRequest();

        $customerModification = new CustomerModification($request);

        $form = $customerModification->getForm();

        if ($request->isMethod("post")) {

            $form->bind($request);

            if ($form->isValid()) {
                $data = $form->getData();

                $customer = CustomerQuery::create()->findPk(1);
                try {
                    $customerEvent = new CustomerEvent($customer);
    				$event->getDispatcher()->dispatch(TheliaEvents::BEFORE_CHANGECUSTOMER, $customerEvent);

                	$data = $form->getData();

                    $customer->createOrUpdate(
                        $data["title"],
                        $data["firstname"],
                        $data["lastname"],
                        $data["address1"],
                        $data["address2"],
                        $data["address3"],
                        $data["phone"],
                        $data["cellphone"],
                        $data["zipcode"],
                        $data["country"]
                    );

                    $customerEvent->customer = $customer;
                    $event->getDispatcher()->dispatch(TheliaEvents::AFTER_CHANGECUSTOMER, $customerEvent);

                    // Update the logged-in user, and redirect to the success URL (exits)
                    // We don-t send the login event, as the customer si already logged.
                    $this->processSuccessfulLogin($event, $customer, $customerModification);
                 }
                catch(PropelException $e) {

                    Tlog::getInstance()->error(sprintf('error during modifying customer on action/modifyCustomer with message "%s"', $e->getMessage()));

                    $message = "Failed to change your account, please try again.";
                }
            }
            else {
            	$message = "Missing or invalid data";
            }
        }
        else {
        	$message = "Wrong form method !";
        }

        // The form has an error
        $customerModification->setError(true);
        $customerModification->setErrorMessage($message);

        // Dispatch the errored form
        $event->setErrorForm($customerModification);
    }


    /**
     * Perform user logout. The user is redirected to the provided view, if any.
     *
     * @param ActionEvent $event
     */
    public function logout(ActionEvent $event)
    {
    	$event->getDispatcher()->dispatch(TheliaEvents::CUSTOMER_LOGOUT, $event);

    	$this->getSecurityContext()->clear();
    }

    /**
     * Perform user login. On a successful login, the user is redirected to the URL
     * found in the success_url form parameter, or / if none was found.
     *
     * If login is not successfull, the same view is dispolyed again.
     *
     * @param ActionEvent $event
     */
    public function login(ActionEvent $event)
    {
    	$request = $event->getRequest();

    	$customerLoginForm = new CustomerLogin($request);

    	$authenticator = new CustomerUsernamePasswordFormAuthenticator($request, $customerLoginForm);

    	try {
    		$user = $authenticator->getAuthentifiedUser();

    		$this->processSuccessfulLogin($event, $user, $customerLoginForm);
     	}
    	catch (ValidatorException $ex) {
    		$message = "Missing or invalid information. Please check your input.";
    	}
        catch (UsernameNotFoundException $ex) {
    		$message = "This email address was not found.";
    	}
    	catch (AuthenticationException $ex) {
    		$message = "Login failed. Please check your username and password.";
    	}
    	catch (\Exception $ex) {
    		$message = sprintf("Unable to process your request. Please try again (%s in %s).", $ex->getMessage(), $ex->getFile());
    	}

    	// The for has an error
    	$customerLoginForm->setError(true);
    	$customerLoginForm->setErrorMessage($message);

    	// Dispatch the errored form
    	$event->setErrorForm($customerLoginForm);

    	// A this point, the same view is displayed again.
    }

    public function changePassword(ActionEvent $event)
    {
		// TODO
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
            "action.createCustomer" => array("create", 128),
            "action.modifyCustomer" => array("modify", 128),
            "action.loginCustomer"  => array("login", 128),
            "action.logoutCustomer" => array("logout", 128),
        );
    }

    /**
     * Stores the current user in the security context, and redirect to the
     * success_url.
     *
     * @param CustomerModel $user the logged user
     */
    protected function processSuccessfulLogin(ActionEvent $event, CustomerModel $user, BaseForm $form, $sendLoginEvent = false)
    {
    	// Success -> store user in security context
    	$this->getSecurityContext()->setUser($user);

    	if ($sendLoginEvent) $event->getDispatcher()->dispatch(TheliaEvents::CUSTOMER_LOGIN, $event);

    	// Redirect to the success URL
    	$this->redirect($form->getSuccessUrl());
    }

    /**
     * Return the security context, beeing sure that we're in the CONTEXT_FRONT_OFFICE context
     *
     * @return SecurityContext the security context
     */
    protected function getSecurityContext() {
    	$this->securityContext->setContext(SecurityContext::CONTEXT_FRONT_OFFICE);

    	return $this->securityContext;
    }
}

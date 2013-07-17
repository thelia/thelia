<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ActionEvent;
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
use Thelia\Tools\Redirect;
use Symfony\Component\Validator\Exception\ValidatorException;
use Thelia\Core\Security\Exception\AuthenticationException;

class Customer implements EventSubscriberInterface
{
	/**
	 * @var Thelia\Core\Security\SecurityContext
	 */
	private $securityContext;

	public function __construct(SecurityContext $context) {
		$this->securityContext = $context;

		$context->setContext(SecurityContext::CONTEXT_FRONT_OFFICE);
	}


    private function getSecurityContext($context) {
    	$this->securityContext->setContext($context === false ? SecurityContext::CONTEXT_FRONT_OFFICE : $context);

    	return $securityContext;
    }

    public function create(ActionEvent $event)
    {

        $event->getDispatcher()->dispatch(TheliaEvents::BEFORE_CREATECUSTOMER, $event);

        $request = $event->getRequest();

        $customerCreation = new CustomerCreation($request);

        $form = $customerCreation->getForm();

        if ($request->isMethod("post")) {

            $form->bind($request);

            if ($form->isValid()) {
                $data = $form->getData();
                $customer = new CustomerModel();
                try {
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
                } catch (\PropelException $e) {
                    Tlog::getInstance()->error(sprintf('error during creating customer on action/createCustomer with message "%s"', $e->getMessage()));
                    $event->setFormError($customerCreation);
                }

                //Customer is create, he is automatically connected

            }
            else {

                $event->setFormError($customerCreation);
            }
        }

        $event->getDispatcher()->dispatch(TheliaEvents::AFTER_CREATECUSTOMER, $event);
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
                } catch(\PropelException $e) {
                    Tlog::getInstance()->error(sprintf('error during modifying customer on action/modifyCustomer with message "%s"', $e->getMessage()));
                    $event->setFormError($customerModification);
                }
            } else {
                $event->setFormError($customerModification);
            }
        }

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

    	$form = new CustomerLogin($request);

    	$authenticator = new CustomerUsernamePasswordFormAuthenticator($request, $form);

    	try {
    		$user = $authenticator->getAuthentifiedUser();

    		// Success -> store user in security context
    		$this->getSecurityContext()->setUser($user);

    		// Log authentication success
    		AdminLog::append("Authentication successufull", $request, $user);

    		// Get the success URL to redirect the user to
    		$successUrl = $form->getForm()->get('success_url')->getData();

    		if (null == $successUrl) $successUrl = ConfigQuery::read('base_url', '/');

    		// Redirect to the success URL
    		return Redirect::exec(URL::absoluteUrl($successUrl));
    	}
    	catch (ValidatorException $ex) {
    		$message = "Missing or invalid information. Please check your input.";
    	}
    	catch (AuthenticationException $ex) {
    		$message = "Login failed. Please check your username and password.";
    	}
    	catch (\Exception $ex) {
    		$message = sprintf("Unable to process your request. Please try again (%s).", $ex->getMessage());
    	}

    	// Store the form name in session (see Form Smarty plugin to find usage of this parameter)
    	$request->getSession()->setErrorFormName($form->getName());

    	// A this point, the same view is displayed again.
    }

    public function changePassword(ActionEvent $event)
    {

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
            "action.loginCustomer"  => array("login", 128)
        );
    }

}

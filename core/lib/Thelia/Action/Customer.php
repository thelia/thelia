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
use Thelia\Core\Event\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\CustomerCreation;
use Thelia\Form\CustomerModification;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Log\Tlog;
use Thelia\Model\CustomerQuery;
use Thelia\Form\CustomerLogin;
use Thelia\Core\Security\Authentication\CustomerUsernamePasswordFormAuthenticator;
use Symfony\Component\Validator\Exception\ValidatorException;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\UsernameNotFoundException;
use Propel\Runtime\Exception\PropelException;

class Customer extends BaseAction implements EventSubscriberInterface
{

    public function create(CustomerCreateOrUpdateEvent $event)
    {

        $customer = new CustomerModel();
        $customer->setDispatcher($this->getDispatcher());

        $this->createOrUpdateCustomer($customer, $event);

    }

    public function modify(CustomerCreateOrUpdateEvent $event)
    {

        $customer = $event->getCustomer();
        $customer->setDispatcher($this->getDispatcher());

        $this->createOrUpdateCustomer($customer, $event);

    }

    private function createOrUpdateCustomer(CustomerModel $customer, CustomerCreateOrUpdateEvent $event)
    {
        $customer->createOrUpdate(
            $event->getTitle(),
            $event->getFirstname(),
            $event->getLastname(),
            $event->getAddress1(),
            $event->getAddress2(),
            $event->getAddress3(),
            $event->getPhone(),
            $event->getCellphone(),
            $event->getZipcode(),
            $event->getCity(),
            $event->getCountry(),
            $event->getEmail(),
            $event->getPassword(),
            $event->getLang(),
            $event->getReseller(),
            $event->getSponsor(),
            $event->getDiscount()
        );

        $event->setCustomer($customer);
    }

    /**
     * Perform user logout. The user is redirected to the provided view, if any.
     *
     * @param ActionEvent $event
     */
    public function logout(ActionEvent $event)
    {
        $event->getDispatcher()->dispatch(TheliaEvents::CUSTOMER_LOGOUT, $event);

          $this->getFrontSecurityContext()->clear();
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

              $event->customer = $user;

          } catch (ValidatorException $ex) {
            $message = "Missing or invalid information. Please check your input.";
          } catch (UsernameNotFoundException $ex) {
            $message = "This email address was not found.";
          } catch (AuthenticationException $ex) {
            $message = "Login failed. Please check your username and password.";
          } catch (\Exception $ex) {
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
}

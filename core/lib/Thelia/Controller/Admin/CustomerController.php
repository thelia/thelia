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

use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\CustomerCreateForm;
use Thelia\Form\CustomerUpdateForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\CustomerQuery;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\Password;

/**
 * Class CustomerController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerController extends BaseAdminController
{
    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CUSTOMER, AccessManager::VIEW)) return $response;
        return $this->render("customers", array("display_customer" => 20));
    }

    public function viewAction($customer_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::CUSTOMER, AccessManager::VIEW)) return $response;
        return $this->render("customer-edit", array(
            "customer_id" => $customer_id
        ));
    }

    /**
     * update customer action
     *
     * @param $customer_id
     * @return mixed|\Symfony\Component\HttpFoundation\Response
     */
    public function updateAction($customer_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::CUSTOMER, AccessManager::UPDATE)) return $response;

        $message = false;

        $customerUpdateForm = new CustomerUpdateForm($this->getRequest());

        try {
            $customer = CustomerQuery::create()->findPk($customer_id);

            if (null === $customer) {
                throw new \InvalidArgumentException(sprintf("%d customer id does not exist", $customer_id));
            }

            $form = $this->validateForm($customerUpdateForm);

            $event = $this->createEventInstance($form->getData());
            $event->setCustomer($customer);

            $this->dispatch(TheliaEvents::CUSTOMER_UPDATEACCOUNT, $event);

            $customerUpdated = $event->getCustomer();

            $this->adminLogAppend(AdminResources::CUSTOMER, AccessManager::UPDATE, sprintf("Customer with Ref %s (ID %d) modified", $customerUpdated->getRef() , $customerUpdated->getId()));

            if ($this->getRequest()->get("save_mode") == "close") {
                $this->redirectToRoute("admin.customers");
            } else {
                $this->redirectSuccess($customerUpdateForm);
            }

        } catch (FormValidationException $e) {
            $message = sprintf("Please check your input: %s", $e->getMessage());
        } catch (PropelException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = sprintf("Sorry, an error occured: %s", $e->getMessage()." ".$e->getFile());
        }

        if ($message !== false) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("Error during customer update process : %s.", $message));

            $customerUpdateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($customerUpdateForm)
                ->setGeneralError($message)
            ;
        }

        return $this->render("customer-edit", array(
            "customer_id" => $customer_id
        ));
    }

    public function createAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CUSTOMER, AccessManager::CREATE)) return $response;

        $message = null;

        $customerCreateForm = new CustomerCreateForm($this->getRequest());

        try {

            $form = $this->validateForm($customerCreateForm);

            $data = $form->getData();
            $data["password"] = Password::generateRandom();

            $event = $this->createEventInstance($form->getData());



            $this->dispatch(TheliaEvents::CUSTOMER_CREATEACCOUNT, $event);

            $successUrl = $customerCreateForm->getSuccessUrl();

            $successUrl = str_replace('_ID_', $event->getCustomer()->getId(), $successUrl);

            $this->redirect($successUrl);


        }catch (FormValidationException $e) {
            $message = sprintf("Please check your input: %s", $e->getMessage());
        } catch (PropelException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = sprintf("Sorry, an error occured: %s", $e->getMessage()." ".$e->getFile());
        }

        if ($message !== false) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("Error during customer creation process : %s.", $message));

            $customerCreateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($customerCreateForm)
                ->setGeneralError($message)
            ;
        }

        return $this->render("customers", array("display_customer" => 20));
    }

    public function deleteAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::CUSTOMER, AccessManager::DELETE)) return $response;

        $message = null;

        try {
            $customer_id = $this->getRequest()->get("customer_id");
            $customer = CustomerQuery::create()->findPk($customer_id);

            if (null === $customer) {
                throw new \InvalidArgumentException(Translator::getInstance("The customer you want to delete does not exist"));
            }

            $event = new CustomerEvent($customer);

            $this->dispatch(TheliaEvents::CUSTOMER_DELETEACCOUNT, $event);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $params = array(
            "customer_page" => $this->getRequest()->get("customer_page", 1)
        );

        if ($message) {
            $params["delete_error_message"] = $message;
        }

        $this->redirectToRoute("admin.customers", $params);

    }

    /**
     * @param $data
     * @return \Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent
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
            $this->getRequest()->getSession()->getLang()->getId(),
            isset($data["reseller"])?$data["reseller"]:null,
            isset($data["sponsor"])?$data["sponsor"]:null,
            isset($data["discount"])?$data["discount"]:null,
            isset($data["company"])?$data["company"]:null
        );

        return $customerCreateEvent;
    }
}

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
use Symfony\Component\Form\Form;
use Thelia\Core\Event\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\CustomerModification;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerController extends BaseAdminController
{
    public function indexAction()
    {
        if (null !== $response = $this->checkAuth("admin.customers.view")) return $response;
        return $this->render("customers", array("display_customer" => 20));
    }

    public function viewAction($customer_id)
    {
        if (null !== $response = $this->checkAuth("admin.customers.view")) return $response;

    	return $this->render("customer-edit", array(
    		"customer_id" => $customer_id
    	));
    }

    public function updateAction($customer_id)
    {
        if (null !== $response = $this->checkAuth("admin.customers.update")) return $response;

        $message = false;

        $customerModification = new CustomerModification($this->getRequest());

        try {
            $customer = CustomerQuery::create()->findPk($customer_id);

            if(null === $customer) {
                throw new \InvalidArgumentException(sprintf("%d customer id does not exists", $customer_id));
            }

            $form = $this->validateForm($customerModification);

            $event = $this->createEventInstance($form->getData());
            $event->setCustomer($customer);

            $this->dispatch(TheliaEvents::CUSTOMER_UPDATEACCOUNT, $event);

            $customerUpdated = $event->getCustomer();

            $this->adminLogAppend(sprintf("Customer with Ref %s (ID %d) modified", $customerUpdated->getRef() , $customerUpdated->getId()));

            if($this->getRequest()->get("save_mode") == "close") {
                $this->redirectToRoute("admin.customers");
            } else {
                $this->redirectSuccess($customerModification);
            }

        } catch (FormValidationException $e) {
            $message = sprintf("Please check your input: %s", $e->getMessage());
        } catch (PropelException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = sprintf("Sorry, an error occured: %s", $e->getMessage()." ".$e->getFile());
        }

        if ($message !== false) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("Error during customer login process : %s.", $message));

            $customerModification->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($customerModification)
                ->setGeneralError($message)
            ;
        }

        return $this->render("customer-edit", array(
            "customer_id" => $customer_id
        ));
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
            $this->getRequest()->getSession()->getLang()->getId(),
            isset($data["reseller"])?$data["reseller"]:null,
            isset($data["sponsor"])?$data["sponsor"]:null,
            isset($data["discount"])?$data["discount"]:null,
            isset($data["company"])?$data["company"]:null
        );

        return $customerCreateEvent;
    }
}
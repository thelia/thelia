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

namespace Front\Controller;

use Front\Front;
use Symfony\Component\Form\Form;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\Address\AddressEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;

/**
 * Class AddressController
 * @package Thelia\Controller\Front
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AddressController extends BaseFrontController
{

    /**
     * Controller for generate modal containing update form
     * Check if request is a XmlHttpRequest and address owner is the current customer
     *
     * @param $address_id
     */
    public function generateModalAction($address_id)
    {

        $this->checkAuth();
        $this->checkXmlHttpRequest();

    }

    /**
     * Create controller.
     * Check if customer is logged in
     *
     * Dispatch TheliaEvents::ADDRESS_CREATE event
     */
    public function createAction()
    {
        $this->checkAuth();

        $addressCreate = $this->createForm(FrontForm::ADDRESS_CREATE);

        try {
            /** @var Customer $customer */
            $customer = $this->getSecurityContext()->getCustomerUser();

            $form = $this->validateForm($addressCreate, "post");
            $event = $this->createAddressEvent($form);
            $event->setCustomer($customer);

            $this->dispatch(TheliaEvents::ADDRESS_CREATE, $event);

            return $this->generateSuccessRedirect($addressCreate);
        } catch (FormValidationException $e) {
            $message = $this->getTranslator()->trans("Please check your input: %s", ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans("Sorry, an error occured: %s", ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        }

        Tlog::getInstance()->error(sprintf("Error during address creation process : %s", $message));

        $addressCreate->setErrorMessage($message);

        $this->getParserContext()
            ->addForm($addressCreate)
            ->setGeneralError($message)
        ;

        // Redirect to error URL if defined
        if ($addressCreate->hasErrorUrl()) {
            return $this->generateErrorRedirect($addressCreate);
        }
    }

    protected function createAddressEvent(Form $form)
    {
        return new AddressCreateOrUpdateEvent(
            $form->get("label")->getData(),
            $form->get("title")->getData(),
            $form->get("firstname")->getData(),
            $form->get("lastname")->getData(),
            $form->get("address1")->getData(),
            $form->get("address2")->getData(),
            $form->get("address3")->getData(),
            $form->get("zipcode")->getData(),
            $form->get("city")->getData(),
            $form->get("country")->getData(),
            $form->get("cellphone")->getData(),
            $form->get("phone")->getData(),
            $form->get("company")->getData(),
            $form->get("is_default")->getData(),
            $form->get("state")->getData()
        );
    }

    public function updateViewAction($address_id)
    {
        $this->checkAuth();

        $customer = $this->getSecurityContext()->getCustomerUser();
        $address = AddressQuery::create()->findPk($address_id);

        if (!$address || $customer->getId() != $address->getCustomerId()) {
            return $this->generateRedirectFromRoute('default');
        }

        $this->getParserContext()->set("address_id", $address_id);
    }

    public function processUpdateAction($address_id)
    {
        $this->checkAuth();

        $addressUpdate = $this->createForm(FrontForm::ADDRESS_UPDATE);

        try {
            $customer = $this->getSecurityContext()->getCustomerUser();

            $form = $this->validateForm($addressUpdate);

            $address = AddressQuery::create()->findPk($address_id);

            if (null === $address) {
                return $this->generateRedirectFromRoute('default');
            }

            if ($address->getCustomer()->getId() != $customer->getId()) {
                return $this->generateRedirectFromRoute('default');
            }

            $event = $this->createAddressEvent($form);
            $event->setAddress($address);

            $this->dispatch(TheliaEvents::ADDRESS_UPDATE, $event);

            return $this->generateSuccessRedirect($addressUpdate);
        } catch (FormValidationException $e) {
            $message = $this->getTranslator()->trans("Please check your input: %s", ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans("Sorry, an error occured: %s", ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        }

        $this->getParserContext()->set("address_id", $address_id);

        Tlog::getInstance()->error(sprintf("Error during address creation process : %s", $message));

        $addressUpdate->setErrorMessage($message);

        $this->getParserContext()
            ->addForm($addressUpdate)
            ->setGeneralError($message)
        ;

        if ($addressUpdate->hasErrorUrl()) {
            return $this->generateErrorRedirect($addressUpdate);
        }
    }

    public function deleteAction($address_id)
    {
        $this->checkAuth();
        $error_message = false;

        $customer = $this->getSecurityContext()->getCustomerUser();
        $address = AddressQuery::create()->findPk($address_id);

        if (!$address || $customer->getId() != $address->getCustomerId()) {
            // If Ajax Request
            if ($this->getRequest()->isXmlHttpRequest()) {
                return $this->jsonResponse(
                    json_encode(
                        array(
                            "success" => false,
                            "message" => $this->getTranslator()->trans(
                                "Error during address deletion process",
                                [],
                                Front::MESSAGE_DOMAIN
                            )
                        )
                    )
                );
            } else {
                return $this->generateRedirectFromRoute('default');
            }
        }

        try {
            $this->dispatch(TheliaEvents::ADDRESS_DELETE, new AddressEvent($address));
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        Tlog::getInstance()->error(sprintf('Error during address deletion : %s', $error_message));

        // If Ajax Request
        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($error_message) {
                $response = $this->jsonResponse(json_encode(array(
                    "success" => false,
                    "message" => $error_message
                )));
            } else {
                $response = $this->jsonResponse(
                    json_encode([
                        "success" => true,
                        "message" => ""
                    ])
                );
            }

            return $response;

        } else {
            return $this->generateRedirectFromRoute('default', array('view'=>'account'));
        }
    }

    public function makeAddressDefaultAction($addressId)
    {
        $this->checkAuth();

        $address = AddressQuery::create()
            ->filterByCustomerId($this->getSecurityContext()->getCustomerUser()->getId())
            ->findPk($addressId)
        ;

        if (null === $address) {
            $this->pageNotFound();
        }

        try {
            $event = new AddressEvent($address);
            $this->dispatch(TheliaEvents::ADDRESS_DEFAULT, $event);
        } catch (\Exception $e) {
            $this->getParserContext()
                ->setGeneralError($e->getMessage())
            ;

            return $this->render("account");
        }

        return $this->generateRedirectFromRoute('default', array('view'=>'account'));
    }
}

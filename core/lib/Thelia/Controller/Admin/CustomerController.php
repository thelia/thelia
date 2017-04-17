<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Exception\CustomerException;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Tools\Password;

/**
 * Class CustomerController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'customer',
            'lastname',
            'customer_order',
            AdminResources::CUSTOMER,
            TheliaEvents::CUSTOMER_CREATEACCOUNT,
            TheliaEvents::CUSTOMER_UPDATEACCOUNT,
            TheliaEvents::CUSTOMER_DELETEACCOUNT
        );
    }

    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::CUSTOMER_CREATE);
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::CUSTOMER_UPDATE);
    }

    protected function getCreationEvent($formData)
    {
        $event = $this->createEventInstance($formData);

        // Create a secure password
        $event->setPassword(Password::generateRandom(8));

        // We will notify the customer of account creation
        $event->setNotifyCustomerOfAccountCreation(true);

        return $event;
    }

    protected function getUpdateEvent($formData)
    {
        $event = $this->createEventInstance($formData);

        $event->setCustomer($this->getExistingObject());

        // We allow customer email modification
        $event->setEmailUpdateAllowed(true);

        return $event;
    }

    protected function getDeleteEvent()
    {
        return new CustomerEvent($this->getExistingObject());
    }

    protected function eventContainsObject($event)
    {
        return $event->hasCustomer();
    }

    /**
     * @param Customer $object
     * @return \Thelia\Form\BaseForm
     */
    protected function hydrateObjectForm($object)
    {
        // Get default adress of the customer
        $address = $object->getDefaultAddress();

        // Prepare the data that will hydrate the form
        $data = array(
                'id'        => $object->getId(),
                'firstname' => $object->getFirstname(),
                'lastname'  => $object->getLastname(),
                'email'     => $object->getEmail(),
                'title'     => $object->getTitleId(),
                'discount'  => $object->getDiscount(),
                'reseller'  => $object->getReseller(),
        );

        if ($address !== null) {
            $data['company']   = $address->getCompany();
            $data['address1']  = $address->getAddress1();
            $data['address2']  = $address->getAddress2();
            $data['address3']  = $address->getAddress3();
            $data['phone']     = $address->getPhone();
            $data['cellphone'] = $address->getCellphone();
            $data['zipcode']   = $address->getZipcode();
            $data['city']      = $address->getCity();
            $data['country']   = $address->getCountryId();
            $data['state']     = $address->getStateId();
        }

        // A loop is used in the template
        return $this->createForm(AdminForm::CUSTOMER_UPDATE, 'form', $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasCustomer() ? $event->getCustomer() : null;
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
            isset($data["password"]) && ! empty($data["password"]) ? $data["password"]:null,
            $this->getRequest()->getSession()->getLang()->getId(),
            isset($data["reseller"])?$data["reseller"]:null,
            isset($data["sponsor"])?$data["sponsor"]:null,
            isset($data["discount"])?$data["discount"]:null,
            isset($data["company"])?$data["company"]:null,
            null,
            $data["state"]
        );

        return $customerCreateEvent;
    }

    protected function getExistingObject()
    {
        return CustomerQuery::create()->findPk($this->getRequest()->get('customer_id', 0));
    }

    /**
     * @param Customer $object
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getRef() . "(".$object->getLastname()." ".$object->getFirstname().")";
    }

    /**
     * @param Customer $object
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getEditionArguments()
    {
        return array(
                'customer_id' => $this->getRequest()->get('customer_id', 0),
                'page'        => $this->getRequest()->get('page', 1),
                'page_order'  => $this->getRequest()->get('page_order', 1)
        );
    }

    protected function renderListTemplate($currentOrder, $customParams = array())
    {
        return $this->render(
            'customers',
            array_merge(array(
                'customer_order'   => $currentOrder,
                'page'             => $this->getRequest()->get('page', 1)
            ), $customParams)
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.customers',
            [
                'page' => $this->getRequest()->get('page', 1)
            ]
        );
    }

    protected function renderEditionTemplate()
    {
        return $this->render('customer-edit', $this->getEditionArguments());
    }

    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            "admin.customer.update.view",
            $this->getEditionArguments()
        );
    }

    public function deleteAction()
    {
        $errorMsg = "No error.";
        $removalError = false;

        try {
            parent::deleteAction();
        } catch (CustomerException $e) {
            $errorMsg = $e->getMessage();

            $removalError = true;
        }

        return $this->renderListTemplate($this->getCurrentListOrder(), [
            "removal_error" => $removalError,
            "error_message" => $errorMsg
        ]);
    }
}

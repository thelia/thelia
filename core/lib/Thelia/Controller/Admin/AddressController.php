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
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\Address\AddressEvent;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\AddressCreateForm;
use Thelia\Form\AddressUpdateForm;
use Thelia\Model\AddressQuery;
use Thelia\Model\CustomerQuery;

/**
 * Class AddressController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AddressController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'address',
            null,
            null,

            AdminResources::ADDRESS,

            TheliaEvents::ADDRESS_CREATE,
            TheliaEvents::ADDRESS_UPDATE,
            TheliaEvents::ADDRESS_DELETE,
            null,
            null

        );
    }

    public function useAddressAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, AccessManager::UPDATE)) return $response;

        $address_id = $this->getRequest()->request->get('address_id');

        try {
            $address = AddressQuery::create()->findPk($address_id);

            if (null === $address) {
                throw new \InvalidArgumentException(sprintf('%d address does not exists', $address_id));
            }

            $addressEvent = new AddressEvent($address);

            $this->dispatch(TheliaEvents::ADDRESS_DEFAULT, $addressEvent);

            $this->adminLogAppend($this->resourceCode, AccessManager::UPDATE, sprintf("address %d for customer %d set as default address", $address_id, $address->getCustomerId()));
        } catch (\Exception $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("error during address setting as default with message %s", $e->getMessage()));
        }

        $this->redirectToRoute('admin.customer.update.view', array(), array('customer_id' => $address->getCustomerId()));
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return new AddressCreateForm($this->getRequest());
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return new AddressUpdateForm($this->getRequest());
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param \Thelia\Model\Address $object
     */
    protected function hydrateObjectForm($object)
    {
        $data = array(
            "label" => $object->getLabel(),
            "title" => $object->getTitleId(),
            "firstname" => $object->getFirstname(),
            "lastname" => $object->getLastname(),
            "address1" => $object->getAddress1(),
            "address2" => $object->getAddress2(),
            "address3" => $object->getAddress3(),
            "zipcode" => $object->getZipcode(),
            "city" => $object->getCity(),
            "country" => $object->getCountryId(),
            "cellphone" => $object->getCellphone(),
            "phone" => $object->getPhone(),
            "company" => $object->getCompany()
        );

        return new AddressUpdateForm($this->getRequest(), "form", $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getCreationEvent($formData)
    {
        $event = $this->getCreateOrUpdateEvent($formData);

        $customer = CustomerQuery::create()->findPk($this->getRequest()->get("customer_id"));

        $event->setCustomer($customer);

        return $event;
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getUpdateEvent($formData)
    {
        $event =  $this->getCreateOrUpdateEvent($formData);

        $event->setAddress($this->getExistingObject());

        return $event;
    }

    protected function getCreateOrUpdateEvent($formData)
    {
        $event = new AddressCreateOrUpdateEvent(
            $formData["label"],
            $formData["title"],
            $formData["firstname"],
            $formData["lastname"],
            $formData["address1"],
            $formData["address2"],
            $formData["address3"],
            $formData["zipcode"],
            $formData["city"],
            $formData["country"],
            $formData["cellphone"],
            $formData["phone"],
            $formData["company"],
            $formData["is_default"]
        );

        return $event;

    }

    /**
     * Creates the delete event with the provided form data
     */
    protected function getDeleteEvent()
    {
        return new AddressEvent($this->getExistingObject());
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param unknown $event
     */
    protected function eventContainsObject($event)
    {
        return null !== $event->getAddress();
    }

    /**
     * Get the created object from an event.
     *
     * @param unknown $createEvent
     */
    protected function getObjectFromEvent($event)
    {
        return null;
    }

    /**
     * Load an existing object from the database
     */
    protected function getExistingObject()
    {
        return AddressQuery::create()->findPk($this->getRequest()->get('address_id'));
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param unknown $object
     */
    protected function getObjectLabel($object)
    {
        return $object->getLabel();
    }

    /**
     * Returns the object ID from the object
     *
     * @param unknown $object
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param unknown $currentOrder, if any, null otherwise.
     */
    protected function renderListTemplate($currentOrder)
    {
        // TODO: Implement renderListTemplate() method.
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render('ajax/address-update-modal', array(
            "address_id" => $this->getRequest()->get('address_id'),
            "customer_id" => $this->getExistingObject()->getCustomerId()
        ));
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        $address = $this->getExistingObject();
        $this->redirectToRoute('admin.customer.update.view', array(), array('customer_id' => $address->getCustomerId()));
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        // TODO: Implement redirectToListTemplate() method.
    }

    /**
     * Put in this method post object delete processing if required.
     *
     * @param  \Thelia\Core\Event\AddressEvent $deleteEvent the delete event
     * @return Response                        a response, or null to continue normal processing
     */
    protected function performAdditionalDeleteAction($deleteEvent)
    {
        $address = $deleteEvent->getAddress();
        $this->redirectToRoute('admin.customer.update.view', array(), array('customer_id' => $address->getCustomerId()));
    }

    /**
     * Put in this method post object creation processing if required.
     *
     * @param  AddressCreateOrUpdateEvent $createEvent the create event
     * @return Response                   a response, or null to continue normal processing
     */
    protected function performAdditionalCreateAction($createEvent)
    {
        $this->redirectToEditionTemplate();
    }

    protected function performAdditionalUpdateAction($event)
    {
        $this->redirectToEditionTemplate();
    }
}

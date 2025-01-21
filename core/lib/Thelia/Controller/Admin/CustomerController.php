<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Exception\CustomerException;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Tools\Password;
use Thelia\Tools\TokenProvider;

/**
 * Class CustomerController.
 *
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
        if (method_exists($event, 'hasCustomer')) {
            return $event->hasCustomer();
        }

        if (method_exists($event, 'getModel')) {
            return $event->getModel() !== null;
        }

        return false;
    }

    /**
     * @param Customer $object
     *
     * @return \Thelia\Form\BaseForm
     */
    protected function hydrateObjectForm(ParserContext $parserContext, $object)
    {
        // Get default adress of the customer
        $address = $object->getDefaultAddress();

        // Prepare the data that will hydrate the form
        $data = [
            'id'        => $object->getId(),
            'firstname' => $object->getFirstname(),
            'lastname'  => $object->getLastname(),
            'email'     => $object->getEmail(),
            'lang_id'   => $object->getLangId(),
            'discount'  => $object->getDiscount(),
            'reseller'  => $object->getReseller() ? true : false,
        ];

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
        return $this->createForm(AdminForm::CUSTOMER_UPDATE, FormType::class, $data);
    }

    protected function getObjectFromEvent($event)
    {
        if (method_exists($event, 'hasCustomer') && $event->hasCustomer()) {
            return $event->getCustomer();
        }

        if (method_exists($event, 'getModel')) {
            return $event->getModel();
        }

        return null;
    }

    /**
     * @return \Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent
     */
    private function createEventInstance($data)
    {
        // Use current language if it is not defined in the form
        if (empty($data['lang_id'])) {
            $data['lang_id'] = $this->getSession()->getLang()->getId();
        }

        $customerCreateEvent = new CustomerCreateOrUpdateEvent(
            $data['title'] ?? null,
            $data['firstname'],
            $data['lastname'],
            $data['address1'],
            $data['address2'],
            $data['address3'],
            $data['phone'],
            $data['cellphone'] ?? null,
            $data['zipcode'],
            $data['city'],
            $data['country'],
            $data['email'] ?? null,
            isset($data['password']) && !empty($data['password']) ? $data['password'] : null,
            $data['lang_id'],
            $data['reseller'] ?? null,
            $data['sponsor'] ?? null,
            $data['discount'] ?? null,
            $data['company'] ?? null,
            null,
            $data['state']
        );

        return $customerCreateEvent;
    }

    protected function getExistingObject()
    {
        return CustomerQuery::create()->findPk($this->getRequest()->get('customer_id', 0));
    }

    /**
     * @param Customer $object
     *
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getRef() . '(' . $object->getLastname() . ' ' . $object->getFirstname() . ')';
    }

    /**
     * @param Customer $object
     *
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getEditionArguments()
    {
        return [
            'customer_id' => $this->getRequest()->get('customer_id', 0),
            'page'        => $this->getRequest()->get('page', 1),
            'page_order'  => $this->getRequest()->get('page_order', 1),
        ];
    }

    protected function renderListTemplate($currentOrder, $customParams = [])
    {
        return $this->render(
            'customers',
            array_merge([
                'customer_order' => $currentOrder,
                'page'           => $this->getRequest()->get('page', 1),
            ], $customParams)
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.customers',
            [
                'page' => $this->getRequest()->get('page', 1),
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
            'admin.customer.update.view',
            $this->getEditionArguments()
        );
    }

    public function deleteAction(
        Request $request,
        TokenProvider $tokenProvider,
        EventDispatcherInterface $eventDispatcher,
        ParserContext $parserContext
    ) {
        $errorMsg     = 'No error.';
        $removalError = false;

        try {
            parent::deleteAction(
                $request,
                $tokenProvider,
                $eventDispatcher,
                $parserContext
            );
        } catch (CustomerException $e) {
            $errorMsg = $e->getMessage();

            $removalError = true;
        }

        return $this->renderListTemplate($this->getCurrentListOrder(), [
            'removal_error' => $removalError,
            'error_message' => $errorMsg,
        ]);
    }
}

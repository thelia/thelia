<?php

declare(strict_types=1);

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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Event\ActiveRecordEvent;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Domain\Customer\Exception\CustomerException;
use Thelia\Form\BaseForm;
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
            TheliaEvents::CUSTOMER_DELETEACCOUNT,
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::CUSTOMER_CREATE);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::CUSTOMER_UPDATE);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $event = $this->createEventInstance($formData);

        // Create a secure password
        $event->setPassword(Password::generateRandom());

        // We will notify the customer of account creation
        $event->setNotifyCustomerOfAccountCreation(true);

        return $event;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $event = $this->createEventInstance($formData);

        $event->setCustomer($this->getExistingObject());

        // We allow customer email modification
        $event->setEmailUpdateAllowed(true);

        return $event;
    }

    protected function getDeleteEvent(): ActiveRecordEvent
    {
        return new CustomerEvent($this->getExistingObject());
    }

    protected function eventContainsObject($event): bool
    {
        if (method_exists($event, 'hasCustomer')) {
            return $event->hasCustomer();
        }

        if (method_exists($event, 'getModel')) {
            return null !== $event->getModel();
        }

        return false;
    }

    /**
     * @param Customer $object
     */
    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        // Get default adress of the customer
        $address = $object->getDefaultAddress();

        // Prepare the data that will hydrate the form
        $data = [
            'id' => $object->getId(),
            'firstname' => $object->getFirstname(),
            'lastname' => $object->getLastname(),
            'email' => $object->getEmail(),
            'lang_id' => $object->getLangId(),
            'discount' => $object->getDiscount(),
            'reseller' => (bool) $object->getReseller(),
        ];

        if (null !== $address) {
            $data['company'] = $address->getCompany();
            $data['address1'] = $address->getAddress1();
            $data['address2'] = $address->getAddress2();
            $data['address3'] = $address->getAddress3();
            $data['phone'] = $address->getPhone();
            $data['cellphone'] = $address->getCellphone();
            $data['zipcode'] = $address->getZipcode();
            $data['city'] = $address->getCity();
            $data['country'] = $address->getCountryId();
            $data['state'] = $address->getStateId();
        }

        // A loop is used in the template
        return $this->createForm(AdminForm::CUSTOMER_UPDATE, FormType::class, $data);
    }

    protected function getObjectFromEvent($event): mixed
    {
        if (method_exists($event, 'hasCustomer') && $event->hasCustomer()) {
            return $event->getCustomer();
        }

        if (method_exists($event, 'getModel')) {
            return $event->getModel();
        }

        return null;
    }

    private function createEventInstance(array $data): CustomerCreateOrUpdateEvent
    {
        // Use current language if it is not defined in the form
        if (empty($data['lang_id'])) {
            $data['lang_id'] = $this->getSession()->getLang()?->getId();
        }

        return new CustomerCreateOrUpdateEvent(
            $data['title'] ? (int) $data['title'] : null,
            $data['firstname'],
            $data['lastname'],
            $data['address1'],
            $data['address2'] ?? '',
            $data['address3'] ?? '',
            $data['phone'],
            isset($data['cellphone']) ? (string) $data['cellphone'] : null,
            $data['zipcode'],
            $data['city'],
            isset($data['country']) ? (string) $data['country'] : null,
            $data['email'] ?? null,
            empty($data['password']) ? null : $data['password'],
            $data['lang_id'],
            $data['reseller'] ?? false,
            $data['sponsor'] ?? null,
            isset($data['discount']) ? (float) $data['discount'] : null,
            $data['company'] ?? null,
            null,
            isset($data['state']) ? (int) $data['state'] : null,
        );
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        return CustomerQuery::create()->findPk($this->getRequest()->get('customer_id', 0));
    }

    protected function getObjectLabel(ActiveRecordInterface $object): string
    {
        return $object->getRef().'('.$object->getLastname().' '.$object->getFirstname().')';
    }

    /**
     * @param Customer $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function getEditionArguments(): array
    {
        return [
            'customer_id' => $this->getRequest()->get('customer_id', 0),
            'page' => $this->getRequest()->get('page', 1),
            'page_order' => $this->getRequest()->get('page_order', 1),
        ];
    }

    protected function renderListTemplate(string $currentOrder): Response
    {
        return $this->render(
            'customers',
            ['customer_order' => $currentOrder, 'page' => $this->getRequest()->get('page', 1)],
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.customers',
            [
                'page' => $this->getRequest()->get('page', 1),
            ],
        );
    }

    protected function renderEditionTemplate(): Response
    {
        return $this->render('customer-edit', $this->getEditionArguments());
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.customer.update.view',
            $this->getEditionArguments(),
        );
    }

    public function deleteAction(
        Request $request,
        TokenProvider $tokenProvider,
        EventDispatcherInterface $eventDispatcher,
        ParserContext $parserContext,
    ): Response {
        $errorMsg = 'No error.';
        $removalError = false;

        try {
            parent::deleteAction(
                $request,
                $tokenProvider,
                $eventDispatcher,
                $parserContext,
            );
        } catch (CustomerException $customerException) {
            $errorMsg = $customerException->getMessage();

            $removalError = true;
        }

        return $this->renderListTemplate($this->getCurrentListOrder());
    }
}

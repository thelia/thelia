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
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\AddressCreateForm;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Log\Tlog;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\AddressEvent;

/**
 * Class AddressController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AddressController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'registration_date',
            null,
            null,
            AdminResources::ADDRESS,
            TheliaEvents::ADDRESS_CREATE,
            TheliaEvents::ADDRESS_UPDATE,
            TheliaEvents::ADDRESS_DELETE,
        );
    }

    public function useAddressAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $address_id = $this->getRequest()->request->get('address_id');

        try {
            $address = AddressQuery::create()->findPk($address_id);

            if (null === $address) {
                throw new \InvalidArgumentException(\sprintf('%d address does not exists', $address_id));
            }

            $addressEvent = new AddressEvent($address);

            $eventDispatcher->dispatch($addressEvent, TheliaEvents::ADDRESS_DEFAULT);

            $this->adminLogAppend(
                $this->resourceCode,
                AccessManager::UPDATE,
                \sprintf(
                    'address %d for customer %d set as default address',
                    $address_id,
                    $address->getCustomerId(),
                ),
                $address_id,
            );
        } catch (\Exception $exception) {
            Tlog::getInstance()->error(\sprintf(
                'error during address setting as default with message %s',
                $exception->getMessage(),
            ));
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * Return the creation form for this object.
     */
    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AddressCreateForm::class);
    }

    /**
     * Return the update form for this object.
     */
    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::ADDRESS_UPDATE);
    }

    /**
     * Fills in the form data array.
     */
    protected function createFormDataArray(Address $object): array
    {
        return [
            'label' => $object->getLabel(),
            'firstname' => $object->getFirstname(),
            'lastname' => $object->getLastname(),
            'address1' => $object->getAddress1(),
            'address2' => $object->getAddress2(),
            'address3' => $object->getAddress3(),
            'zipcode' => $object->getZipcode(),
            'city' => $object->getCity(),
            'country' => $object->getCountryId(),
            'state' => $object->getStateId(),
            'cellphone' => $object->getCellphone(),
            'phone' => $object->getPhone(),
            'company' => $object->getCompany(),
        ];
    }

    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        return $this->createForm(AdminForm::ADDRESS_UPDATE, FormType::class, $this->createFormDataArray($object));
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $event = $this->getCreateOrUpdateEvent($formData);

        $customer = CustomerQuery::create()->findPk($this->getRequest()->get('customer_id'));

        $event->setCustomer($customer);

        return $event;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $event = $this->getCreateOrUpdateEvent($formData);

        $event->setAddress($this->getExistingObject());

        return $event;
    }

    protected function getCreateOrUpdateEvent($formData): AddressCreateOrUpdateEvent
    {
        return new AddressCreateOrUpdateEvent(
            $formData['label'],
            $formData['title'] ?? null,
            $formData['firstname'],
            $formData['lastname'],
            $formData['address1'],
            $formData['address2'],
            $formData['address3'],
            $formData['zipcode'],
            $formData['city'],
            $formData['country'],
            $formData['cellphone'] ? (string) $formData['cellphone'] : null,
            $formData['phone'],
            $formData['company'],
            $formData['is_default'],
            $formData['state'],
        );
    }

    protected function getDeleteEvent(): ActionEvent
    {
        $address = $this->getExistingObject();

        return new AddressCreateOrUpdateEvent(
            $address->getLabel(),
            $address->getTitleId(),
            $address->getFirstname(),
            $address->getLastname(),
            $address->getAddress1(),
            $address->getAddress2(),
            $address->getAddress3(),
            $address->getZipcode(),
            $address->getCity(),
            $address->getCountryId(),
            $address->getCellphone(),
            $address->getPhone(),
            $address->getCompany(),
            false, // is_default is not used for delete
            $address->getStateId(),
        );
    }

    protected function eventContainsObject(Event $event): bool
    {
        return null !== $event->getAddress();
    }

    protected function getObjectFromEvent($event): null
    {
        return null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        return AddressQuery::create()->findPk($this->getRequest()->get('address_id'));
    }

    protected function getObjectLabel($object): string
    {
        return $object->getLabel();
    }

    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function renderListTemplate(string $currentOrder): Response
    {
        // We render here the customer edit template.
        return $this->renderEditionTemplate();
    }

    protected function renderEditionTemplate(): Response
    {
        return $this->render('customer-edit', [
            'address_id' => $this->getRequest()->get('address_id'),
            'page' => $this->getRequest()->get('page'),
            'customer_id' => $this->getCustomerId(),
        ]);
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        // We display here the custromer edition template
        return $this->generateRedirectFromRoute(
            'admin.customer.update.view',
            [
                'page' => $this->getRequest()->get('page'),
                'customer_id' => $this->getCustomerId(),
            ],
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.home',
            [
                'page' => $this->getRequest()->get('page'),
                'customer_id' => $this->getCustomerId(),
            ],
        );
    }

    protected function performAdditionalDeleteAction(ActionEvent|ActiveRecordEvent|null $deleteEvent): Response
    {
        return $this->redirectToEditionTemplate();
    }

    protected function performAdditionalCreateAction(ActionEvent|ActiveRecordEvent|null $createEvent): ?Response
    {
        return $this->redirectToEditionTemplate();
    }

    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, ActionEvent|ActiveRecordEvent|null $updateEvent): Response
    {
        return $this->redirectToEditionTemplate();
    }

    protected function getCustomerId()
    {
        if (($address = $this->getExistingObject()) instanceof ActiveRecordInterface) {
            return $address->getCustomerId();
        }

        return $this->getRequest()->get('customer_id', 0);
    }
}

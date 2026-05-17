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
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;

class AdministratorController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'administrator',
            'manual',
            'order',
            AdminResources::ADMINISTRATOR,
            TheliaEvents::ADMINISTRATOR_CREATE,
            TheliaEvents::ADMINISTRATOR_UPDATE,
            TheliaEvents::ADMINISTRATOR_DELETE,
        );
    }

    public function viewAction(): Response
    {
        // Open the update dialog for the current administrator
        return $this->render('administrators', ['show_update_dialog' => true]);
    }

    public function setEmailAction(): Response
    {
        // Open the update dialog for the current administrator, and display the "set email address" notice.
        return $this->render(
            'administrators',
            [
                'show_update_dialog' => true,
                'show_email_change_notice' => true,
            ],
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::ADMINISTRATOR_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::ADMINISTRATOR_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $event = new AdministratorEvent();

        $event->setLogin($formData['login']);
        $event->setFirstname($formData['firstname']);
        $event->setLastname($formData['lastname']);
        $event->setPassword($formData['password']);
        $event->setProfile($formData['profile'] ?: null);
        $event->setLocale($formData['locale']);
        $event->setEmail($formData['email']);

        return $event;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $event = new AdministratorEvent();

        $event->setId($formData['id']);
        $event->setLogin($formData['login']);
        $event->setFirstname($formData['firstname']);
        $event->setLastname($formData['lastname']);
        $event->setPassword($formData['password']);
        $event->setProfile($formData['profile'] ?: null);
        $event->setLocale($formData['locale']);
        $event->setEmail($formData['email']);

        return $event;
    }

    protected function getDeleteEvent(): AdministratorEvent
    {
        $event = new AdministratorEvent();

        $event->setId(
            $this->getRequest()->get('administrator_id', 0),
        );

        return $event;
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasAdministrator();
    }

    /**
     * @param Admin $object
     */
    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
            'firstname' => $object->getFirstname(),
            'lastname' => $object->getLastname(),
            'login' => $object->getLogin(),
            'profile' => $object->getProfileId(),
            'locale' => $object->getLocale(),
            'email' => $object->getEmail(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::ADMINISTRATOR_MODIFICATION, FormType::class, $data);
    }

    protected function getObjectFromEvent($event): ?Admin
    {
        return $event->hasAdministrator() ? $event->getAdministrator() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        return AdminQuery::create()
            ->findOneById($this->getRequest()->get('administrator_id'));
    }

    protected function getObjectLabel(ActiveRecordInterface $object): string
    {
        if ($object instanceof Admin) {
            return $object->getLogin();
        }

        return (string) $object;
    }

    protected function getObjectId(ActiveRecordInterface $object): int
    {
        if ($object instanceof Admin) {
            return $object->getId();
        }

        return (int) $object;
    }

    protected function renderListTemplate(string $currentOrder): Response
    {
        // We always return to the feature edition form
        return $this->render(
            'administrators',
        );
    }

    protected function renderEditionTemplate(): Response
    {
        // We always return to the feature edition form
        return $this->render('administrators');
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        // We always return to the feature edition form
        return $this->redirectToListTemplate();
    }

    protected function performAdditionalCreateAction(ActionEvent|ActiveRecordEvent|null $createEvent): ?Response
    {
        // We always return to the feature edition form
        return $this->redirectToListTemplate();
    }

    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, ActionEvent|ActiveRecordEvent|null $updateEvent): ?Response
    {
        // We always return to the feature edition form
        return $this->redirectToListTemplate();
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.administrators.view',
        );
    }
}

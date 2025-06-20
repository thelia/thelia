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

use Thelia\Core\Event\Folder\FolderEvent;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Folder\FolderCreateEvent;
use Thelia\Core\Event\Folder\FolderDeleteEvent;
use Thelia\Core\Event\Folder\FolderToggleVisibilityEvent;
use Thelia\Core\Event\Folder\FolderUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;

/**
 * Class FolderController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class FolderController extends AbstractSeoCrudController
{
    public function __construct()
    {
        parent::__construct(
            'folder',
            'manual',
            'folder_order',
            AdminResources::FOLDER,
            TheliaEvents::FOLDER_CREATE,
            TheliaEvents::FOLDER_UPDATE,
            TheliaEvents::FOLDER_DELETE,
            TheliaEvents::FOLDER_TOGGLE_VISIBILITY,
            TheliaEvents::FOLDER_UPDATE_POSITION,
            TheliaEvents::FOLDER_UPDATE_SEO
        );
    }

    /**
     * Return the creation form for this object.
     */
    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::FOLDER_CREATION);
    }

    /**
     * Return the update form for this object.
     */
    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::FOLDER_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template.
     *
     * @param Folder $object
     */
    protected function hydrateObjectForm(ParserContext $parserContext, $object): BaseForm
    {
        // Hydrate the "SEO" tab form
        $this->hydrateSeoForm($parserContext, $object);

        // Prepare the data that will hydrate the form
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
            'visible' => $object->getVisible(),
            'parent' => $object->getParent(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::FOLDER_MODIFICATION, FormType::class, $data);
    }

    /**
     * Creates the creation event with the provided form data.
     *
     * @param array $formData
     */
    protected function getCreationEvent($formData): FolderCreateEvent
    {
        $creationEvent = new FolderCreateEvent();

        $creationEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setVisible($formData['visible'])
            ->setParent($formData['parent']);

        return $creationEvent;
    }

    /**
     * Creates the update event with the provided form data.
     *
     * @param array $formData
     */
    protected function getUpdateEvent($formData): FolderUpdateEvent
    {
        $updateEvent = new FolderUpdateEvent($formData['id']);

        $updateEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setVisible($formData['visible'])
            ->setParent($formData['parent'])
        ;

        return $updateEvent;
    }

    /**
     * Creates the delete event with the provided form data.
     */
    protected function getDeleteEvent(): FolderDeleteEvent
    {
        return new FolderDeleteEvent($this->getRequest()->get('folder_id'));
    }

    /**
     * @return FolderToggleVisibilityEvent|void
     */
    protected function createToggleVisibilityEvent(): FolderToggleVisibilityEvent
    {
        return new FolderToggleVisibilityEvent($this->getExistingObject());
    }

    /**
     * @return UpdatePositionEvent|void
     */
    protected function createUpdatePositionEvent($positionChangeMode, $positionValue): UpdatePositionEvent
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('folder_id', null),
            $positionChangeMode,
            $positionValue
        );
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param FolderEvent $event
     */
    protected function eventContainsObject($event): bool
    {
        return $event->hasFolder();
    }

    /**
     * Get the created object from an event.
     *
     * @param $event \Thelia\Core\Event\Folder\FolderEvent $event
     *
     * @return Folder|null
     */
    protected function getObjectFromEvent($event)
    {
        return $event->hasFolder() ? $event->getFolder() : null;
    }

    /**
     * Load an existing object from the database.
     */
    protected function getExistingObject()
    {
        $folder = FolderQuery::create()
            ->findOneById($this->getRequest()->get('folder_id', 0));

        if (null !== $folder) {
            $folder->setLocale($this->getCurrentEditionLocale());
        }

        return $folder;
    }

    /**
     * Returns the object label form the object event (name, title, etc.).
     *
     * @param Folder $object
     *
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object.
     *
     * @param Folder $object
     *
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template.
     *
     * @return Response
     */
    protected function renderListTemplate($currentOrder)
    {
        // Get content order
        $content_order = $this->getListOrderFromSession('content', 'content_order', 'manual');

        return $this->render(
            'folders',
            [
                'folder_order' => $currentOrder,
                'content_order' => $content_order,
                'parent' => $this->getRequest()->get('parent', 0),
            ]
        );
    }

    /**
     * Render the edition template.
     */
    protected function renderEditionTemplate()
    {
        return $this->render('folder-edit', $this->getEditionArguments());
    }

    protected function getEditionArguments(Request $request = null): array
    {
        if (!$request instanceof Request) {
            $request = $this->getRequest();
        }

        return [
            'folder_id' => $request->get('folder_id', 0),
            'current_tab' => $request->get('current_tab', 'general'),
        ];
    }

    /**
     * @param FolderUpdateEvent $updateEvent
     *
     * @return Response|void
     */
    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, $updateEvent)
    {
        if ($this->getRequest()->get('save_mode') != 'stay') {
            return $this->generateRedirectFromRoute(
                'admin.folders.default',
                ['parent' => $updateEvent->getFolder()->getParent()]
            );
        }

        return null;
    }

    /**
     * Put in this method post object delete processing if required.
     *
     * @param FolderDeleteEvent $deleteEvent the delete event
     *
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalDeleteAction($deleteEvent)
    {
        return $this->generateRedirectFromRoute(
            'admin.folders.default',
            ['parent' => $deleteEvent->getFolder()->getParent()]
        );
    }

    /**
     * @param $event \Thelia\Core\Event\UpdatePositionEvent
     *
     * @return Response|null
     */
    protected function performAdditionalUpdatePositionAction($event)
    {
        $folder = FolderQuery::create()->findPk($event->getObjectId());

        if ($folder != null) {
            return $this->generateRedirectFromRoute(
                'admin.folders.default',
                ['parent' => $folder->getParent()]
            );
        }

        return null;
    }

    /**
     * Redirect to the edition template.
     *
     * @return Response
     */
    protected function redirectToEditionTemplate(Request $request = null)
    {
        return $this->generateRedirectFromRoute('admin.folders.update', [], $this->getEditionArguments($request));
    }

    /**
     * Redirect to the list template.
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.folders.default',
            ['parent' => $this->getRequest()->get('parent', 0)]
        );
    }
}

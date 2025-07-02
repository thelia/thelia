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


use Exception;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\ContentModificationForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;

/**
 * Class ContentController.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class ContentController extends AbstractSeoCrudController
{
    public function __construct()
    {
        parent::__construct(
            'content',
            'manual',
            'content_order',
            AdminResources::CONTENT,
            TheliaEvents::CONTENT_CREATE,
            TheliaEvents::CONTENT_UPDATE,
            TheliaEvents::CONTENT_DELETE,
            TheliaEvents::CONTENT_TOGGLE_VISIBILITY,
            TheliaEvents::CONTENT_UPDATE_POSITION,
            TheliaEvents::CONTENT_UPDATE_SEO
        );
    }

    /**
     * controller adding content to additional folder.
     *
     * @return mixed|Response
     */
    public function addAdditionalFolderAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $folder_id = (int) $this->getRequest()->request->get('additional_folder_id');

        if ($folder_id > 0) {
            $event = new ContentAddFolderEvent(
                $this->getExistingObject(),
                $folder_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::CONTENT_ADD_FOLDER);
            } catch (Exception $e) {
                return $this->errorPage($e);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * controller removing additional folder to a content.
     *
     * @return mixed|Response
     */
    public function removeAdditionalFolderAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $folder_id = (int) $this->getRequest()->request->get('additional_folder_id');

        if ($folder_id > 0) {
            $event = new ContentRemoveFolderEvent(
                $this->getExistingObject(),
                $folder_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::CONTENT_REMOVE_FOLDER);
            } catch (Exception $e) {
                return $this->errorPage($e);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * Return the creation form for this object.
     */
    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::CONTENT_CREATION);
    }

    /**
     * Return the update form for this object.
     */
    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::CONTENT_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template.
     *
     * @param Content $object
     *
     * @return ContentModificationForm
     */
    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
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
        ];

        // Setup the object form
        return $this->createForm(AdminForm::CONTENT_MODIFICATION, FormType::class, $data);
    }

    /**
     * Creates the creation event with the provided form data.
     */
    protected function getCreationEvent(array $formData): ActionEvent
    {
        $contentCreateEvent = new ContentCreateEvent();

        $contentCreateEvent
            ->setLocale($formData['locale'])
            ->setDefaultFolder($formData['default_folder'])
            ->setTitle($formData['title'])
            ->setVisible($formData['visible'])
        ;

        return $contentCreateEvent;
    }

    /**
     * Creates the update event with the provided form data.
     */
    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $contentUpdateEvent = new ContentUpdateEvent($formData['id']);

        $contentUpdateEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setVisible($formData['visible'])
            ->setDefaultFolder($formData['default_folder']);

        return $contentUpdateEvent;
    }

    /**
     * Creates the delete event with the provided form data.
     */
    protected function getDeleteEvent(): ContentDeleteEvent
    {
        return new ContentDeleteEvent($this->getRequest()->get('content_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param ContentEvent $event
     */
    protected function eventContainsObject($event): bool
    {
        return $event->hasContent();
    }

    /**
     * Get the created object from an event.
     *
     * @param $event \Thelia\Core\Event\Content\ContentEvent
     *
     * @return Content|null
     */
    protected function getObjectFromEvent($event): mixed
    {
        return $event->getContent();
    }

    /**
     * Load an existing object from the database.
     *
     * @return Content
     */
    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $content = ContentQuery::create()
            ->findOneById($this->getRequest()->get('content_id', 0));

        if (null !== $content) {
            $content->setLocale($this->getCurrentEditionLocale());
        }

        return $content;
    }

    /**
     * Returns the object label form the object event (name, title, etc.).
     *
     * @param Content $object
     *
     * @return string content title
     */
    protected function getObjectLabel(activeRecordInterface $object): ?string    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object.
     *
     * @param Content $object
     *
     * @return int content id
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function getFolderId()
    {
        $folderId = $this->getRequest()->get('folder_id');

        if (null === $folderId) {
            $content = $this->getExistingObject();

            if ($content instanceof ActiveRecordInterface) {
                $folderId = $content->getDefaultFolderId();
            }
        }

        return $folderId ?: 0;
    }

    /**
     * Render the main list template.
     *
     * @param int $currentOrder , if any, null otherwise
     */
    protected function renderListTemplate($currentOrder): Response
    {
        $this->getListOrderFromSession('content', 'content_order', 'manual');

        return $this->render(
            'folders',
            [
                'content_order' => $currentOrder,
                'parent' => $this->getFolderId(),
            ]
        );
    }

    protected function getEditionArguments(): array
    {
        return [
            'content_id' => $this->getRequest()->get('content_id', 0),
            'current_tab' => $this->getRequest()->get('current_tab', 'general'),
            'folder_id' => $this->getFolderId(),
        ];
    }

    /**
     * Render the edition template.
     */
    protected function renderEditionTemplate(): Response
    {
        return $this->render('content-edit', $this->getEditionArguments());
    }

    /**
     * Redirect to the edition template.
     */
    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.content.update',
            [],
            $this->getEditionArguments()
        );
    }

    /**
     * Redirect to the list template.
     */
    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.content.default',
            ['parent' => $this->getFolderId()]
        );
    }

    /**
     * @return Response|void
     */
    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, ActionEvent $updateEvent): ?\Symfony\Component\HttpFoundation\Response
    {
        if ($this->getRequest()->get('save_mode') != 'stay') {
            return $this->generateRedirectFromRoute(
                'admin.folders.default',
                ['parent' => $this->getFolderId()]
            );
        }

        return null;
    }

    /**
     * Put in this method post object delete processing if required.
     *
     * @param ActionEvent $deleteEvent the delete event
     *
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalDeleteAction(ActionEvent $deleteEvent): ?\Symfony\Component\HttpFoundation\Response
    {
        return $this->generateRedirectFromRoute(
            'admin.folders.default',
            ['parent' => $deleteEvent->getDefaultFolderId()]
        );
    }

    /**
     * @param $positionChangeEvent ActionEvent
     *
     * @return Response|null
     */
    protected function performAdditionalUpdatePositionAction(ActionEvent $positionChangeEvent): ?\Symfony\Component\HttpFoundation\Response
    {
        if (null !== $content = ContentQuery::create()->findPk($positionChangeEvent->getObjectId())) {
            // Redirect to parent category list
            return $this->generateRedirectFromRoute(
                'admin.folders.default',
                ['parent' => $positionChangeEvent->getReferrerId()]
            );
        }

        return null;
    }

    /**
     * @return UpdatePositionEvent|void
     */
    protected function createUpdatePositionEvent($positionChangeMode, $positionValue): UpdatePositionEvent
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('content_id'),
            $positionChangeMode,
            $positionValue,
            $this->getRequest()->get('folder_id')
        );
    }

    /**
     * @return ContentToggleVisibilityEvent|void
     */
    protected function createToggleVisibilityEvent(): ContentToggleVisibilityEvent
    {
        return new ContentToggleVisibilityEvent($this->getExistingObject());
    }
}

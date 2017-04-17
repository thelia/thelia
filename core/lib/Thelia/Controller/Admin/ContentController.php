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

use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;

/**
 * Class ContentController
 * @package Thelia\Controller\Admin
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
     * controller adding content to additional folder
     *
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function addAdditionalFolderAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $folder_id = intval($this->getRequest()->request->get('additional_folder_id'));

        if ($folder_id > 0) {
            $event = new ContentAddFolderEvent(
                $this->getExistingObject(),
                $folder_id
            );

            try {
                $this->dispatch(TheliaEvents::CONTENT_ADD_FOLDER, $event);
            } catch (\Exception $e) {
                return $this->errorPage($e);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * controller removing additional folder to a content
     *
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function removeAdditionalFolderAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $folder_id = intval($this->getRequest()->request->get('additional_folder_id'));

        if ($folder_id > 0) {
            $event = new ContentRemoveFolderEvent(
                $this->getExistingObject(),
                $folder_id
            );

            try {
                $this->dispatch(TheliaEvents::CONTENT_REMOVE_FOLDER, $event);
            } catch (\Exception $e) {
                return $this->errorPage($e);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::CONTENT_CREATION);
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::CONTENT_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param  Content                              $object
     * @return \Thelia\Form\ContentModificationForm
     */
    protected function hydrateObjectForm($object)
    {
        // Hydrate the "SEO" tab form
        $this->hydrateSeoForm($object);

        // Prepare the data that will hydrate the form
        $data = array(
            'id'           => $object->getId(),
            'locale'       => $object->getLocale(),
            'title'        => $object->getTitle(),
            'chapo'        => $object->getChapo(),
            'description'  => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
            'visible'      => $object->getVisible()
        );

        // Setup the object form
        return $this->createForm(AdminForm::CONTENT_MODIFICATION, "form", $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param  array                                         $formData
     * @return \Thelia\Core\Event\Content\ContentCreateEvent
     */
    protected function getCreationEvent($formData)
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
     * Creates the update event with the provided form data
     *
     * @param  array                                         $formData
     * @return \Thelia\Core\Event\Content\ContentUpdateEvent
     */
    protected function getUpdateEvent($formData)
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
     * Creates the delete event with the provided form data
     */
    protected function getDeleteEvent()
    {
        return new ContentDeleteEvent($this->getRequest()->get('content_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param  \Thelia\Core\Event\Content\ContentEvent $event
     * @return bool
     */
    protected function eventContainsObject($event)
    {
        return $event->hasContent();
    }

    /**
     * Get the created object from an event.
     *
     * @param $event \Thelia\Core\Event\Content\ContentEvent
     *
     * @return null|\Thelia\Model\Content
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getContent();
    }

    /**
     * Load an existing object from the database
     *
     * @return \Thelia\Model\Content
     */
    protected function getExistingObject()
    {
        $content = ContentQuery::create()
            ->findOneById($this->getRequest()->get('content_id', 0));

        if (null !== $content) {
            $content->setLocale($this->getCurrentEditionLocale());
        }

        return $content;
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param Content $object
     *
     * @return string content title
     *
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object
     *
     * @param Content $object
     *
     * @return int content id
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getFolderId()
    {
        $folderId = $this->getRequest()->get('folder_id', null);

        if (null === $folderId) {
            $content = $this->getExistingObject();

            if ($content) {
                $folderId = $content->getDefaultFolderId();
            }
        }

        return $folderId ?: 0;
    }

    /**
     * Render the main list template
     *
     * @param  int                                  $currentOrder , if any, null otherwise.
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function renderListTemplate($currentOrder)
    {
        $this->getListOrderFromSession('content', 'content_order', 'manual');

        return $this->render(
            'folders',
            array(
                'content_order' => $currentOrder,
                'parent' => $this->getFolderId()
            )
        );
    }

    protected function getEditionArguments()
    {
        return array(
            'content_id' => $this->getRequest()->get('content_id', 0),
            'current_tab' => $this->getRequest()->get('current_tab', 'general'),
            'folder_id' => $this->getFolderId(),
        );
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render('content-edit', $this->getEditionArguments());
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.content.update',
            [],
            $this->getEditionArguments()
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.content.default',
            ['parent' => $this->getFolderId()]
        );
    }

    /**
     * @param  \Thelia\Core\Event\Content\ContentUpdateEvent $updateEvent
     * @return Response|void
     */
    protected function performAdditionalUpdateAction($updateEvent)
    {
        if ($this->getRequest()->get('save_mode') != 'stay') {
            return $this->generateRedirectFromRoute(
                'admin.folders.default',
                ['parent' => $this->getFolderId()]
            );
        } else {
            return null;
        }
    }

    /**
     * Put in this method post object delete processing if required.
     *
     * @param  \Thelia\Core\Event\Content\ContentDeleteEvent $deleteEvent the delete event
     * @return Response                                      a response, or null to continue normal processing
     */
    protected function performAdditionalDeleteAction($deleteEvent)
    {
        return $this->generateRedirectFromRoute(
            'admin.folders.default',
            ['parent' => $deleteEvent->getDefaultFolderId()]
        );
    }

    /**
     * @param $event \Thelia\Core\Event\UpdatePositionEvent
     * @return null|Response
     */
    protected function performAdditionalUpdatePositionAction($event)
    {
        if (null !== $content = ContentQuery::create()->findPk($event->getObjectId())) {
            // Redirect to parent category list
            return $this->generateRedirectFromRoute(
                'admin.folders.default',
                ['parent' => $event->getReferrerId()]
            );
        } else {
            return null;
        }
    }

    /**
     * @param $positionChangeMode
     * @param $positionValue
     * @return UpdatePositionEvent|void
     */
    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('content_id', null),
            $positionChangeMode,
            $positionValue,
            $this->getRequest()->get('folder_id', null)
        );
    }

    /**
     * @return ContentToggleVisibilityEvent|void
     */
    protected function createToggleVisibilityEvent()
    {
        return new ContentToggleVisibilityEvent($this->getExistingObject());
    }
}

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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Category\CategoryAddContentEvent;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryDeleteContentEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryToggleVisibilityEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Category;
use Thelia\Model\CategoryAssociatedContentQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;

/**
 * Manages categories.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class CategoryController extends AbstractSeoCrudController
{
    public function __construct()
    {
        parent::__construct(
            'category',
            'manual',
            'category_order',
            AdminResources::CATEGORY,
            TheliaEvents::CATEGORY_CREATE,
            TheliaEvents::CATEGORY_UPDATE,
            TheliaEvents::CATEGORY_DELETE,
            TheliaEvents::CATEGORY_TOGGLE_VISIBILITY,
            TheliaEvents::CATEGORY_UPDATE_POSITION,
            TheliaEvents::CATEGORY_UPDATE_SEO
        );
    }

    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::CATEGORY_CREATION);
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::CATEGORY_MODIFICATION);
    }

    protected function getCreationEvent($formData)
    {
        $createEvent = new CategoryCreateEvent();

        $createEvent
            ->setTitle($formData['title'])
            ->setLocale($formData['locale'])
            ->setParent($formData['parent'])
            ->setVisible($formData['visible'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData)
    {
        $changeEvent = new CategoryUpdateEvent($formData['id']);

        // Create and dispatch the change event
        $changeEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setVisible($formData['visible'])
            ->setParent($formData['parent'])
            ->setDefaultTemplateId($formData['default_template_id'])
        ;

        return $changeEvent;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('category_id', null),
            $positionChangeMode,
            $positionValue
        );
    }

    protected function getDeleteEvent()
    {
        return new CategoryDeleteEvent($this->getRequest()->get('category_id', 0));
    }

    protected function eventContainsObject($event)
    {
        return $event->hasCategory();
    }

    /**
     * @param \Thelia\Model\Category $object
     *
     * @return \Thelia\Form\BaseForm
     */
    protected function hydrateObjectForm(ParserContext $parserContext, $object)
    {
        // Hydrate the "SEO" tab form
        $this->hydrateSeoForm($parserContext, $object);

        // The "General" tab form
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
            'visible' => $object->getVisible(),
            'parent' => $object->getParent(),
            'default_template_id' => $object->getDefaultTemplateId(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::CATEGORY_MODIFICATION, FormType::class, $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasCategory() ? $event->getCategory() : null;
    }

    protected function getExistingObject()
    {
        $category = CategoryQuery::create()
            ->findOneById($this->getRequest()->get('category_id', 0));

        if (null !== $category) {
            $category->setLocale($this->getCurrentEditionLocale());
        }

        return $category;
    }

    /**
     * @param Category $object
     *
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * @param Category $object
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
                'category_id' => $this->getRequest()->get('category_id', 0),
                'folder_id' => $this->getRequest()->get('folder_id', 0),
                'current_tab' => $this->getRequest()->get('current_tab', 'general'),
                'page' => $this->getRequest()->get('page', 1),
        ];
    }

    protected function renderListTemplate($currentOrder)
    {
        // Get product order
        $product_order = $this->getListOrderFromSession('product', 'product_order', 'manual');

        return $this->render(
            'categories',
            [
                'category_order' => $currentOrder,
                'product_order' => $product_order,
                'category_id' => $this->getRequest()->get('category_id', 0),
                'page' => $this->getRequest()->get('page', 1),
            ]
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.categories.default',
            [
                'category_id' => $this->getRequest()->get('category_id', 0),
                'page' => $this->getRequest()->get('page', 1),
            ]
        );
    }

    protected function redirectToListTemplateWithId($category_id)
    {
        $response = null;
        if ($category_id > 0) {
            $response = $this->generateRedirectFromRoute(
                'admin.categories.default',
                ['category_id' => $category_id]
            );
        } else {
            $response = $this->generateRedirectFromRoute('admin.catalog');
        }

        return $response;
    }

    protected function renderEditionTemplate()
    {
        return $this->render('category-edit', $this->getEditionArguments());
    }

    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.categories.update',
            $this->getEditionArguments()
        );
    }

    /**
     * Online status toggle category.
     */
    public function setToggleVisibilityAction(
        EventDispatcherInterface $eventDispatcher
    ) {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $event = new CategoryToggleVisibilityEvent($this->getExistingObject());

        try {
            $eventDispatcher->dispatch($event, TheliaEvents::CATEGORY_TOGGLE_VISIBILITY);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        // Ajax response -> no action
        return $this->nullResponse();
    }

    /**
     * @param CategoryDeleteEvent $deleteEvent
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    protected function performAdditionalDeleteAction($deleteEvent)
    {
        // Redirect to parent category list
        $category_id = $deleteEvent->getCategory()->getParent();

        return $this->redirectToListTemplateWithId($category_id);
    }

    /**
     * @param CategoryUpdateEvent $updateEvent
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, $updateEvent)
    {
        $response = null;
        if ($this->getRequest()->get('save_mode') != 'stay') {
            // Redirect to parent category list
            $category_id = $updateEvent->getCategory()->getParent();
            $response = $this->redirectToListTemplateWithId($category_id);
        }

        return $response;
    }

    /**
     * @param UpdatePositionEvent $event
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    protected function performAdditionalUpdatePositionAction($event)
    {
        $category = CategoryQuery::create()->findPk($event->getObjectId());
        $response = null;
        if ($category != null) {
            // Redirect to parent category list
            $category_id = $category->getParent();
            $response = $this->redirectToListTemplateWithId($category_id);
        }

        return $response;
    }

    public function getAvailableRelatedContentAction($categoryId, $folderId)
    {
        $result = [];

        $folders = FolderQuery::create()->filterById($folderId)->find();

        if ($folders !== null) {
            $list = ContentQuery::create()
                ->joinWithI18n($this->getCurrentEditionLocale())
                ->filterByFolder($folders, Criteria::IN)
                ->filterById(
                    CategoryAssociatedContentQuery::create()->select('content_id')->findByCategoryId($categoryId),
                    Criteria::NOT_IN
                )
                ->find();

            if ($list !== null) {
                foreach ($list as $item) {
                    $result[] = ['id' => $item->getId(), 'title' => $item->getTitle()];
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addRelatedContentAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $content_id = (int) $this->getRequest()->get('content_id');

        if ($content_id > 0) {
            $event = new CategoryAddContentEvent(
                $this->getExistingObject(),
                $content_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::CATEGORY_ADD_CONTENT);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        return $this->redirectToEditionTemplate();
    }

    /**
     * Add category pictures.
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function addRelatedPictureAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        return $this->redirectToEditionTemplate();
    }

    public function deleteRelatedContentAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

        $content_id = (int) $this->getRequest()->get('content_id');

        if ($content_id > 0) {
            $event = new CategoryDeleteContentEvent(
                $this->getExistingObject(),
                $content_id
            );

            try {
                $eventDispatcher->dispatch($event, TheliaEvents::CATEGORY_REMOVE_CONTENT);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        return $this->redirectToEditionTemplate();
    }
}

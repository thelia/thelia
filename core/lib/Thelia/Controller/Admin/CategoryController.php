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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
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
use Thelia\Form\BaseForm;
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
            TheliaEvents::CATEGORY_UPDATE_SEO,
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::CATEGORY_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::CATEGORY_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $createEvent = new CategoryCreateEvent();

        $createEvent
            ->setTitle($formData['title'])
            ->setLocale($formData['locale'])
            ->setParent($formData['parent'])
            ->setVisible($formData['visible']);

        return $createEvent;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
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
            ->setDefaultTemplateId($formData['default_template_id']);

        return $changeEvent;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue): UpdatePositionEvent
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('category_id'),
            $positionChangeMode,
            $positionValue,
        );
    }

    protected function getDeleteEvent(): CategoryDeleteEvent
    {
        return new CategoryDeleteEvent($this->getRequest()->get('category_id', 0));
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasCategory();
    }

    /**
     * @param Category $object
     */
    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
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

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasCategory() ? $event->getCategory() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $category = CategoryQuery::create()
            ->findOneById($this->getRequest()->get('category_id', 0));

        if (null !== $category) {
            $category->setLocale($this->getCurrentEditionLocale());
        }

        return $category;
    }

    protected function getObjectLabel(ActiveRecordInterface $object): string
    {
        \assert($object instanceof Category);

        return $object->getTitle();
    }

    /**
     * @param Category $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function getEditionArguments(): array
    {
        return [
            'category_id' => $this->getRequest()->get('category_id', 0),
            'folder_id' => $this->getRequest()->get('folder_id', 0),
            'current_tab' => $this->getRequest()->get('current_tab', 'general'),
            'page' => $this->getRequest()->get('page', 1),
        ];
    }

    protected function renderListTemplate(string $currentOrder): Response
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
            ],
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.categories.default',
            [
                'category_id' => $this->getRequest()->get('category_id', 0),
                'page' => $this->getRequest()->get('page', 1),
            ],
        );
    }

    protected function redirectToListTemplateWithId($category_id)
    {
        $response = null;

        if ($category_id > 0) {
            $response = $this->generateRedirectFromRoute(
                'admin.categories.default',
                ['category_id' => $category_id],
            );
        } else {
            $response = $this->generateRedirectFromRoute('admin.catalog');
        }

        return $response;
    }

    protected function renderEditionTemplate(): Response
    {
        return $this->render('category-edit', $this->getEditionArguments());
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.categories.update',
            $this->getEditionArguments(),
        );
    }

    /**
     * Online status toggle category.
     */
    public function setToggleVisibilityAction(
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $event = new CategoryToggleVisibilityEvent($this->getExistingObject());

        try {
            $eventDispatcher->dispatch($event, TheliaEvents::CATEGORY_TOGGLE_VISIBILITY);
        } catch (\Exception $exception) {
            // Any error
            return $this->errorPage($exception);
        }

        // Ajax response -> no action
        return $this->nullResponse();
    }

    protected function performAdditionalDeleteAction(ActionEvent $deleteEvent): ?Response
    {
        // Redirect to parent category list
        $category_id = $deleteEvent->getCategory()->getParent();

        return $this->redirectToListTemplateWithId($category_id);
    }

    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, ActionEvent $updateEvent): ?Response
    {
        $response = null;

        if ('stay' !== $this->getRequest()->get('save_mode')) {
            // Redirect to parent category list
            $category_id = $updateEvent->getCategory()->getParent();
            $response = $this->redirectToListTemplateWithId($category_id);
        }

        return $response;
    }

    protected function performAdditionalUpdatePositionAction(ActionEvent $positionChangeEvent): ?Response
    {
        $category = CategoryQuery::create()->findPk($positionChangeEvent->getObjectId());
        $response = null;

        if (null !== $category) {
            // Redirect to parent category list
            $category_id = $category->getParent();
            $response = $this->redirectToListTemplateWithId($category_id);
        }

        return $response;
    }

    public function getAvailableRelatedContentAction($categoryId, $folderId): Response
    {
        $result = [];

        $folders = FolderQuery::create()->filterById($folderId)->find();

        if (null !== $folders) {
            $list = ContentQuery::create()
                ->joinWithI18n($this->getCurrentEditionLocale())
                ->filterByFolder($folders, Criteria::IN)
                ->filterById(
                    CategoryAssociatedContentQuery::create()->select('content_id')->findByCategoryId($categoryId),
                    Criteria::NOT_IN,
                )
                ->find();

            if (null !== $list) {
                foreach ($list as $item) {
                    $result[] = ['id' => $item->getId(), 'title' => $item->getTitle()];
                }
            }
        }

        return $this->jsonResponse(json_encode($result));
    }

    public function addRelatedContentAction(EventDispatcherInterface $eventDispatcher): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $content_id = (int) $this->getRequest()->get('content_id');

        if ($content_id > 0) {
            $event = new CategoryAddContentEvent(
                $this->getExistingObject(),
                $content_id,
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
     */
    public function addRelatedPictureAction(): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        return $this->redirectToEditionTemplate();
    }

    public function deleteRelatedContentAction(EventDispatcherInterface $eventDispatcher): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $content_id = (int) $this->getRequest()->get('content_id');

        if ($content_id > 0) {
            $event = new CategoryDeleteContentEvent(
                $this->getExistingObject(),
                $content_id,
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

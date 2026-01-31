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

namespace Thelia\Domain\Catalog\Category;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Category\CategoryAddContentEvent;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryDeleteContentEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryToggleVisibilityEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Domain\Catalog\Category\DTO\CategoryCreateDTO;
use Thelia\Domain\Catalog\Category\DTO\CategorySeoDTO;
use Thelia\Domain\Catalog\Category\DTO\CategoryUpdateDTO;
use Thelia\Domain\Catalog\Category\Exception\CategoryNotFoundException;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;

final readonly class CategoryFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function create(CategoryCreateDTO $dto): Category
    {
        $event = new CategoryCreateEvent();
        $event
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setParent($dto->parentId)
            ->setVisible($dto->visible);

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_CREATE);

        return $event->getCategory();
    }

    public function update(int $categoryId, CategoryUpdateDTO $dto): Category
    {
        $this->assertCategoryExists($categoryId);

        $event = new CategoryUpdateEvent($categoryId);
        $event
            ->setTitle($dto->title)
            ->setLocale($dto->locale)
            ->setParent($dto->parentId)
            ->setVisible($dto->visible)
            ->setChapo($dto->chapo)
            ->setDescription($dto->description)
            ->setPostscriptum($dto->postscriptum);

        if (null !== $dto->defaultTemplateId) {
            $event->setDefaultTemplateId($dto->defaultTemplateId);
        }

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_UPDATE);

        return $event->getCategory();
    }

    public function delete(int $categoryId): void
    {
        $this->assertCategoryExists($categoryId);

        $event = new CategoryDeleteEvent($categoryId);

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_DELETE);
    }

    public function toggleVisibility(int $categoryId): Category
    {
        $category = $this->getById($categoryId);

        if (null === $category) {
            throw CategoryNotFoundException::withId($categoryId);
        }

        $event = new CategoryToggleVisibilityEvent($category);

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_TOGGLE_VISIBILITY);

        return $event->getCategory();
    }

    public function updatePosition(int $categoryId, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $this->assertCategoryExists($categoryId);

        $event = new UpdatePositionEvent($categoryId, $mode, $position);

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_UPDATE_POSITION);
    }

    public function addContent(int $categoryId, int $contentId): void
    {
        $category = $this->getById($categoryId);

        if (null === $category) {
            throw CategoryNotFoundException::withId($categoryId);
        }

        $event = new CategoryAddContentEvent($category, $contentId);

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_ADD_CONTENT);
    }

    public function removeContent(int $categoryId, int $contentId): void
    {
        $category = $this->getById($categoryId);

        if (null === $category) {
            throw CategoryNotFoundException::withId($categoryId);
        }

        $event = new CategoryDeleteContentEvent($category, $contentId);

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_REMOVE_CONTENT);
    }

    public function updateSeo(int $categoryId, CategorySeoDTO $dto): Category
    {
        $this->assertCategoryExists($categoryId);

        $event = new UpdateSeoEvent($categoryId);
        $event
            ->setLocale($dto->locale)
            ->setUrl($dto->url)
            ->setMetaTitle($dto->metaTitle)
            ->setMetaDescription($dto->metaDescription)
            ->setMetaKeywords($dto->metaKeywords);

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_UPDATE_SEO);

        return $event->getObject();
    }

    public function getById(int $categoryId): ?Category
    {
        return CategoryQuery::create()->findPk($categoryId);
    }

    /**
     * @return Category[]
     */
    public function getChildren(int $parentId, bool $visibleOnly = false): array
    {
        $query = CategoryQuery::create()
            ->filterByParent($parentId)
            ->orderByPosition();

        if ($visibleOnly) {
            $query->filterByVisible(true);
        }

        return $query->find()->getData();
    }

    /**
     * @return Category[]
     */
    public function getBreadcrumb(int $categoryId): array
    {
        $breadcrumb = [];
        $category = $this->getById($categoryId);

        while (null !== $category) {
            array_unshift($breadcrumb, $category);

            if ($category->getParent() > 0) {
                $category = $this->getById($category->getParent());
            } else {
                $category = null;
            }
        }

        return $breadcrumb;
    }

    /**
     * @return Category[]
     */
    public function getRootCategories(bool $visibleOnly = false): array
    {
        return $this->getChildren(0, $visibleOnly);
    }

    private function assertCategoryExists(int $categoryId): void
    {
        if (null === CategoryQuery::create()->findPk($categoryId)) {
            throw CategoryNotFoundException::withId($categoryId);
        }
    }
}

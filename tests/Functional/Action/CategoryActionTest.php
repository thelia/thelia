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

namespace Thelia\Tests\Functional\Action;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CategoryQuery;
use Thelia\Test\IntegrationTestCase;

final class CategoryActionTest extends IntegrationTestCase
{
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
    }

    public function testCreateCategoryPersistsAndAssignsId(): void
    {
        $event = new CategoryCreateEvent();
        $event->setTitle('Test Category');
        $event->setLocale('en_US');
        $event->setParent(0);
        $event->setVisible(1);

        $this->dispatcher->dispatch($event, TheliaEvents::CATEGORY_CREATE);

        $category = $event->getCategory();
        self::assertNotNull($category);
        self::assertNotNull($category->getId());
        self::assertSame(0, $category->getParent());
        self::assertSame(1, $category->getVisible());

        $reloaded = CategoryQuery::create()->findPk($category->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Test Category', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testUpdateCategoryChangesFields(): void
    {
        $createEvent = new CategoryCreateEvent();
        $createEvent->setTitle('Original');
        $createEvent->setLocale('en_US');
        $createEvent->setParent(0);
        $createEvent->setVisible(1);
        $this->dispatcher->dispatch($createEvent, TheliaEvents::CATEGORY_CREATE);

        $categoryId = $createEvent->getCategory()->getId();

        $updateEvent = new CategoryUpdateEvent($categoryId);
        $updateEvent->setTitle('Updated Title');
        $updateEvent->setLocale('en_US');
        $updateEvent->setParent(0);
        $updateEvent->setVisible(0);
        $updateEvent->setDescription('A description');
        $updateEvent->setDefaultTemplateId(0);

        $this->dispatcher->dispatch($updateEvent, TheliaEvents::CATEGORY_UPDATE);

        $reloaded = CategoryQuery::create()->findPk($categoryId);
        self::assertNotNull($reloaded);
        $reloaded->setLocale('en_US');
        self::assertSame('Updated Title', $reloaded->getTitle());
        self::assertSame('A description', $reloaded->getDescription());
        self::assertSame(0, $reloaded->getVisible());
        self::assertNull($reloaded->getDefaultTemplateId());
    }

    public function testCreateNestedCategory(): void
    {
        $parentEvent = new CategoryCreateEvent();
        $parentEvent->setTitle('Parent');
        $parentEvent->setLocale('en_US');
        $parentEvent->setParent(0);
        $parentEvent->setVisible(1);
        $this->dispatcher->dispatch($parentEvent, TheliaEvents::CATEGORY_CREATE);

        $parentId = $parentEvent->getCategory()->getId();

        $childEvent = new CategoryCreateEvent();
        $childEvent->setTitle('Child');
        $childEvent->setLocale('en_US');
        $childEvent->setParent($parentId);
        $childEvent->setVisible(1);
        $this->dispatcher->dispatch($childEvent, TheliaEvents::CATEGORY_CREATE);

        $child = $childEvent->getCategory();
        self::assertSame($parentId, $child->getParent());
    }
}

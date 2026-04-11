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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Folder\FolderCreateEvent;
use Thelia\Core\Event\Folder\FolderDeleteEvent;
use Thelia\Core\Event\Folder\FolderToggleVisibilityEvent;
use Thelia\Core\Event\Folder\FolderUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\FolderQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class FolderActionTest extends ActionIntegrationTestCase
{
    public function testCreateRootFolderPersistsWithI18n(): void
    {
        $event = new FolderCreateEvent();
        $event->setTitle('Docs')->setLocale('en_US')->setParent(0)->setVisible(1);

        $this->dispatch($event, TheliaEvents::FOLDER_CREATE);

        $folder = $event->getFolder();
        self::assertNotNull($folder);
        self::assertSame(0, $folder->getParent());
        self::assertSame('Docs', $folder->setLocale('en_US')->getTitle());
    }

    public function testCreateChildFolderReferencesParent(): void
    {
        $parent = $this->factory->folder(0, ['title' => 'Root']);

        $event = new FolderCreateEvent();
        $event->setTitle('Child')->setLocale('en_US')->setParent($parent->getId())->setVisible(1);

        $this->dispatch($event, TheliaEvents::FOLDER_CREATE);

        self::assertSame($parent->getId(), $event->getFolder()->getParent());
    }

    public function testUpdateChangesTitleAndDescription(): void
    {
        $folder = $this->factory->folder(0, ['title' => 'Old']);

        $event = new FolderUpdateEvent($folder->getId());
        $event
            ->setTitle('New')
            ->setLocale('en_US')
            ->setParent(0)
            ->setVisible(1)
            ->setChapo('')
            ->setDescription('description')
            ->setPostscriptum('');

        $this->dispatch($event, TheliaEvents::FOLDER_UPDATE);

        $reloaded = FolderQuery::create()->findPk($folder->getId());
        self::assertSame('New', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('description', $reloaded->getDescription());
    }

    public function testToggleVisibilityFlipsFlag(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 1]);

        $event = new FolderToggleVisibilityEvent($folder);
        $this->dispatch($event, TheliaEvents::FOLDER_TOGGLE_VISIBILITY);

        self::assertSame(0, (int) FolderQuery::create()->findPk($folder->getId())->getVisible());
    }

    public function testDeleteRemovesFolderFromDatabase(): void
    {
        $folder = $this->factory->folder();
        $folderId = $folder->getId();

        $this->dispatch(new FolderDeleteEvent($folderId), TheliaEvents::FOLDER_DELETE);

        self::assertNull(FolderQuery::create()->findPk($folderId));
    }
}

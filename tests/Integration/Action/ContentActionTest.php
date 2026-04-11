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

use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ContentActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsContentAndLinksDefaultFolder(): void
    {
        $folder = $this->factory->folder(0, ['title' => 'Docs']);

        $event = new ContentCreateEvent();
        $event
            ->setTitle('Article One')
            ->setLocale('en_US')
            ->setDefaultFolder($folder->getId())
            ->setVisible(1);

        $this->dispatch($event, TheliaEvents::CONTENT_CREATE);

        $content = $event->getContent();
        self::assertNotNull($content);
        self::assertSame('Article One', $content->setLocale('en_US')->getTitle());

        $link = ContentFolderQuery::create()
            ->filterByContentId($content->getId())
            ->filterByDefaultFolder(true)
            ->findOne();
        self::assertNotNull($link);
        self::assertSame($folder->getId(), $link->getFolderId());
    }

    public function testUpdateChangesI18nFields(): void
    {
        $folder = $this->factory->folder();
        $content = $this->factory->content($folder, ['title' => 'Old']);

        $event = new ContentUpdateEvent($content->getId());
        $event
            ->setTitle('New')
            ->setLocale('en_US')
            ->setDefaultFolder($folder->getId())
            ->setVisible(1)
            ->setChapo('')
            ->setDescription('body')
            ->setPostscriptum('');

        $this->dispatch($event, TheliaEvents::CONTENT_UPDATE);

        $reloaded = ContentQuery::create()->findPk($content->getId());
        self::assertSame('New', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('body', $reloaded->getDescription());
    }

    public function testToggleVisibilityFlipsFlag(): void
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 1]);

        $this->dispatch(new ContentToggleVisibilityEvent($content), TheliaEvents::CONTENT_TOGGLE_VISIBILITY);

        self::assertSame(0, (int) ContentQuery::create()->findPk($content->getId())->getVisible());
    }

    public function testDeleteRemovesContentAndContentFolderLinks(): void
    {
        $folder = $this->factory->folder();
        $content = $this->factory->content($folder);
        $contentId = $content->getId();

        $this->dispatch(new ContentDeleteEvent($contentId), TheliaEvents::CONTENT_DELETE);

        self::assertNull(ContentQuery::create()->findPk($contentId));
        self::assertSame(0, ContentFolderQuery::create()->filterByContentId($contentId)->count());
    }
}

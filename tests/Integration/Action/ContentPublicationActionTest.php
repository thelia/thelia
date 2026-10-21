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

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\Content\ContentUpdatePublicationEvent;
use Thelia\Core\Event\Folder\FolderUpdatePublicationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Domain\CMS\Content\ContentFacade;
use Thelia\Domain\CMS\Publication\PublicationRequest;
use Thelia\Domain\CMS\Publication\PublicationState;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The publication events, the facade, and the page check that turns down a content
 * whose default folder is not online yet.
 */
final class ContentPublicationActionTest extends ActionIntegrationTestCase
{
    public function testTheContentEventPublishesAndLeavesTheStateOnTheEvent(): void
    {
        $content = $this->draftContent();

        $event = $this->dispatch(new ContentUpdatePublicationEvent($content, PublicationRequest::publish()), TheliaEvents::CONTENT_UPDATE_PUBLICATION);

        self::assertSame(PublicationState::Published, $event->getStatus()?->state);
        self::assertSame(1, (int) ContentQuery::create()->findPk($content->getId())->getVisible());
    }

    public function testTheFolderEventSchedulesAndLeavesTheStateOnTheEvent(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 0]);
        $tomorrow = new \DateTimeImmutable('+1 day');

        $event = $this->dispatch(new FolderUpdatePublicationEvent($folder, PublicationRequest::schedule($tomorrow)), TheliaEvents::FOLDER_UPDATE_PUBLICATION);

        self::assertSame(PublicationState::Scheduled, $event->getStatus()?->state);
        $row = FolderQuery::create()->findPk($folder->getId());
        self::assertInstanceOf(Folder::class, $row);
        self::assertSame(0, (int) $row->getVisible());
        self::assertSame($tomorrow->format('Y-m-d H:i:s'), $row->getPublishAt('Y-m-d H:i:s'));
    }

    public function testTheFacadeTakesAContentOffline(): void
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 1]);

        $status = $this->getService(ContentFacade::class)->updatePublication((int) $content->getId(), PublicationRequest::unpublish());

        self::assertSame(PublicationState::Unpublished, $status->state);
        self::assertSame(0, (int) ContentQuery::create()->findPk($content->getId())->getVisible());
    }

    public function testAVisibleContentInAPublishedFolderPassesTheCheck(): void
    {
        $content = $this->factory->content($this->factory->folder(0, ['visible' => 1]), ['visible' => 1]);

        $this->dispatch(new ViewCheckEvent('content', $content->getId()), TheliaEvents::VIEW_CHECK);

        $this->addToAssertionCount(1);
    }

    public function testAContentInAFolderTakenOfflineKeepsAnsweringAsBefore(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 0]);
        $folder->setUnpublishAt(new \DateTimeImmutable('-1 day'))->save();
        $content = $this->factory->content($folder, ['visible' => 1]);

        $this->dispatch(new ViewCheckEvent('content', $content->getId()), TheliaEvents::VIEW_CHECK);

        $this->addToAssertionCount(1);
    }

    public function testAContentWhoseDefaultFolderIsADraftIsTurnedDown(): void
    {
        $content = $this->factory->content($this->factory->folder(0, ['visible' => 0]), ['visible' => 1]);

        $this->expectException(NotFoundHttpException::class);

        $this->dispatch(new ViewCheckEvent('content', $content->getId()), TheliaEvents::VIEW_CHECK);
    }

    public function testAContentWhoseDefaultFolderIsScheduledIsTurnedDown(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 0]);
        $folder->setPublishAt(new \DateTimeImmutable('+1 day'))->setUnpublishAt(new \DateTimeImmutable('+2 days'))->save();
        $content = $this->factory->content($folder, ['visible' => 1]);

        $this->expectException(NotFoundHttpException::class);

        $this->dispatch(new ViewCheckEvent('content', $content->getId()), TheliaEvents::VIEW_CHECK);
    }

    public function testADraftFolderThatIsNotTheDefaultOneDoesNotHideTheContent(): void
    {
        $content = $this->factory->content($this->factory->folder(0, ['visible' => 1]), ['visible' => 1]);
        $this->getService(ContentFacade::class)->addFolder((int) $content->getId(), (int) $this->factory->folder(0, ['visible' => 0])->getId());

        $this->dispatch(new ViewCheckEvent('content', $content->getId()), TheliaEvents::VIEW_CHECK);

        $this->addToAssertionCount(1);
    }

    private function draftContent(): Content
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $content->setLocale('en_US')->setDescription('<p>A body</p>')->save();

        return $content;
    }
}

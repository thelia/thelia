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

namespace Thelia\Tests\Integration\Model;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\Folder\FolderToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\CMS\Publication\PublicationRequest;
use Thelia\Domain\CMS\Publication\PublicationService;
use Thelia\Domain\CMS\Publication\PublicationState;
use Thelia\Domain\CMS\Publication\ScheduledPublicationRunner;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A change of `visible` made outside the publication service (the old toggle and
 * update events, the admin API, a module) keeps the dates in step: a row taken offline
 * that way is unpublished, not "scheduled, overdue", and the scheduled command does not
 * put it back online.
 */
final class VisibilityDatesAlignmentTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testAContentToggledOffIsUnpublishedAndStaysOffAfterTheScheduledCommand(): void
    {
        $content = $this->publishedContent();

        $this->dispatcher()->dispatch(new ContentToggleVisibilityEvent($content), TheliaEvents::CONTENT_TOGGLE_VISIBILITY);

        self::assertSame(PublicationState::Unpublished, $this->stateOf($content));
        self::assertNotNull($this->reloaded($content)->getUnpublishAt());

        $this->getService(ScheduledPublicationRunner::class)->run();

        self::assertSame(PublicationState::Unpublished, $this->stateOf($content), 'the command does not put it back online');
        self::assertSame(0, (int) $this->reloaded($content)->getVisible());
    }

    public function testAContentHiddenByTheUpdateEventIsUnpublished(): void
    {
        $content = $this->publishedContent();

        $event = (new ContentUpdateEvent($content->getId()))
            ->setTitle('Hidden again')
            ->setLocale('en_US')
            ->setDefaultFolder($content->getDefaultFolderId())
            ->setVisible(0)
            ->setChapo('')
            ->setDescription('<p>A body</p>')
            ->setPostscriptum('');
        $this->dispatcher()->dispatch($event, TheliaEvents::CONTENT_UPDATE);

        self::assertSame(PublicationState::Unpublished, $this->stateOf($content));

        $this->getService(ScheduledPublicationRunner::class)->run();

        self::assertSame(PublicationState::Unpublished, $this->stateOf($content));
    }

    public function testAPlainSaveHidingAContentWithAFutureWithdrawalTakesItOffNow(): void
    {
        $content = $this->publishedContent();
        $content->setUnpublishAt(new \DateTimeImmutable('+1 week'))->save();

        $content->setVisible(0)->save();

        $row = $this->reloaded($content);
        self::assertSame(PublicationState::Unpublished, $this->stateOf($content));
        self::assertLessThanOrEqual(new \DateTimeImmutable(), new \DateTimeImmutable($row->getUnpublishAt('Y-m-d H:i:s')));
    }

    public function testAContentToggledOnGetsItsPublicationDateAndForgetsAPastWithdrawal(): void
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $content->setLocale('en_US')->setDescription('<p>A body</p>')->setUnpublishAt(new \DateTimeImmutable('-1 day'))->save();
        self::assertSame(PublicationState::Unpublished, $this->stateOf($content));

        $this->dispatcher()->dispatch(new ContentToggleVisibilityEvent($this->reloaded($content)), TheliaEvents::CONTENT_TOGGLE_VISIBILITY);

        $row = $this->reloaded($content);
        self::assertSame(PublicationState::Published, $this->stateOf($content));
        self::assertNotNull($row->getPublishAt());
        self::assertNull($row->getUnpublishAt());
    }

    public function testAScheduleMadeByTheServiceFromAPublishedRowIsLeftAlone(): void
    {
        $content = $this->publishedContent();
        $tomorrow = new \DateTimeImmutable('+1 day');

        $this->getService(PublicationService::class)->apply($content, PublicationRequest::schedule($tomorrow));

        $row = $this->reloaded($content);
        self::assertSame(PublicationState::Scheduled, $this->stateOf($content));
        self::assertSame($tomorrow->format('Y-m-d H:i:s'), $row->getPublishAt('Y-m-d H:i:s'));
        self::assertNull($row->getUnpublishAt());
    }

    public function testAFolderToggledOffIsUnpublishedAndStaysOffAfterTheScheduledCommand(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 1]);
        $folder->setPublishAt(new \DateTimeImmutable('-1 week'))->save();

        $this->dispatcher()->dispatch(new FolderToggleVisibilityEvent($folder), TheliaEvents::FOLDER_TOGGLE_VISIBILITY);

        self::assertSame(PublicationState::Unpublished, $this->stateOf($folder));

        $this->getService(ScheduledPublicationRunner::class)->run();

        self::assertSame(PublicationState::Unpublished, $this->stateOf($folder));
        self::assertSame(0, (int) FolderQuery::create()->findPk($folder->getId())->getVisible());
    }

    private function publishedContent(): Content
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 1]);
        $content->setLocale('en_US')->setDescription('<p>A body</p>')->setPublishAt(new \DateTimeImmutable('-1 week'))->save();

        return $content;
    }

    private function reloaded(Content $content): Content
    {
        $row = ContentQuery::create()->findPk($content->getId());
        self::assertInstanceOf(Content::class, $row);

        return $row;
    }

    private function stateOf(Content|Folder $row): PublicationState
    {
        $fresh = $row instanceof Content
            ? ContentQuery::create()->findPk($row->getId())
            : FolderQuery::create()->findPk($row->getId());

        return $this->getService(PublicationService::class)->statusOf($fresh)->state;
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }
}

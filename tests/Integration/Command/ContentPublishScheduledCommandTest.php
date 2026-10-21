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

namespace Thelia\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Content\ContentUpdatePublicationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\CMS\Publication\PublicationService;
use Thelia\Domain\CMS\Publication\PublicationState;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Log\CollectingTlogDestination;

/**
 * `content:publish-scheduled` acts on the dates that have come, reports what it did,
 * leaves a content with no body scheduled, and finds nothing left on a second pass.
 */
final class ContentPublishScheduledCommandTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testTheDueRowsArePublishedAndTakenOfflineAndTheOthersLeftAlone(): void
    {
        $due = $this->scheduledContent('-1 hour', '<p>Ready</p>');
        $dueWithAnEnd = $this->scheduledContent('-1 hour', '<p>Ready</p>', '+1 day');
        $later = $this->scheduledContent('+1 hour', '<p>Not yet</p>');
        $over = $this->publishedContent('-1 hour');
        $stillOn = $this->publishedContent('+1 hour');
        $closedWindow = $this->scheduledContent('-2 hours', '<p>Window closed</p>', '-1 hour');
        $dueFolder = $this->scheduledFolder('-1 hour');
        $overFolder = $this->publishedFolder('-1 hour');

        $tester = $this->runTheCommand();

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(PublicationState::Published, $this->stateOf($due));
        self::assertSame($due->getPublishAt('Y-m-d H:i:s'), ContentQuery::create()->findPk($due->getId())->getPublishAt('Y-m-d H:i:s'), 'the date waited for is the publication date');
        self::assertSame(PublicationState::Published, $this->stateOf($dueWithAnEnd));
        self::assertNotNull(ContentQuery::create()->findPk($dueWithAnEnd->getId())->getUnpublishAt(), 'a withdrawal still ahead is kept');
        self::assertSame(PublicationState::Scheduled, $this->stateOf($later));
        self::assertSame(PublicationState::Unpublished, $this->stateOf($over));
        self::assertSame(PublicationState::Published, $this->stateOf($stillOn));
        self::assertSame(PublicationState::Unpublished, $this->stateOf($closedWindow), 'a window that closed while nothing ran is not opened');
        self::assertSame(PublicationState::Published, $this->stateOf($dueFolder));
        self::assertSame(PublicationState::Unpublished, $this->stateOf($overFolder));

        $display = $tester->getDisplay();
        self::assertStringContainsString(\sprintf('content #%d published', $due->getId()), $display);
        self::assertStringContainsString(\sprintf('content #%d unpublished', $over->getId()), $display);
        self::assertStringContainsString(\sprintf('folder #%d published', $dueFolder->getId()), $display);
        self::assertStringContainsString(\sprintf('folder #%d unpublished', $overFolder->getId()), $display);
        self::assertStringNotContainsString(\sprintf('content #%d', $later->getId()), $display);
    }

    public function testASecondPassFindsNothingToDo(): void
    {
        $this->scheduledContent('-1 hour', '<p>Ready</p>');
        $this->publishedContent('-1 hour');
        $this->runTheCommand();

        $tester = $this->runTheCommand();

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Nothing is due', $tester->getDisplay());
    }

    public function testADryRunReportsWithoutWriting(): void
    {
        $due = $this->scheduledContent('-1 hour', '<p>Ready</p>');
        $over = $this->publishedContent('-1 hour');

        $tester = $this->runTheCommand(dryRun: true);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('nothing is written', $tester->getDisplay());
        self::assertStringContainsString(\sprintf('content #%d published (would be)', $due->getId()), $tester->getDisplay());
        self::assertSame(PublicationState::Scheduled, $this->stateOf($due));
        self::assertSame(PublicationState::Published, $this->stateOf($over));
    }

    public function testAContentWithNoBodyStaysScheduledAndIsReported(): void
    {
        $empty = $this->scheduledContent('-1 hour', '<p>&nbsp;</p>');
        $ready = $this->scheduledContent('-1 hour', '<p>Ready</p>');

        $tester = $this->runTheCommand();

        self::assertSame(1, $tester->getStatusCode(), 'the scheduler is told something was left behind');
        self::assertSame(PublicationState::Scheduled, $this->stateOf($empty));
        self::assertSame(PublicationState::Published, $this->stateOf($ready), 'the pass goes on with the others');
        self::assertStringContainsString(\sprintf('content #%d left as it is', $empty->getId()), $tester->getDisplay());
        self::assertStringContainsString('no body in the default language', $tester->getDisplay());
    }

    public function testADryRunAnnouncesTheRefusalOfAContentWithNoBodyWithoutWriting(): void
    {
        $empty = $this->scheduledContent('-1 hour', '<p>&nbsp;</p>');
        $ready = $this->scheduledContent('-1 hour', '<p>Ready</p>');

        $tester = $this->runTheCommand(dryRun: true);

        self::assertSame(1, $tester->getStatusCode(), 'the same exit code as the real pass');
        self::assertStringContainsString(\sprintf('content #%d left as it is', $empty->getId()), $tester->getDisplay());
        self::assertStringNotContainsString(\sprintf('content #%d published', $empty->getId()), $tester->getDisplay());
        self::assertStringContainsString(\sprintf('content #%d published (would be)', $ready->getId()), $tester->getDisplay());
        self::assertSame(PublicationState::Scheduled, $this->stateOf($empty));
        self::assertSame(PublicationState::Scheduled, $this->stateOf($ready), 'nothing is written');
    }

    public function testARefusalIsLoggedAtTheErrorLevelTheLogKeepsByDefault(): void
    {
        $empty = $this->scheduledContent('-1 hour', '<p>&nbsp;</p>');

        ConfigQuery::initCache([Tlog::VAR_DESTINATIONS => CollectingTlogDestination::class, Tlog::VAR_LEVEL => (string) Tlog::DEFAULT_LEVEL]);
        (new \ReflectionProperty(Tlog::class, 'instance'))->setValue(null, null);
        CollectingTlogDestination::$lines = [];

        try {
            $this->runTheCommand();
            $lines = CollectingTlogDestination::$lines;
        } finally {
            ConfigQuery::resetCache();
            (new \ReflectionProperty(Tlog::class, 'instance'))->setValue(null, null);
            CollectingTlogDestination::$lines = [];
        }

        $refusals = array_values(array_filter($lines, static fn (string $line): bool => str_contains($line, \sprintf('Scheduled publication of content #%d refused', $empty->getId()))));
        self::assertCount(1, $refusals, implode("\n", $lines));
        self::assertStringContainsString('ERROR', $refusals[0]);
    }

    public function testEachRowGoesThroughItsPublicationEvent(): void
    {
        $due = $this->scheduledContent('-1 hour', '<p>Ready</p>');
        $seen = [];
        $listener = static function (ContentUpdatePublicationEvent $event) use (&$seen): void {
            $seen[] = (int) $event->getContent()?->getId();
        };
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::CONTENT_UPDATE_PUBLICATION, $listener);

        try {
            $this->runTheCommand();
        } finally {
            $dispatcher->removeListener(TheliaEvents::CONTENT_UPDATE_PUBLICATION, $listener);
        }

        self::assertContains((int) $due->getId(), $seen);
    }

    public function testTheRowsAreReadInBatchesWithoutSkippingAny(): void
    {
        $contents = [];

        for ($index = 0; $index < 5; ++$index) {
            $contents[] = $this->scheduledContent('-1 hour', '<p>Ready</p>');
        }

        $this->runTheCommand(batchSize: 2);

        foreach ($contents as $content) {
            self::assertSame(PublicationState::Published, $this->stateOf($content));
        }
    }

    private function runTheCommand(bool $dryRun = false, int $batchSize = 100): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('content:publish-scheduled'));
        $tester->execute(['--dry-run' => $dryRun, '--batch-size' => (string) $batchSize]);

        return $tester;
    }

    private function scheduledContent(string $publishAt, string $body, ?string $unpublishAt = null): Content
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $content
            ->setLocale('en_US')
            ->setDescription($body)
            ->setPublishAt(new \DateTimeImmutable($publishAt))
            ->setUnpublishAt(null === $unpublishAt ? null : new \DateTimeImmutable($unpublishAt))
            ->save();

        return $content;
    }

    private function publishedContent(string $unpublishAt): Content
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 1]);
        $content->setPublishAt(new \DateTimeImmutable('-1 week'))->setUnpublishAt(new \DateTimeImmutable($unpublishAt))->save();

        return $content;
    }

    private function scheduledFolder(string $publishAt): Folder
    {
        $folder = $this->factory->folder(0, ['visible' => 0]);
        $folder->setPublishAt(new \DateTimeImmutable($publishAt))->save();

        return $folder;
    }

    private function publishedFolder(string $unpublishAt): Folder
    {
        $folder = $this->factory->folder(0, ['visible' => 1]);
        $folder->setPublishAt(new \DateTimeImmutable('-1 week'))->setUnpublishAt(new \DateTimeImmutable($unpublishAt))->save();

        return $folder;
    }

    private function stateOf(Content|Folder $row): PublicationState
    {
        $fresh = $row instanceof Content
            ? ContentQuery::create()->findPk($row->getId())
            : FolderQuery::create()->findPk($row->getId());

        return $this->getService(PublicationService::class)->statusOf($fresh)->state;
    }
}

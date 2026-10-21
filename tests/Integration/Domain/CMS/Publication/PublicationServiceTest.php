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

namespace Thelia\Tests\Integration\Domain\CMS\Publication;

use Symfony\Component\Clock\MockClock;
use Thelia\Domain\CMS\Publication\Exception\EmptyContentPublicationException;
use Thelia\Domain\CMS\Publication\Exception\InvalidPublicationDatesException;
use Thelia\Domain\CMS\Publication\Exception\InvalidPublicationTransitionException;
use Thelia\Domain\CMS\Publication\PublicationRequest;
use Thelia\Domain\CMS\Publication\PublicationService;
use Thelia\Domain\CMS\Publication\PublicationState;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Every transition of a content or a folder, written through the service and read back
 * from the row, under a clock stopped at noon.
 */
final class PublicationServiceTest extends IntegrationTestCase
{
    private const string NOW = '2026-10-21 12:00:00';

    private MockClock $clock;

    private PublicationService $service;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new MockClock(self::NOW);
        $this->service = new PublicationService($this->clock);
        $this->factory = $this->createFixtureFactory();
    }

    public function testADraftIsPublishedNow(): void
    {
        $content = $this->draft();

        $status = $this->service->apply($content, PublicationRequest::publish());

        self::assertSame(PublicationState::Published, $status->state);
        $row = $this->reloaded($content);
        self::assertSame(1, (int) $row->getVisible());
        self::assertSame(self::NOW, $row->getPublishAt('Y-m-d H:i:s'));
        self::assertNull($row->getUnpublishAt());
    }

    public function testADraftIsScheduledWithItsDates(): void
    {
        $content = $this->draft();

        $status = $this->service->apply($content, PublicationRequest::schedule($this->at('+1 day'), $this->at('+3 days')));

        self::assertSame(PublicationState::Scheduled, $status->state);
        self::assertSame('2026-10-22 12:00:00', $status->pendingDate()?->format('Y-m-d H:i:s'));
        $row = $this->reloaded($content);
        self::assertSame(0, (int) $row->getVisible());
        self::assertSame('2026-10-24 12:00:00', $row->getUnpublishAt('Y-m-d H:i:s'));
    }

    public function testAScheduleInThePastIsRefusedAndNothingIsWritten(): void
    {
        $content = $this->draft();

        try {
            $this->service->apply($content, PublicationRequest::schedule($this->at('-1 minute')));
            self::fail('A past date was accepted.');
        } catch (InvalidPublicationDatesException) {
        }

        self::assertSame(PublicationState::Draft, $this->service->statusOf($this->reloaded($content))->state);
    }

    public function testAScheduleAtTheCurrentSecondIsRefused(): void
    {
        $this->expectException(InvalidPublicationDatesException::class);

        $this->service->apply($this->draft(), PublicationRequest::schedule($this->clock->now()));
    }

    public function testCancellingAScheduleGivesADraftBack(): void
    {
        $content = $this->draft();
        $this->service->apply($content, PublicationRequest::schedule($this->at('+1 day'), $this->at('+2 days')));

        $status = $this->service->apply($content, PublicationRequest::cancel());

        self::assertSame(PublicationState::Draft, $status->state);
        $row = $this->reloaded($content);
        self::assertNull($row->getPublishAt());
        self::assertNull($row->getUnpublishAt());
    }

    public function testAPublishedRowIsTakenOfflineNow(): void
    {
        $content = $this->published();

        $status = $this->service->apply($content, PublicationRequest::unpublish());

        self::assertSame(PublicationState::Unpublished, $status->state);
        $row = $this->reloaded($content);
        self::assertSame(0, (int) $row->getVisible());
        self::assertSame(self::NOW, $row->getUnpublishAt('Y-m-d H:i:s'));
    }

    public function testAnUnpublishedRowIsPublishedAgain(): void
    {
        $content = $this->published();
        $this->service->apply($content, PublicationRequest::unpublish());
        $this->clock->modify('+1 hour');

        $status = $this->service->apply($content, PublicationRequest::publish());

        self::assertSame(PublicationState::Published, $status->state);
        $row = $this->reloaded($content);
        self::assertSame('2026-10-21 13:00:00', $row->getPublishAt('Y-m-d H:i:s'));
        self::assertNull($row->getUnpublishAt(), 'the past withdrawal is forgotten');
    }

    public function testAnUnpublishedRowCanBeScheduledAgain(): void
    {
        $content = $this->published();
        $this->service->apply($content, PublicationRequest::unpublish());

        $status = $this->service->apply($content, PublicationRequest::schedule($this->at('+1 day')));

        self::assertSame(PublicationState::Scheduled, $status->state);
        self::assertNull($this->reloaded($content)->getUnpublishAt());
    }

    public function testAWithdrawalIsScheduledOnAPublishedRow(): void
    {
        $content = $this->published();

        $status = $this->service->apply($content, PublicationRequest::scheduleUnpublication($this->at('+1 week')));

        self::assertSame(PublicationState::Published, $status->state);
        self::assertSame('2026-10-28 12:00:00', $status->pendingDate()?->format('Y-m-d H:i:s'));
        self::assertSame(1, (int) $this->reloaded($content)->getVisible());
    }

    public function testCancellingOnAPublishedRowForgetsItsWithdrawal(): void
    {
        $content = $this->published();
        $this->service->apply($content, PublicationRequest::scheduleUnpublication($this->at('+1 week')));

        $status = $this->service->apply($content, PublicationRequest::cancel());

        self::assertSame(PublicationState::Published, $status->state);
        self::assertNull($status->pendingDate());
        self::assertNull($this->reloaded($content)->getUnpublishAt());
    }

    public function testAWithdrawalCannotBeScheduledOnARowThatIsNotOnline(): void
    {
        $this->expectException(InvalidPublicationTransitionException::class);

        $this->service->apply($this->draft(), PublicationRequest::scheduleUnpublication($this->at('+1 week')));
    }

    public function testPublishingEarlyKeepsAWithdrawalStillAhead(): void
    {
        $content = $this->draft();
        $this->service->apply($content, PublicationRequest::schedule($this->at('+1 day'), $this->at('+3 days')));

        $status = $this->service->apply($content, PublicationRequest::publish());

        self::assertSame(PublicationState::Published, $status->state);
        self::assertSame('2026-10-24 12:00:00', $this->reloaded($content)->getUnpublishAt('Y-m-d H:i:s'));
    }

    public function testPublishingWithAWithdrawalInThePastIsRefused(): void
    {
        $this->expectException(InvalidPublicationDatesException::class);

        $this->service->apply($this->draft(), PublicationRequest::publish($this->at('-1 day')));
    }

    public function testAContentWithNoBodyInTheDefaultLanguageStaysHidden(): void
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $content->setLocale('fr_FR')->setTitle('Contenu')->setDescription('<p>Un corps en français seulement</p>')->save();

        try {
            $this->service->apply($content, PublicationRequest::publish());
            self::fail('A content with no body in the default language went online.');
        } catch (EmptyContentPublicationException $exception) {
            self::assertSame((int) $content->getId(), $exception->contentId);
            self::assertSame('en_US', $exception->locale);
            self::assertStringNotContainsString('français', $exception->getMessage(), 'the message never quotes a body');
        }

        self::assertSame(0, (int) $content->getVisible(), 'the row in memory is read back from the database');
        self::assertSame(PublicationState::Draft, $this->service->statusOf($this->reloaded($content))->state);
    }

    public function testAFolderGoesThroughTheSameTransitions(): void
    {
        $folder = $this->factory->folder(0, ['visible' => 0]);

        self::assertSame(PublicationState::Draft, $this->service->statusOf($folder)->state);
        self::assertSame(PublicationState::Scheduled, $this->service->apply($folder, PublicationRequest::schedule($this->at('+1 day')))->state);
        self::assertSame(PublicationState::Published, $this->service->apply($folder, PublicationRequest::publish())->state);
        self::assertSame(PublicationState::Unpublished, $this->service->apply($folder, PublicationRequest::unpublish())->state);

        $row = FolderQuery::create()->findPk($folder->getId());
        self::assertInstanceOf(Folder::class, $row);
        self::assertSame(0, (int) $row->getVisible());
        self::assertSame(self::NOW, $row->getUnpublishAt('Y-m-d H:i:s'));
    }

    private function draft(): Content
    {
        $content = $this->factory->content($this->factory->folder(), ['visible' => 0]);
        $content->setLocale('en_US')->setDescription('<p>A body</p>')->save();

        return $content;
    }

    private function published(): Content
    {
        $content = $this->draft();
        $this->service->apply($content, PublicationRequest::publish());

        return $content;
    }

    private function reloaded(Content $content): Content
    {
        $row = ContentQuery::create()->findPk($content->getId());
        self::assertInstanceOf(Content::class, $row);

        return $row;
    }

    private function at(string $modifier): \DateTimeImmutable
    {
        return $this->clock->now()->modify($modifier);
    }
}

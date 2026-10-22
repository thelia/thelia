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
use Thelia\Domain\CMS\Publication\PublicationRequest;
use Thelia\Domain\CMS\Publication\PublicationService;
use Thelia\Domain\CMS\Publication\PublicationState;
use Thelia\Domain\CMS\Publication\PublicationStatus;
use Thelia\Domain\CMS\Publication\VisibilityDatesAligner;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Test\IntegrationTestCase;

/**
 * Every publication date is written at the second, whatever fraction the clock carries:
 * MySQL rounds a fraction up where MariaDB truncates it, and a withdrawal written at
 * 12:00:00.7 read as a future one, so as "scheduled", for the rest of that second.
 */
final class PublicationMomentTest extends IntegrationTestCase
{
    /** Read in the timezone of PHP, as the clock of the shop is; a mock clock would otherwise tick in UTC. */
    private const string NOW_WITH_A_FRACTION = '2026-10-21 12:00:00.700000';

    public function testAWithdrawalIsWrittenAtTheSecondAndReadsAsUnpublishedAtOnce(): void
    {
        $clock = new MockClock(self::NOW_WITH_A_FRACTION, date_default_timezone_get());
        $service = new PublicationService($clock);
        $content = $this->publishedContent();

        $status = $service->apply($content, PublicationRequest::unpublish());

        self::assertSame(PublicationState::Unpublished, $status->state);
        $row = ContentQuery::create()->findPk($content->getId());
        self::assertSame('2026-10-21 12:00:00.000000', $row->getUnpublishAt('Y-m-d H:i:s.u'));
        self::assertSame(PublicationState::Unpublished, $service->statusOf($row)->state, 'read back under the same clock, still within the second');
    }

    public function testAPublicationIsWrittenAtTheSecond(): void
    {
        $service = new PublicationService(new MockClock(self::NOW_WITH_A_FRACTION, date_default_timezone_get()));
        $content = $this->publishedContent();
        $service->apply($content, PublicationRequest::unpublish());

        $service->apply($content, PublicationRequest::publish());

        self::assertSame('2026-10-21 12:00:00.000000', ContentQuery::create()->findPk($content->getId())->getPublishAt('Y-m-d H:i:s.u'));
    }

    public function testADateGivenWithAFractionIsScheduledAtTheSecond(): void
    {
        $service = new PublicationService(new MockClock(self::NOW_WITH_A_FRACTION, date_default_timezone_get()));
        $content = $this->publishedContent();

        $service->apply($content, PublicationRequest::schedule(new \DateTimeImmutable('2026-10-22 09:30:15.900000')));

        self::assertSame('2026-10-22 09:30:15.000000', ContentQuery::create()->findPk($content->getId())->getPublishAt('Y-m-d H:i:s.u'));
    }

    /**
     * The column keeps no timezone either: a date given in another zone is brought to the
     * zone of PHP, which every "now" of the publication is read in, by the setters of the
     * models, so every path that writes a date is covered.
     */
    public function testTheModelsWriteADateInTheTimezoneOfPhpAtTheSecondWhateverTheZoneGiven(): void
    {
        $given = new \DateTimeImmutable('2026-12-01T09:00:00.700+02:00');
        $expected = $given->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s').'.000000';

        $content = (new Content())->setPublishAt($given)->setUnpublishAt('2026-12-01T10:00:00.700+02:00');
        $folder = (new Folder())->setPublishAt($given)->setUnpublishAt($given->getTimestamp());

        self::assertSame($expected, $content->getPublishAt('Y-m-d H:i:s.u'));
        self::assertSame(date_default_timezone_get(), $content->getPublishAt()?->getTimezone()->getName());
        self::assertSame($given->setTimezone(new \DateTimeZone(date_default_timezone_get()))->modify('+1 hour')->format('Y-m-d H:i:s').'.000000', $content->getUnpublishAt('Y-m-d H:i:s.u'), 'a string is read the same way');
        self::assertSame($expected, $folder->getPublishAt('Y-m-d H:i:s.u'));
        self::assertSame($expected, $folder->getUnpublishAt('Y-m-d H:i:s.u'), 'a timestamp too');
        self::assertNull((new Content())->setPublishAt(null)->getPublishAt());
    }

    /** An empty field, as a form posts it, clears the date like the generated setter does, rather than reading as now. */
    public function testAnEmptyStringClearsTheDate(): void
    {
        $content = (new Content())->setPublishAt('2026-12-01 09:00:00')->setUnpublishAt('2026-12-01 10:00:00');
        $folder = (new Folder())->setPublishAt('2026-12-01 09:00:00')->setUnpublishAt('2026-12-01 10:00:00');

        $content->setPublishAt('')->setUnpublishAt('');
        $folder->setPublishAt('')->setUnpublishAt('');

        self::assertNull($content->getPublishAt());
        self::assertNull($content->getUnpublishAt());
        self::assertNull($folder->getPublishAt());
        self::assertNull($folder->getUnpublishAt());
    }

    public function testTheAlignerWritesTheWithdrawalAtTheSecond(): void
    {
        $content = $this->publishedContent();
        $content->setVisible(0);

        VisibilityDatesAligner::alignOn($content, new \DateTimeImmutable(self::NOW_WITH_A_FRACTION));

        self::assertSame('2026-10-21 12:00:00.000000', $content->getUnpublishAt('Y-m-d H:i:s.u'));
        self::assertSame(PublicationState::Unpublished, PublicationStatus::ofPublishable($content, new \DateTimeImmutable(self::NOW_WITH_A_FRACTION))->state);
    }

    public function testAStateIsReadAtTheSecondOnBothSides(): void
    {
        // What MySQL would store for a withdrawal written at 12:00:00.7, read at 12:00:00.9:
        // a withdrawal still ahead, so a draft. That is what writing at the second prevents.
        $status = PublicationStatus::of(false, null, new \DateTimeImmutable('2026-10-21 12:00:01'), new \DateTimeImmutable('2026-10-21 12:00:00.900000'));
        self::assertSame(PublicationState::Draft, $status->state);

        $status = PublicationStatus::of(false, null, new \DateTimeImmutable('2026-10-21 12:00:00.700000'), new \DateTimeImmutable('2026-10-21 12:00:00.100000'));
        self::assertSame(PublicationState::Unpublished, $status->state, 'two moments of the same second are the same moment');
    }

    private function publishedContent(): Content
    {
        $factory = $this->createFixtureFactory();
        $content = $factory->content($factory->folder(), ['visible' => 1]);
        $content->setLocale('en_US')->setDescription('<p>A body</p>')->setPublishAt(new \DateTimeImmutable('2026-10-01 10:00:00'))->save();

        return $content;
    }
}

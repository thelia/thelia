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

namespace Thelia\Domain\CMS\Publication;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Content\ContentUpdatePublicationEvent;
use Thelia\Core\Event\Folder\FolderUpdatePublicationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\CMS\Publication\Exception\PublicationException;
use Thelia\Log\Tlog;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderTableMap;

/**
 * Acts on the dates that have come: puts online the rows scheduled for a moment now
 * past, takes offline the published rows whose withdrawal date is past.
 *
 * Each row goes through its publication event, so the caches are purged and a module
 * listening to the events sees the change. A content the body guard refuses stays
 * scheduled and is reported, by a dry run as well; the pass goes on with the others. A
 * second pass right after finds nothing left to do.
 */
final readonly class ScheduledPublicationRunner
{
    public const string CONTENT = 'content';
    public const string FOLDER = 'folder';

    public function __construct(
        private ClockInterface $clock,
        private EventDispatcherInterface $dispatcher,
        private PublicationGuard $guard = new PublicationGuard(),
    ) {
    }

    public function run(bool $dryRun = false, int $batchSize = 100): ScheduledPublicationReport
    {
        $report = new ScheduledPublicationReport();
        $now = $this->clock->now();

        foreach ($this->inBatches(fn (int $afterId) => $this->contentsToPublish($now, $afterId, $batchSize)) as $content) {
            $this->release(self::CONTENT, $content, $this->publicationOf($content, $now), $dryRun, $report);
        }

        foreach ($this->inBatches(fn (int $afterId) => $this->contentsToUnpublish($now, $afterId, $batchSize)) as $content) {
            $this->release(self::CONTENT, $content, PublicationRequest::unpublish(self::dateOf($content->getUnpublishAt())), $dryRun, $report);
        }

        foreach ($this->inBatches(fn (int $afterId) => $this->foldersToPublish($now, $afterId, $batchSize)) as $folder) {
            $this->release(self::FOLDER, $folder, $this->publicationOf($folder, $now), $dryRun, $report);
        }

        foreach ($this->inBatches(fn (int $afterId) => $this->foldersToUnpublish($now, $afterId, $batchSize)) as $folder) {
            $this->release(self::FOLDER, $folder, PublicationRequest::unpublish(self::dateOf($folder->getUnpublishAt())), $dryRun, $report);
        }

        return $report;
    }

    private function release(string $type, Content|Folder $row, PublicationRequest $request, bool $dryRun, ScheduledPublicationReport $report): void
    {
        $state = PublicationAction::Unpublish === $request->action ? PublicationState::Unpublished : PublicationState::Published;

        if ($dryRun) {
            // The guard runs on the save a real pass would make: a dry run asks it
            // itself, so it announces the refusal the pass would meet, without writing.
            try {
                if ($row instanceof Content && PublicationState::Published === $state) {
                    $this->guard->assertHasBody($row, null, PublicationGuard::defaultLocale());
                }

                $report->changed($type, (int) $row->getId(), $state);
            } catch (PublicationException $exception) {
                $report->refused($type, (int) $row->getId(), $exception->getMessage());
            }

            return;
        }

        try {
            if ($row instanceof Content) {
                $this->dispatcher->dispatch(new ContentUpdatePublicationEvent($row, $request), TheliaEvents::CONTENT_UPDATE_PUBLICATION);
            } else {
                $this->dispatcher->dispatch(new FolderUpdatePublicationEvent($row, $request), TheliaEvents::FOLDER_UPDATE_PUBLICATION);
            }

            $report->changed($type, (int) $row->getId(), $state);
        } catch (PublicationException $exception) {
            $report->refused($type, (int) $row->getId(), $exception->getMessage());
            // An error, not a warning: a publication that was promised did not happen, and
            // the default level of the log only keeps errors.
            Tlog::getInstance()->error(\sprintf('Scheduled publication of %s #%d refused: %s', $type, $row->getId(), $exception->getMessage()));
        }
    }

    /**
     * The publication of a row whose date has come: the date waited for is the one written,
     * a withdrawal date still ahead is kept by the service.
     */
    private function publicationOf(Content|Folder $row, \DateTimeImmutable $now): PublicationRequest
    {
        return PublicationRequest::publish(null, self::dateOf($row->getPublishAt()) ?? $now);
    }

    /**
     * Reads the rows by batches, after the last id read: a row the pass changed no longer
     * matches the query, so an offset would skip one row per row changed.
     *
     * @template T of Content|Folder
     *
     * @param \Closure(int): iterable<T> $batch
     *
     * @return iterable<T>
     */
    private function inBatches(\Closure $batch): iterable
    {
        $afterId = 0;

        do {
            $count = 0;

            foreach ($batch($afterId) as $row) {
                ++$count;
                $afterId = (int) $row->getId();

                yield $row;
            }
        } while ($count > 0);
    }

    /** @return iterable<Content> */
    private function contentsToPublish(\DateTimeImmutable $now, int $afterId, int $batchSize): iterable
    {
        return $this->toPublish(ContentQuery::create(), ContentTableMap::COL_ID, ContentTableMap::COL_VISIBLE, ContentTableMap::COL_PUBLISH_AT, ContentTableMap::COL_UNPUBLISH_AT, $now, $afterId, $batchSize)->find();
    }

    /** @return iterable<Content> */
    private function contentsToUnpublish(\DateTimeImmutable $now, int $afterId, int $batchSize): iterable
    {
        return $this->toUnpublish(ContentQuery::create(), ContentTableMap::COL_ID, ContentTableMap::COL_VISIBLE, ContentTableMap::COL_UNPUBLISH_AT, $now, $afterId, $batchSize)->find();
    }

    /** @return iterable<Folder> */
    private function foldersToPublish(\DateTimeImmutable $now, int $afterId, int $batchSize): iterable
    {
        return $this->toPublish(FolderQuery::create(), FolderTableMap::COL_ID, FolderTableMap::COL_VISIBLE, FolderTableMap::COL_PUBLISH_AT, FolderTableMap::COL_UNPUBLISH_AT, $now, $afterId, $batchSize)->find();
    }

    /** @return iterable<Folder> */
    private function foldersToUnpublish(\DateTimeImmutable $now, int $afterId, int $batchSize): iterable
    {
        return $this->toUnpublish(FolderQuery::create(), FolderTableMap::COL_ID, FolderTableMap::COL_VISIBLE, FolderTableMap::COL_UNPUBLISH_AT, $now, $afterId, $batchSize)->find();
    }

    /**
     * Scheduled rows whose date has come, and that no past withdrawal date takes offline:
     * a window that closed while nothing ran is over, not to be opened.
     */
    private function toPublish(ModelCriteria $query, string $id, string $visible, string $publishAt, string $unpublishAt, \DateTimeImmutable $now, int $afterId, int $batchSize): ModelCriteria
    {
        $moment = $now->format('Y-m-d H:i:s');

        return $query
            ->where(\sprintf('(%s IS NULL OR %s <> 1)', $visible, $visible))
            ->where(\sprintf('%s IS NOT NULL AND %s <= ?', $publishAt, $publishAt), $moment)
            ->where(\sprintf('(%s IS NULL OR %s > ?)', $unpublishAt, $unpublishAt), $moment)
            ->where(\sprintf('%s > ?', $id), $afterId)
            ->orderBy($id, Criteria::ASC)
            ->limit($batchSize);
    }

    private function toUnpublish(ModelCriteria $query, string $id, string $visible, string $unpublishAt, \DateTimeImmutable $now, int $afterId, int $batchSize): ModelCriteria
    {
        return $query
            ->where(\sprintf('%s = 1', $visible))
            ->where(\sprintf('%s IS NOT NULL AND %s <= ?', $unpublishAt, $unpublishAt), $now->format('Y-m-d H:i:s'))
            ->where(\sprintf('%s > ?', $id), $afterId)
            ->orderBy($id, Criteria::ASC)
            ->limit($batchSize);
    }

    private static function dateOf(string|\DateTimeInterface|null $date): ?\DateTimeImmutable
    {
        if (null === $date) {
            return null;
        }

        return \is_string($date) ? new \DateTimeImmutable($date) : \DateTimeImmutable::createFromInterface($date);
    }
}

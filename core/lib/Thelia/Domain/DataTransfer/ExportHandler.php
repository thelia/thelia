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

namespace Thelia\Domain\DataTransfer;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Archiver\ClosableArchiverInterface;
use Thelia\Core\Event\ExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Exception\HandlerUnavailableException;
use Thelia\Domain\DataTransfer\Exception\JobRefusedException;
use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;
use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Model\Export;
use Thelia\Model\ExportCategory;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;

/**
 * Class ExportHandler.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ExportHandler
{
    /** Told the rows written while export() runs: processExport() keeps its signature. */
    private ?\Closure $onProgress = null;

    /** The rows the last export wrote: told again while its images and documents are added. */
    private int $rowsWritten = 0;

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected ExportCachePurger $exportCachePurger,
    ) {
    }

    public function getExport(int $exportId, bool $dispatchException = false): ?Export
    {
        $export = (new ExportQuery())->findPk($exportId);

        if (null === $export && $dispatchException) {
            throw new \ErrorException(Translator::getInstance()->trans('There is no id "%id" in the exports', ['%id' => $exportId]));
        }

        return $export;
    }

    public function getExportByRef(string $exportRef, bool $dispatchException = false): ?Export
    {
        $export = (new ExportQuery())->findOneByRef($exportRef);

        if (null === $export && $dispatchException) {
            throw new \ErrorException(Translator::getInstance()->trans('There is no ref "%ref" in the exports', ['%ref' => $exportRef]));
        }

        return $export;
    }

    public function getCategory(int $exportCategoryId, bool $dispatchException = false): ?ExportCategory
    {
        $category = (new ExportCategoryQuery())->findPk($exportCategoryId);

        if (null === $category && $dispatchException) {
            throw new \ErrorException(Translator::getInstance()->trans('There is no id "%id" in the export categories', ['%id' => $exportCategoryId]));
        }

        return $category;
    }

    public function export(
        Export $export,
        SerializerInterface $serializer,
        ?ArchiverInterface $archiver = null,
        ?Lang $language = null,
        bool $includeImages = false,
        bool $includeDocuments = false,
        ?array $rangeDate = null,
        ?\Closure $onProgress = null,
    ): ExportEvent {
        if (!$export->isHandlerAvailable()) {
            throw new HandlerUnavailableException(Translator::getInstance()->trans('The export "%ref" cannot be run: its handler class "%class" is not available. The module that provided it has probably been removed.', ['%ref' => $export->getRef(), '%class' => $export->getHandleClass()]));
        }

        $instance = $this->configuredInstance($export, $archiver, $language, $includeImages, $includeDocuments, $rangeDate);
        $event = new ExportEvent($instance, $serializer, $archiver);

        $this->eventDispatcher->dispatch($event, TheliaEvents::EXPORT_BEGIN);

        $this->onProgress = $onProgress;
        $this->rowsWritten = 0;
        $written = [];

        // A file left behind by a failure holds customer data: whatever fails, a listener
        // of the export included, what was written goes with it.
        try {
            $filePath = $this->processExport($event->getExport(), $event->getSerializer());
            $written[] = $filePath;
            $event->setFilePath($filePath);

            $this->eventDispatcher->dispatch($event, TheliaEvents::EXPORT_FINISHED);

            $eventArchiver = $event->getArchiver();

            if ($eventArchiver instanceof ArchiverInterface) {
                $eventArchiver->create($filePath);
                $written[] = $eventArchiver->getArchivePath();
                $this->archive($event, $eventArchiver, $filePath, $includeImages, $includeDocuments);
            }

            $this->eventDispatcher->dispatch($event, TheliaEvents::EXPORT_SUCCESS);
        } catch (\Throwable $exception) {
            $this->discard($event->getArchiver(), $written);

            throw $exception;
        } finally {
            // The handler outlives the export in a worker: nothing is told to the next one.
            $this->onProgress = null;
            $this->rowsWritten = 0;
        }

        // A listener may have handed the export over elsewhere: what it was made of in the
        // export folder would never be downloaded.
        $this->discardWhatTheExportLeft($event, $written);

        return $event;
    }

    /**
     * @param array{start?: mixed, end?: mixed}|null $rangeDate
     */
    private function configuredInstance(
        Export $export,
        ?ArchiverInterface $archiver,
        ?Lang $language,
        bool $includeImages,
        bool $includeDocuments,
        ?array $rangeDate,
    ): AbstractExport {
        $instance = $export->getHandleClassInstance();
        $instance->setLang($language);

        if ($archiver instanceof ArchiverInterface) {
            if ($includeImages && $instance->hasImages()) {
                $instance->setExportImages(true);
            }

            if ($includeDocuments && $instance->hasDocuments()) {
                $instance->setExportDocuments(true);
            }
        }

        $rangeDate = $this->resolveRangeDate($rangeDate);

        if (null !== $rangeDate) {
            $instance->setRangeDate($rangeDate);
        }

        return $instance;
    }

    /**
     * @param list<string> $written the files the export wrote
     */
    private function discardWhatTheExportLeft(ExportEvent $event, array $written): void
    {
        $kept = ExportCachePurger::resolve($event->getFilePath());

        foreach ($written as $file) {
            $resolved = ExportCachePurger::resolve($file);

            if (null === $resolved || $resolved === $kept) {
                continue;
            }

            // The export is done: a file the purge will sweep never fails it.
            try {
                (new Filesystem())->remove($resolved);
            } catch (\Throwable $notRemoved) {
                Tlog::getInstance()->addWarning(\sprintf('A file an export left was not removed: %s', JobFailureMessage::forLog($notRemoved)));
            }
        }
    }

    /**
     * @param list<string> $written the files the failed export wrote
     */
    private function discard(?ArchiverInterface $archiver, array $written): void
    {
        // A zip still open writes what it holds when it is let go: dropped first, so
        // nothing comes back once the files are removed.
        if ($archiver instanceof ClosableArchiverInterface) {
            try {
                $archiver->discard();
            } catch (\Throwable $notDiscarded) {
                Tlog::getInstance()->addWarning(\sprintf('The archive of a failed export could not be let go: %s', JobFailureMessage::forLog($notDiscarded)));
            }
        }

        (new Filesystem())->remove($written);
    }

    /**
     * The period of an export, as the export reads it: a start and an end given as a
     * year and a month (the form of the back office) become the first second of that
     * month and the last second of the end month. Dates are kept as they are.
     *
     * @param array{start?: mixed, end?: mixed}|null $rangeDate
     *
     * @return array{start?: mixed, end?: mixed}|null
     */
    public function resolveRangeDate(?array $rangeDate): ?array
    {
        if (null === $rangeDate) {
            return null;
        }

        return [
            'start' => self::boundOf($rangeDate['start'] ?? null, false),
            'end' => self::boundOf($rangeDate['end'] ?? null, true),
        ];
    }

    /**
     * A bound given as a date stays as it is; one given as the year and month of the
     * back-office form becomes the first, or the last, moment of that month.
     */
    private static function boundOf(mixed $bound, bool $endOfMonth): mixed
    {
        if (!$bound || $bound instanceof \DateTimeInterface) {
            return $bound;
        }

        $year = \is_array($bound) ? (string) ($bound['year'] ?? '') : '';
        $month = \is_array($bound) ? (string) ($bound['month'] ?? '') : '';

        // A year of four digits and a month of the year: anything else would not parse,
        // or would roll over into another date, and the export would quietly cover
        // another period.
        $valid = \is_array($bound)
            && ('' === $year || (ctype_digit($year) && 4 === \strlen($year)))
            && ('' === $month || (ctype_digit($month) && (int) $month >= 1 && (int) $month <= 12));

        $date = $valid ? \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            ('' !== $year ? $year : (new \DateTime())->format('Y')).'-'.('' !== $month ? $month : (new \DateTime())->format('m')).($endOfMonth ? '-1 23:59:59' : '-1 00:00:00'),
        ) : false;

        if (false === $date) {
            throw new JobRefusedException(Translator::getInstance()->trans('The dates of the export are not valid.'));
        }

        if ($endOfMonth && $date instanceof \DateTime) {
            $date->add(new \DateInterval('P1M'))->sub(new \DateInterval('P1D'));
        }

        return $date;
    }

    /**
     * Tells the progress callback given to export(), if any, the number of rows written
     * every DataTransferProgress::STEP rows and once at the end.
     */
    protected function processExport(AbstractExport $export, SerializerInterface $serializer): string
    {
        $onProgress = $this->onProgress;

        $filename = \sprintf(
            '%s-%s-%s.%s',
            (new \DateTime())->format('Ymd'),
            uniqid('', true),
            $export->getFileName(),
            $serializer->getExtension(),
        );

        $filePath = ExportCachePurger::directory().DS.$filename;

        $fileSystem = new Filesystem();
        $fileSystem->mkdir(\dirname($filePath));

        $this->exportCachePurger->purgeOldExportFiles(\dirname($filePath));

        $file = new \SplFileObject($filePath, 'w+b');

        // A file left half written holds customer data: it goes with the failure.
        try {
            $written = $this->writeRows($export, $serializer, $file, $onProgress);
            $this->rowsWritten = $written;

            // The caller records the count, and may fail to: the file goes with it too.
            if (null !== $onProgress) {
                $onProgress($written);
            }
        } catch (\Throwable $exception) {
            unset($file);
            (new Filesystem())->remove($filePath);

            throw $exception;
        }

        unset($file);

        return $filePath;
    }

    /**
     * @return int the rows written
     */
    private function writeRows(AbstractExport $export, SerializerInterface $serializer, \SplFileObject $file, ?\Closure $onProgress): int
    {
        $written = 0;
        $serializer->prepareFile($file);

        foreach ($export as $idx => $data) {
            if (!\is_array($data) || empty($data)) {
                continue;
            }
            $data = $export->beforeSerialize($data);
            $data = $export->applyOrderAndAliases($data);
            $data = $serializer->serialize($data);
            $data = $export->afterSerialize($data);

            if ($idx > 0) {
                $data = $serializer->separator().$data;
            }

            $file->fwrite($data);

            if (0 === ++$written % DataTransferProgress::STEP && null !== $onProgress) {
                $onProgress($written);
            }
        }

        $serializer->finalizeFile($file);

        return $written;
    }

    private function archive(ExportEvent $event, ArchiverInterface $archiver, string $filePath, bool $includeImages, bool $includeDocuments): void
    {
        if ($includeImages && $event->getExport()->hasImages()) {
            $this->processExportImages($event->getExport(), $archiver);
        }

        if ($includeDocuments && $event->getExport()->hasDocuments()) {
            $this->processExportDocuments($event->getExport(), $archiver);
        }

        // A zip is written when it is saved, and says it failed only by what save() returns.
        if (!$archiver->add($filePath)->save()) {
            throw new \RuntimeException(\sprintf('The archive %s of the export was not written.', basename($archiver->getArchivePath())));
        }

        // A tar writes as it goes and keeps its handle: let go once the archive is whole.
        if ($archiver instanceof ClosableArchiverInterface) {
            $archiver->close();
        }

        $event->setFilePath($archiver->getArchivePath());

        // The archive holds the export: the file it was made of would keep customer data twice.
        (new Filesystem())->remove($filePath);
    }

    protected function processExportImages(AbstractExport $export, ArchiverInterface $archiver): void
    {
        $added = 0;

        foreach ($export->getImagesPaths() as $imagePath) {
            $archiver->add($imagePath);
            $this->signOfLife(++$added);
        }
    }

    protected function processExportDocuments(AbstractExport $export, ArchiverInterface $archiver): void
    {
        $added = 0;

        foreach ($export->getDocumentsPaths() as $documentPath) {
            $archiver->add($documentPath);
            $this->signOfLife(++$added);
        }
    }

    /**
     * The files added to an archive are not rows: the caller is told the rows written
     * again, every DataTransferProgress::STEP files, so a long archive is never taken for
     * a dead one.
     */
    private function signOfLife(int $added): void
    {
        if (null !== $this->onProgress && 0 === $added % DataTransferProgress::STEP) {
            ($this->onProgress)($this->rowsWritten);
        }
    }
}

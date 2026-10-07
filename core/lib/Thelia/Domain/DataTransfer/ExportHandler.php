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
use Thelia\Core\Event\ExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Exception\HandlerUnavailableException;
use Thelia\Domain\DataTransfer\Exception\JobRefusedException;
use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;
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

        $instance = $export->getHandleClassInstance();

        // Configure handle class
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

        // Process export
        $event = new ExportEvent($instance, $serializer, $archiver);

        $this->eventDispatcher->dispatch($event, TheliaEvents::EXPORT_BEGIN);

        $this->onProgress = $onProgress;

        try {
            $filePath = $this->processExport($event->getExport(), $event->getSerializer());
        } finally {
            $this->onProgress = null;
        }

        $event->setFilePath($filePath);

        $this->eventDispatcher->dispatch($event, TheliaEvents::EXPORT_FINISHED);

        if ($event->getArchiver() instanceof ArchiverInterface) {
            // Create archive
            $event->getArchiver()->create($filePath);

            // Add images
            if ($includeImages && $event->getExport()->hasImages()) {
                $this->processExportImages($event->getExport(), $event->getArchiver());
            }

            // Add documents
            if ($includeDocuments && $event->getExport()->hasDocuments()) {
                $this->processExportDocuments($event->getExport(), $event->getArchiver());
            }

            // Finalize archive
            $event->getArchiver()->add($filePath)->save();

            // Change returned file path
            $event->setFilePath($event->getArchiver()->getArchivePath());
        }

        $this->eventDispatcher->dispatch($event, TheliaEvents::EXPORT_SUCCESS);

        return $event;
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

        $filePath = THELIA_CACHE_DIR.'export'.DS.$filename;

        $fileSystem = new Filesystem();
        $fileSystem->mkdir(\dirname($filePath));

        $this->exportCachePurger->purgeOldExportFiles(\dirname($filePath));

        $file = new \SplFileObject($filePath, 'w+b');

        $serializer->prepareFile($file);
        $written = 0;

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

            if (null !== $onProgress && 0 === ++$written % DataTransferProgress::STEP) {
                $onProgress($written);
            }
        }

        $serializer->finalizeFile($file);

        if (null !== $onProgress) {
            $onProgress($written);
        }

        unset($file);

        return $filePath;
    }

    protected function processExportImages(AbstractExport $export, ArchiverInterface $archiver): void
    {
        foreach ($export->getImagesPaths() as $imagePath) {
            $archiver->add($imagePath);
        }
    }

    protected function processExportDocuments(AbstractExport $export, ArchiverInterface $archiver): void
    {
        foreach ($export->getDocumentsPaths() as $documentPath) {
            $archiver->add($documentPath);
        }
    }
}

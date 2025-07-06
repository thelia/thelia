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
namespace Thelia\Service\DataTransfer;

use ErrorException;

use DateInterval;
use SplFileObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Event\ExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\AbstractExport;
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
    public function __construct(protected EventDispatcherInterface $eventDispatcher)
    {
    }

    public function getExport(int $exportId, bool $dispatchException = false): ?Export
    {
        $export = (new ExportQuery())->findPk($exportId);

        if ($export === null && $dispatchException) {
            throw new ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%id" in the exports',
                    [
                        '%id' => $exportId,
                    ]
                )
            );
        }

        return $export;
    }

    public function getExportByRef(string $exportRef, bool $dispatchException = false): ?Export
    {
        $export = (new ExportQuery())->findOneByRef($exportRef);

        if ($export === null && $dispatchException) {
            throw new ErrorException(
                Translator::getInstance()->trans(
                    'There is no ref "%ref" in the exports',
                    [
                        '%ref' => $exportRef,
                    ]
                )
            );
        }

        return $export;
    }

    public function getCategory(int $exportCategoryId, bool $dispatchException = false): ?ExportCategory
    {
        $category = (new ExportCategoryQuery())->findPk($exportCategoryId);

        if ($category === null && $dispatchException) {
            throw new ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%id" in the export categories',
                    [
                        '%id' => $exportCategoryId,
                    ]
                )
            );
        }

        return $category;
    }

    public function export(
        Export $export,
        SerializerInterface $serializer,
        ArchiverInterface $archiver = null,
        Lang $language = null,
        bool $includeImages = false,
        bool $includeDocuments = false,
        array $rangeDate = null
    ): ExportEvent {
        $exportHandleClass = $export->getHandleClass();

        /** @var AbstractExport $instance */
        $instance = new $exportHandleClass();

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

        if ($rangeDate['start'] && !($rangeDate['start'] instanceof DateTime)) {
            $startYear = $rangeDate['start']['year'] !== '' ? $rangeDate['start']['year'] : (new \DateTime())->format('Y');
            $startMonth = $rangeDate['start']['month'] !== '' ? $rangeDate['start']['month'] : (new \DateTime())->format('m');
            $rangeDate['start'] = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $startYear.'-'.$startMonth.'-1 00:00:00'
            );
        }

        if ($rangeDate['end'] && !($rangeDate['end'] instanceof DateTime)) {
            $endYear = $rangeDate['end']['year'] !== '' ? $rangeDate['end']['year'] : (new \DateTime())->format('Y');
            $endMonth = $rangeDate['end']['month'] !== '' ? $rangeDate['end']['month'] : (new \DateTime())->format('m');
            $rangeDate['end'] = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $endYear.'-'.$endMonth.'-1 23:59:59'
            );

            if ($rangeDate['end'] instanceof DateTime) {
                $rangeDate['end']
                    ->add(new DateInterval('P1M'))
                    ->sub(new DateInterval('P1D'));
            }
        }

        $instance->setRangeDate($rangeDate);

        // Process export
        $event = new ExportEvent($instance, $serializer, $archiver);

        $this->eventDispatcher->dispatch($event, TheliaEvents::EXPORT_BEGIN);

        $filePath = $this->processExport($event->getExport(), $event->getSerializer());

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

    protected function processExport(AbstractExport $export, SerializerInterface $serializer): string
    {
        $filename = sprintf(
            '%s-%s-%s.%s',
            (new \DateTime())->format('Ymd'),
            uniqid('', true),
            $export->getFileName(),
            $serializer->getExtension()
        );

        $filePath = THELIA_CACHE_DIR.'export'.DS.$filename;

        $fileSystem = new Filesystem();
        $fileSystem->mkdir(\dirname($filePath));

        $file = new SplFileObject($filePath, 'w+b');

        $serializer->prepareFile($file);

        foreach ($export as $idx => $data) {
            $data = $export->beforeSerialize($data);
            $data = $export->applyOrderAndAliases($data);
            $data = $serializer->serialize($data);
            $data = $export->afterSerialize($data);

            if ($idx > 0) {
                $data = $serializer->separator().$data;
            }

            $file->fwrite($data);
        }

        $serializer->finalizeFile($file);

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

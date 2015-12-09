<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Handler;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Event\ExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\ImportExport\Export\DocumentsExportInterface;
use Thelia\ImportExport\Export\ImagesExportInterface;
use Thelia\Model\Export;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;

/**
 * Class ExportHandler
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ExportHandler
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface An event dispatcher interface instance
     */
    protected $eventDispatcher;

    /**
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *  An event dispatcher interface instance
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Get export model based on given ID
     *
     * @param integer $exportId          An export ID
     * @param boolean $dispatchException Dispatch exception if model doesn't exist
     *
     * @throws \ErrorException
     *
     * @return null|\Thelia\Model\Export
     */
    public function getExport($exportId, $dispatchException = false)
    {
        $export = (new ExportQuery)->findPk($exportId);

        if ($export === null && $dispatchException) {
            throw new \ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%id" in the exports',
                    [
                        '%id' => $exportId
                    ]
                )
            );
        }

        return $export;
    }

    /**
     * Get export category model based on given ID
     *
     * @param integer $exportCategoryId  An export category ID
     * @param boolean $dispatchException Dispatch exception if model doesn't exist
     *
     * @throws \ErrorException
     *
     * @return null|\Thelia\Model\ExportCategory
     */
    public function getCategory($exportCategoryId, $dispatchException = false)
    {
        $category = (new ExportCategoryQuery)->findPk($exportCategoryId);

        if ($category === null && $dispatchException) {
            throw new \ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%id" in the export categories',
                    [
                        '%id' => $exportCategoryId
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
        $includeImages = false,
        $includeDocuments = false,
        $rangeDate = null
    ) {
//        if ($rangeDate !== null) {
//            $handler->setRangeDate($rangeDate);
//        }

        $exportHandleClass = $export->getHandleClass();

        /** @var \Thelia\ImportExport\Export\AbstractExport $instance */
        $instance = new $exportHandleClass;
        $instance->setLang($language);

        $event = new ExportEvent($instance, $serializer, $archiver);

        $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_BEGIN, $event);

        $filePath = $this->processExport($event->getExport(), $event->getSerializer());

        $event->setFilePath($filePath);

        $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_FINISHED, $event);

        if ($event->getArchiver() !== null) {
            // Todo
            /*if ($includeImages && $handler instanceof ImagesExportInterface) {
                $this->processExportImages($handler, $archiveBuilder);

                $handler->setImageExport(true);
            }

            // Todo
            if ($includeDocuments && $handler instanceof DocumentsExportInterface) {
                $this->processExportDocuments($handler, $archiveBuilder);

                $handler->setDocumentExport(true);
            }*/

            $event->getArchiver()
                ->create($filePath)
                ->add($filePath)
                ->save()
            ;
            $event->setFilePath($event->getArchiver()->getArchivePath());
        }

        $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_SUCCESS, $event);

        return $event;
    }

    /**
     * Process export
     *
     * @param \Thelia\ImportExport\Export\AbstractExport  $export     An export instance
     * @param \Thelia\Core\Serializer\SerializerInterface $serializer A serializer instance
     *
     * @return string Export file path
     */
    protected function processExport(AbstractExport $export, SerializerInterface $serializer)
    {
        set_time_limit(0);

        $filename = sprintf(
            '%s-%s-%s.%s',
            (new \DateTime)->format('Ymd'),
            uniqid(),
            $export->getFileName(),
            $serializer->getExtension()
        );

        $filePath = THELIA_CACHE_DIR . 'export' . DS . $filename;

        $fileSystem = new Filesystem;
        $fileSystem->mkdir(dirname($filePath));

        $file = new \SplFileObject($filePath, 'w+b');

        $serializer->prepareFile($file);

        foreach ($export as $idx => $data) {
            $data = $export->beforeSerialize($data);
            $data = $export->applyOrderAndAliases($data);
            $data = $serializer->serialize($data);
            $data = $export->afterSerialize($data);

            if ($idx > 0) {
                $data = $serializer->separator() . $data;
            }

            $file->fwrite($data);
        }

        $serializer->finalizeFile($file);

        unset($file);

        return $filePath;
    }

    /*---------- Copied code from old controller - NOT YET REWORKED ----------*/

    /**
     * @param ImagesExportInterface  $handler
     * @param AbstractArchiveBuilder $archiveBuilder
     *
     * Procedure that add images in the export's archive
     */
    protected function processExportImages(ImagesExportInterface $handler, AbstractArchiveBuilder $archiveBuilder)
    {
        foreach ($handler->getImagesPaths() as $name => $documentPath) {
            $archiveBuilder->addFile(
                $documentPath,
                $handler::IMAGES_DIRECTORY,
                is_integer($name) ? null : $name
            );
        }
    }

    /**
     * @param DocumentsExportInterface $handler
     * @param AbstractArchiveBuilder   $archiveBuilder
     *
     * Procedure that add documents in the export's archive
     */
    protected function processExportDocuments(DocumentsExportInterface $handler, AbstractArchiveBuilder $archiveBuilder)
    {
        foreach ($handler->getDocumentsPaths() as $name => $documentPath) {
            $archiveBuilder->addFile(
                $documentPath,
                $handler::DOCUMENTS_DIRECTORY,
                is_integer($name) ? null : $name
            );
        }
    }
}

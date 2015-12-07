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
        AbstractArchiveBuilder $archiver = null,
        Lang $lang = null,
        $includeImages = false,
        $includeDocuments = false,
        $rangeDate = null
    ) {
//        if ($rangeDate !== null) {
//            $handler->setRangeDate($rangeDate);
//        }

        $exportHandleClass = $export->getHandleClass();

        $event = new ExportEvent(new $exportHandleClass, $serializer, $archiver);

        $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_BEGIN, $event);

        $filePath = $this->processExport($event->getExport(), $event->getSerializer());

        $event->setFilePath($filePath);

        $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_FINISHED, $event);

        if ($event->archiver !== null) {
            // Todo
            /*if ($includeImages && $handler instanceof ImagesExportInterface) {
                $this->processExportImages($handler, $archiveBuilder);

                $handler->setImageExport(true);
            }

            if ($includeDocuments && $handler instanceof DocumentsExportInterface) {
                $this->processExportDocuments($handler, $archiveBuilder);

                $handler->setDocumentExport(true);
            }

            $data = $handler
                ->buildData($lang)
                ->setLang($lang)
            ;

            $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_BEFORE_ENCODE, $event);

            $formattedContent = $formatter
                ->setOrder($handler->getOrder())
                ->encode($data)
            ;

            $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_AFTER_ENCODE, $event->setContent($formattedContent));


            $archiveBuilder->addFileFromString(
                $event->getContent(),
                $filename
            );*/
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

        $fd = fopen($filePath, 'w');

        fwrite($fd, $serializer->wrapOpening());

        foreach ($export as $idx => $data) {
            if ($idx > 0) {
                fwrite($fd, $serializer->separator());
            }

            $export->beforeSerialize($data);
            $serializedData = $serializer->serialize($data);
            $export->afterSerialize($serializedData);

            fwrite($fd, $serializedData);
        }

        fwrite($fd, $serializer->wrapClosing());

        fclose($fd);

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

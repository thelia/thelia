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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Thelia\Core\Event\ExportEvent;
use Thelia\Core\Event\ImportExport;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\ImportExport\Export\DocumentsExportInterface;
use Thelia\ImportExport\Export\ImagesExportInterface;
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

    /*---------- Copied code from old controller - NOT YET REWORKED ----------*/

    public function export(
        AbstractExport $handler,
        SerializerInterface $serializer,
        AbstractArchiveBuilder $archiveBuilder = null,
        Lang $lang = null,
        $includeImages = false,
        $includeDocuments = false,
        $rangeDate = null
    ) {
//        if ($rangeDate !== null) {
//            $handler->setRangeDate($rangeDate);
//        }

        $event = new ExportEvent($handler, $serializer);

        $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_BEGIN, $event);

        $this->processExport($handler, $serializer);

        $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_SUCCESS, $event);


        /*$event = new ImportExport($serializer, $handler);

        $filename = $handler->getFilename() . '.' . $serializer->getExtension();

        if ($archiveBuilder === null) {
            $data = $handler->buildData($lang);

            $event->setData($data);
            $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_BEFORE_ENCODE, $event);

            $serializedContent = $serializer
                ->setOrder($handler->getOrder())
                ->encode($data)
            ;

            $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_AFTER_ENCODE, $event->setContent($serializedContent));

            return new Response(
                $event->getContent(),
                200,
                [
                    'Content-Type' => $serializer->getMimeType(),
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]
            );
        } else {
            $event->setArchiveBuilder($archiveBuilder);

            if ($includeImages && $handler instanceof ImagesExportInterface) {
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
            );

            return $archiveBuilder->buildArchiveResponse($handler->getFilename());
        }*/
    }

    protected function processExport($handler, SerializerInterface $serializer)
    {
//        $filename = sprintf(
//            '%s-%s-%s.%s',
//            (new \DateTime)->format('Ymd'),
//            uniqid(),
//            $handler->getFilename(),
//            $serializer->getExtension()
//        );
        echo $serializer->wrapOpening();

        foreach ($handler as $test) {
            echo $serializer->serialize($test);
        }

        echo $serializer->wrapClosing();
        exit;
    }

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

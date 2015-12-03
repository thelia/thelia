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
use Symfony\Component\Translation\TranslatorInterface;
use Thelia\Core\Event\ImportExport;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\FileFormat\Formatting\AbstractFormatter;
use Thelia\Core\HttpFoundation\Response;
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
     * @var \Symfony\Component\Translation\TranslatorInterface A translator interface instance
     */
    protected $translator;

    /**
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *  An event dispatcher interface instance
     * @param \Symfony\Component\Translation\TranslatorInterface          $translator
     *  A translator interface instance
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
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
                $this->translator->trans(
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
                $this->translator->trans(
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
        \Thelia\ImportExport\Export\ExportHandler $handler,
        AbstractFormatter $formatter,
        AbstractArchiveBuilder $archiveBuilder = null,
        Lang $lang = null,
        $includeImages = false,
        $includeDocuments = false,
        $rangeDate = null
    ) {
        /**
         * Build an event containing the formatter and the handler.
         * Used for specific configuration (e.g: XML node names)
         */

        $event = new ImportExport($formatter, $handler);

        $filename = $handler->getFilename() . '.' . $formatter->getExtension();

        if ($rangeDate !== null) {
            $handler->setRangeDate($rangeDate);
        }

        if ($archiveBuilder === null) {
            $data = $handler->buildData($lang);

            $event->setData($data);
            $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_BEFORE_ENCODE, $event);

            $formattedContent = $formatter
                ->setOrder($handler->getOrder())
                ->encode($data)
            ;

            $this->eventDispatcher->dispatch(TheliaEvents::EXPORT_AFTER_ENCODE, $event->setContent($formattedContent));

            return new Response(
                $event->getContent(),
                200,
                [
                    "Content-Type" => $formatter->getMimeType(),
                    "Content-Disposition" =>
                        "attachment; filename=\"" . $filename . "\"",
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
        }
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

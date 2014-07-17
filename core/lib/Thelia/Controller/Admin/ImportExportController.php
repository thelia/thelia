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

namespace Thelia\Controller\Admin;
use Thelia\Core\Event\ImportExport as ImportExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\FileFormat\Formatting\AbstractFormatter;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\ArchiveBuilder;
use Thelia\Core\Template\Loop\Export as ExportLoop;
use Thelia\Core\Template\Loop\Import as ImportLoop;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ExportForm;
use Thelia\Form\ImportForm;
use Thelia\ImportExport\AbstractHandler;
use Thelia\ImportExport\Export\DocumentsExportInterface;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\ImportExport\Export\ImagesExportInterface;
use Thelia\Model\ExportQuery;
use Thelia\Model\ImportQuery;

/**
 * Class ImportExportController
 * @package Thelia\Controller\Admin
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportExportController extends BaseAdminController
{
    /** @var \Thelia\Core\FileFormat\Archive\ArchiveBuilderManager */
    protected $archiveBuilderManager;

    /** @var \Thelia\Core\FileFormat\Formatting\FormatterManager */
    protected $formatterManager;

    public function hydrate()
    {
        $this->archiveBuilderManager = $this->container->get("thelia.manager.archive_builder_manager");
        $this->formatterManager = $this->container->get("thelia.manager.formatter_manager");
    }

    /**
     * @param  integer  $id
     * @return Response
     *
     * This method is called when the route /admin/import/{id}
     * is called with a POST request.
     */
    public function import($id)
    {
        if (null === $import = $this->getImport($id)) {
            return $this->render("404");
        }

        /**
         * Get needed services
         */
        $this->hydrate();

        $form = new ImportForm($this->getRequest());
        $errorMessage = null;
        $successMessage = null;


        try {
            $boundForm = $this->validateForm($form);

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $file = $boundForm->get("file_upload")->getData();

            /**
             * We have to check the extension manually because of composed file formats as tar.gz or tar.bz2
             */
            $name = $file->getClientOriginalName();
            $nameLength = strlen($name);


            $handler = $import->getHandleClassInstance($this->container);
            $types = $handler->getHandledTypes();

            $formats =
                $this->formatterManager->getExtensionsByTypes($types, true) +
                $this->archiveBuilderManager->getExtensions(true)
            ;

            $uploadFormat = null;

            /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
            $formatter = null;

            /** @var \Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder $archiveBuilder */
            $archiveBuilder = null;

            foreach ($formats as $format) {
                $formatLength = strlen($format);
                if ($nameLength >= $formatLength  && substr($name, -$formatLength) === $formatLength) {
                    $uploadFormat = $format;

                    $flip = array_flip($format);

                    try {
                        $formatter = $this->formatterManager->get($flip[$format]);
                    } catch(\OutOfBoundsException $e) {}

                    try {
                        $archiveBuilder = $this->archiveBuilderManager->get($flip[$format]);
                    } catch(\OutOfBoundsException $e) {}

                    break;
                }
            }

            $splitName = explode(".", $name);
            $ext = "";

            if (1 < $limit = count($splitName)) {
                $ext = "." . $splitName[$limit-1];
            }

            if ($uploadFormat === null) {


                throw new FormValidationException(
                    $this->getTranslator()->trans(
                        "The extension \"%ext\" is not allowed",
                        [
                            "%ext" => $ext
                        ]
                    )
                );
            }

            if ($archiveBuilder !== null) {
                /**
                 * If the file is an archive
                 */
                $archiveBuilder->loadArchive($file->getPathname());
                $content = null;

                /**
                 * TODO: HANDLE
                 */

            } elseif ($formatter !== null) {
                /**
                 * If the file isn't
                 */

                $content = file_get_contents($file->getPathname());

            } else {
                throw new \ErrorException(
                    $this->getTranslator()->trans(
                        "There's a problem, the extension \"%ext\" has been found, ".
                        "but has no formatters nor archive builder",
                        [
                            "%ext" => $ext
                        ]
                    )
                );
            }

            $data = $formatter->decode($content);

            // Dispatch event

            $handler->retrieveFromFormatterData($data);

            $successMessage = $this->getTranslator()->trans("Import successfully done");

        } catch(FormValidationException $e) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($e);
        } catch(\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        if ($successMessage !== null) {
            $this->getParserContext()->set("success_message", $successMessage);
        }

        if ($errorMessage !== null) {
            $form->setErrorMessage($errorMessage);

            $this->getParserContext()
                ->addForm($form)
                ->setGeneralError($errorMessage)
            ;
        }

        return $this->importView($id);
    }

    /**
     * @param  integer  $id
     * @return Response
     *
     * This method is called when the route /admin/export/{id}
     * is called with a POST request.
     */
    public function export($id)
    {
        if (null === $export = $this->getExport($id)) {
            return $this->render("404");
        }

        /**
         * Get needed services
         */
        $this->hydrate();

        /**
         * Get the archive builders
         */
        $archiveBuilders = [];
        foreach ($this->archiveBuilderManager->getNames() as $archiveBuilder) {
            $archiveBuilders[$archiveBuilder] = $archiveBuilder;
        }

        /**
         * Define and validate the form
         */
        $form = new ExportForm($this->getRequest());
        $errorMessage = null;

        try {
            $boundForm = $this->validateForm($form);

            $archiveBuilder = null;

            /**
             * Get the formatter and the archive builder if we have to compress the file(s)
             */

            /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
            $formatter = $this->formatterManager->get(
                $boundForm->get("formatter")->getData()
            );

            if ($boundForm->get("do_compress")->getData()) {
                /** @var \Thelia\Core\FileFormat\Archive\ArchiveBuilderInterface $archiveBuilder */
                $archiveBuilder = $this->archiveBuilderManager->get(
                    $boundForm->get("archive_builder")->getData()
                );
            }

            /**
             * Return the generated Response
             */

            return $this->processExport(
                $formatter,
                $export->getHandleClassInstance($this->container),
                $archiveBuilder,
                $boundForm->get("images")->getData(),
                $boundForm->get("documents")->getData()
            );

        } catch (FormValidationException $e) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        /**
         * If has an error, display it
         */
        if (null !== $errorMessage) {
            $form->setErrorMessage($errorMessage);

            $this->getParserContext()
                ->addForm($form)
                ->setGeneralError($errorMessage)
            ;
        }

        return $this->exportView($id);
    }

    /**
     * @param AbstractFormatter $formatter
     * @param ExportHandler $handler
     * @param AbstractArchiveBuilder $archiveBuilder
     * @param bool $includeImages
     * @param bool $includeDocuments
     * @return Response
     *
     * Processes an export by returning a response with the export's content.
     */
    protected function processExport(
        AbstractFormatter $formatter,
        ExportHandler $handler,
        AbstractArchiveBuilder $archiveBuilder = null,
        $includeImages = false,
        $includeDocuments = false
    ) {
        /**
         * Build an event containing the formatter and the handler.
         * Used for specific configuration (e.g: XML node names)
         */
        $data = $handler->buildFormatterData();
        $event = new ImportExportEvent($formatter, $handler , $data);

        $filename = $formatter::FILENAME . "." . $formatter->getExtension();

        if ($archiveBuilder === null) {
            $this->dispatch(TheliaEvents::BEFORE_EXPORT, $event);

            $formattedContent = $formatter->encode($data);

            return new Response(
                $formattedContent,
                200,
                [
                    "Content-Type" => $formatter->getMimeType(),
                    "Content-Disposition" =>
                        "attachment; filename=\"" . $filename . "\"",
                ]
            );
        } else {
            $event->setArchiveBuilder($archiveBuilder);
            $this->dispatch(TheliaEvents::BEFORE_EXPORT, $event);

            $formattedContent = $formatter->encode($data);

            if ($includeImages && $handler instanceof ImagesExportInterface) {
                $this->processExportImages($handler, $archiveBuilder);
            }

            if ($includeDocuments && $handler instanceof DocumentsExportInterface) {
                $this->processExportDocuments($handler, $archiveBuilder);
            }

            $archiveBuilder->addFileFromString(
                $formattedContent, $filename
            );

            return $archiveBuilder->buildArchiveResponse($formatter::FILENAME);
        }
    }

    /**
     * @param ImagesExportInterface $handler
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
     * @param AbstractArchiveBuilder $archiveBuilder
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

    /**
     * @param  integer  $id
     * @return Response
     *
     * This method is called when the route /admin/import/{id}
     * is called with a GET request.
     *
     * It returns a modal view if the request is an AJAX one,
     * otherwise it generates a "normal" back-office page
     */
    public function importView($id)
    {
        if (null === $import = $this->getImport($id)) {
            return $this->render("404");
        }

        /**
         * Use the loop to inject the same vars in Smarty
         */
        $loop = new ImportLoop($this->container);

        $loop->initializeArgs([
            "export" => $import->getId()
        ]);

        $query = $loop->buildModelCriteria();
        $result= $query->find();

        $results = $loop->parseResults(
            new LoopResult($result)
        );

        $parserContext = $this->getParserContext();

        /** @var \Thelia\Core\Template\Element\LoopResultRow $row */
        foreach ($results as $row) {
            foreach ($row->getVarVal() as $name=>$value) {
                $parserContext->set($name, $value);
            }
        }

        /**
         * Get allowed formats
         */
        /** @var \Thelia\ImportExport\AbstractHandler $handler */
        $this->hydrate();
        $handler = $import->getHandleClassInstance($this->container);

        $types = $handler->getHandledTypes();

        $formats =
            $this->formatterManager->getExtensionsByTypes($types, true) +
            $this->archiveBuilderManager->getExtensions(true)
        ;

        /**
         * Get allowed mime types (used for the "Search a file" window
         */
        $mimeTypes =
            $this->formatterManager->getMimeTypesByTypes($types) +
            $this->archiveBuilderManager->getMimeTypes()
        ;

        /**
         * Inject them in smarty
         */
        $parserContext
            ->set( "ALLOWED_MIME_TYPES", implode(",", $mimeTypes))
            ->set("ALLOWED_EXTENSIONS", implode(", ", $formats))
        ;

        /** Then render the form */
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->render("ajax/import-modal");
        } else {
            return $this->render("import-page");
        }
    }

    /**
     * @param  integer  $id
     * @return Response
     *
     * This method is called when the route /admin/export/{id}
     * is called with a GET request.
     *
     * It returns a modal view if the request is an AJAX one,
     * otherwise it generates a "normal" back-office page
     */
    public function exportView($id)
    {
        if (null === $export = $this->getExport($id)) {
            return $this->render("404");
        }

        /**
         * Use the loop to inject the same vars in Smarty
         */
        $loop = new ExportLoop($this->container);

        $loop->initializeArgs([
            "export" => $export->getId()
        ]);

        $query = $loop->buildModelCriteria();
        $result= $query->find();

        $results = $loop->parseResults(
            new LoopResult($result)
        );

        $parserContext = $this->getParserContext();

        /** @var \Thelia\Core\Template\Element\LoopResultRow $row */
        foreach ($results as $row) {
            foreach ($row->getVarVal() as $name=>$value) {
                $parserContext->set($name, $value);
            }
        }

        /**
         * Inject conditions in smarty,
         * It is used to display or not the checkboxes "Include images"
         * and "Include documents"
         */
        $this->getParserContext()
            ->set("HAS_IMAGES", $export->hasImages($this->container))
            ->set("HAS_DOCUMENTS", $export->hasDocuments($this->container))
        ;

        /** Then render the form */
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->render("ajax/export-modal");
        } else {
            return $this->render("export-page");
        }
    }

    /**
     * @param $id
     * @return array|mixed|\Thelia\Model\Export
     *
     * This method is a shortcut to get an export model
     */
    protected function getExport($id)
    {
        $export = ExportQuery::create()
            ->findPk($id)
        ;

        return $export;
    }

    /**
     * @param $id
     * @return array|mixed|\Thelia\Model\Import
     *
     * This method is a shortcut to get an import model
     */
    protected function getImport($id)
    {
        $export = ImportQuery::create()
            ->findPk($id)
        ;

        return $export;
    }

}

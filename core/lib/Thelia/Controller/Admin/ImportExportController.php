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
use Thelia\Core\Event\ImportExport\Export as ExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Export as ExportLoop;
use Thelia\Core\Template\Loop\Import as ImportLoop;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ExportForm;
use Thelia\ImportExport\DocumentsExportInterface;
use Thelia\ImportExport\ImagesExportInterface;
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

    public function import($id)
    {
        if (null === $import = $this->getImport($id))  {
            return $this->render("404");
        }

        /**
         * Get needed services
         */
        $this->hydrate();
    }

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
         * Get the allowed formatters to inject them into the form
         */
        $handler = $export->getHandleClassInstance($this->container);

        $types = $handler->getHandledType();

        if (!is_array($types)) {
            $types = [$types];
        }

        $formatters = [];
        /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
        foreach ($this->formatterManager->getAll() as $formatter) {
            if (in_array($formatter->getExportType(), $types)) {
                $formatters[$formatter->getName()] = $formatter->getName();
            }
        }

        /**
         * Define and validate the form
         */
        $form = new ExportForm(
            $this->getRequest(),
            "form",
            array(),
            array(),
            $archiveBuilders,
            $formatters
        );
        $errorMessage = null;

        try {
            $boundForm = $this->validateForm($form);

            $data = $handler->buildFormatterData();

            $formatter = $this->formatterManager->get(
                $boundForm->get("formatter")->getData()
            );

            /**
             * Build an event containing the formatter and the handler.
             * Used for specific configuration (e.g: XML node names)
             */
            $event = new ExportEvent($formatter, $handler);

            $filename = $formatter::FILENAME . "." . $formatter->getExtension();

            if (!$boundForm->get("do_compress")->getData()) {

                if (!$boundForm->get("do_compress")->getData()) {
                    /**
                     * Dispatch the event
                     */
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
                }
            } else {
                /** @var \Thelia\Core\FileFormat\Archive\ArchiveBuilderInterface $archiveBuilder */
                $archiveBuilder = $this->archiveBuilderManager->get(
                    $boundForm->get("archive_builder")->getData()
                );

                $event->setArchiveBuilder($archiveBuilder);
                $this->dispatch(TheliaEvents::BEFORE_EXPORT, $event);

                $formattedContent = $formatter->encode($data);

                $includeImages = $boundForm->get("images")->getData();
                $includeDocuments = $boundForm->get("documents")->getData();

                if ($includeImages && $handler instanceof ImagesExportInterface) {
                    foreach ($handler->getImagesPaths() as $name => $documentPath) {
                        $archiveBuilder->addFile(
                            $documentPath,
                            $handler::IMAGES_DIRECTORY,
                            is_integer($name) ? null : $name
                        );
                    }
                }

                if ($includeDocuments && $handler instanceof DocumentsExportInterface) {
                    foreach ($handler->getDocumentsPaths() as $name => $documentPath) {
                        $archiveBuilder->addFile(
                            $documentPath,
                            $handler::DOCUMENTS_DIRECTORY,
                            is_integer($name) ? null : $name
                        );
                    }
                }

                $archiveBuilder->addFileFromString(
                    $formattedContent, $filename
                );

                return $archiveBuilder->buildArchiveResponse($formatter::FILENAME);
            }

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

    public function importView($id)
    {
        if (null === $import = $this->getImport($id))  {
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

        /** Then render the form */
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->render("ajax/import-modal");
        } else {
            return $this->render("import-page");
        }
    }

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

    protected function getExport($id)
    {
        $export = ExportQuery::create()
            ->findPk($id)
        ;

        return $export;
    }

    protected function getImport($id)
    {
        $export = ImportQuery::create()
            ->findPk($id)
        ;

        return $export;
    }
}
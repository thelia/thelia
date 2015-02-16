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
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilderManagerTrait;
use Thelia\Core\FileFormat\Formatting\AbstractFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterManagerTrait;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Export as ExportLoop;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\ImportExport\Export\DocumentsExportInterface;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\ImportExport\Export\ImagesExportInterface;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Class ExportController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <manu@thelia.net>
 */
class ExportController extends BaseAdminController
{
    use ArchiveBuilderManagerTrait;
    use FormatterManagerTrait;

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::VIEW])) {
            return $response;
        }

        $this->setOrders();

        return $this->render('export');
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
            return $this->pageNotFound();
        }

        /**
         * Get needed services
         */
        $archiveBuilderManager = $this->getArchiveBuilderManager($this->container);
        $formatterManager = $this->getFormatterManager($this->container);

        /**
         * Define and validate the form
         */
        $form = $this->createForm(AdminForm::EXPORT);
        $errorMessage = null;

        try {
            $boundForm = $this->validateForm($form);

            $lang = LangQuery::create()->findPk(
                $boundForm->get("language")->getData()
            );

            $archiveBuilder = null;

            /**
             * Get the formatter and the archive builder if we have to compress the file(s)
             */

            /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
            $formatter = $formatterManager->get(
                $boundForm->get("formatter")->getData()
            );

            if ($boundForm->get("do_compress")->getData()) {
                /** @var \Thelia\Core\FileFormat\Archive\ArchiveBuilderInterface $archiveBuilder */
                $archiveBuilder = $archiveBuilderManager->get(
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
                $lang,
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
     * @param Lang $lang
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
        Lang $lang = null,
        $includeImages = false,
        $includeDocuments = false
    ) {
        /**
         * Build an event containing the formatter and the handler.
         * Used for specific configuration (e.g: XML node names)
         */

        $event = new ImportExportEvent($formatter, $handler);

        $filename = $formatter::FILENAME . "." . $formatter->getExtension();

        if ($archiveBuilder === null) {
            $data = $handler->buildData($lang);

            $event->setData($data);
            $this->dispatch(TheliaEvents::EXPORT_BEFORE_ENCODE, $event);

            $formattedContent = $formatter
                ->setOrder($handler->getOrder())
                ->encode($data)
            ;

            $this->dispatch(TheliaEvents::EXPORT_AFTER_ENCODE, $event->setContent($formattedContent));

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

            $this->dispatch(TheliaEvents::EXPORT_BEFORE_ENCODE, $event);

            $formattedContent = $formatter
                ->setOrder($handler->getOrder())
                ->encode($data)
            ;

            $this->dispatch(TheliaEvents::EXPORT_AFTER_ENCODE, $event->setContent($formattedContent));


            $archiveBuilder->addFileFromString(
                $event->getContent(),
                $filename
            );

            return $archiveBuilder->buildArchiveResponse($formatter::FILENAME);
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
            return $this->pageNotFound();
        }

        /**
         * Use the loop to inject the same vars in the Template engine
         */
        $loop = new ExportLoop($this->container);

        $loop->initializeArgs([
            "id" => $export->getId()
        ]);

        $query = $loop->buildModelCriteria();
        $result= $query->find();

        $results = $loop->parseResults(
            new LoopResult($result)
        );

        $parserContext = $this->getParserContext();

        /** @var \Thelia\Core\Template\Element\LoopResultRow $row */
        foreach ($results as $row) {
            foreach ($row->getVarVal() as $name => $value) {
                $parserContext->set($name, $value);
            }
        }

        /**
         * Inject conditions in template engine,
         * It is used to display or not the checkboxes "Include images"
         * and "Include documents"
         */
        $this->getParserContext()
            ->set("HAS_IMAGES", $export->hasImages($this->container))
            ->set("HAS_DOCUMENTS", $export->hasDocuments($this->container))
            ->set("CURRENT_LANG_ID", $this->getSession()->getLang()->getId())
        ;

        /** Then render the form */
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->render("ajax/export-modal");
        } else {
            return $this->render("export-page");
        }
    }


    public function changePosition()
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $query = $this->getRequest()->query;

        $mode = $query->get("mode");
        $id = $query->get("id");
        $value = $query->get("value");

        $this->getExport($id);

        $event = new UpdatePositionEvent($id, $this->getMode($mode), $value);
        $this->dispatch(TheliaEvents::EXPORT_CHANGE_POSITION, $event);

        $this->setOrders(null, "manual");

        return $this->render('export');
    }

    public function changeCategoryPosition()
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $query = $this->getRequest()->query;

        $mode = $query->get("mode");
        $id = $query->get("id");
        $value = $query->get("value");

        $this->getCategory($id);

        $event = new UpdatePositionEvent($id, $this->getMode($mode), $value);
        $this->dispatch(TheliaEvents::EXPORT_CATEGORY_CHANGE_POSITION, $event);

        $this->setOrders("manual");

        return $this->render('export');
    }

    public function getMode($action)
    {
        if ($action === "up") {
            $mode = UpdatePositionEvent::POSITION_UP;
        } elseif ($action === "down") {
            $mode = UpdatePositionEvent::POSITION_DOWN;
        } else {
            $mode = UpdatePositionEvent::POSITION_ABSOLUTE;
        }

        return $mode;
    }

    protected function setOrders($category = null, $export = null)
    {
        if ($category === null) {
            $category = $this->getRequest()->query->get("category_order", "manual");
        }

        if ($export === null) {
            $export = $this->getRequest()->query->get("export_order", "manual");
        }

        $this->getParserContext()
            ->set("category_order", $category)
        ;

        $this->getParserContext()
            ->set("export_order", $export)
        ;
    }

    protected function getExport($id)
    {
        $export = ExportQuery::create()->findPk($id);

        if (null === $export) {
            throw new \ErrorException(
                $this->getTranslator()->trans(
                    "There is no id \"%id\" in the exports",
                    [
                        "%id" => $id
                    ]
                )
            );
        }

        return $export;
    }

    protected function getCategory($id)
    {
        $category = ExportCategoryQuery::create()->findPk($id);

        if (null === $category) {
            throw new \ErrorException(
                $this->getTranslator()->trans(
                    "There is no id \"%id\" in the export categories",
                    [
                        "%id" => $id
                    ]
                )
            );
        }

        return $category;
    }
}

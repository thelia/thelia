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
use Thelia\Core\FileFormat\Archive\ArchiveBuilderManager;
use Thelia\Core\FileFormat\Archive\ArchiveBuilderManagerTrait;
use Thelia\Core\FileFormat\Formatting\AbstractFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterManager;
use Thelia\Core\FileFormat\Formatting\FormatterManagerTrait;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Import as ImportLoop;
use Thelia\Exception\FileNotFoundException;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\ImportExport\Import\ImportHandler;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Class ImportController
 * @package Thelia\Controller\Admin
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportController extends BaseAdminController
{
    use FormatterManagerTrait;
    use ArchiveBuilderManagerTrait;

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::VIEW])) {
            return $response;
        }

        $this->setOrders();

        return $this->render('import');
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
            return $this->pageNotFound();
        }

        $archiveBuilderManager = $this->getArchiveBuilderManager($this->container);
        $formatterManager = $this->getFormatterManager($this->container);
        $handler = $import->getHandleClassInstance($this->container);

        /**
         * Get needed services
         */
        $form = $this->createForm(AdminForm::IMPORT);
        $errorMessage = null;
        $successMessage = null;

        try {
            $boundForm = $this->validateForm($form);

            $lang = LangQuery::create()->findPk(
                $boundForm->get("language")->getData()
            );

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $file = $boundForm->get("file_upload")->getData();

            /**
             * We have to check the extension manually because of composed file formats as tar.gz or tar.bz2
             */
            $name = $file->getClientOriginalName();

            $tools = $this->retrieveFormatTools(
                $name,
                $handler,
                $formatterManager,
                $archiveBuilderManager
            );

            /** @var AbstractArchiveBuilder $archiveBuilder */
            $archiveBuilder = $tools["archive_builder"];

            /** @var AbstractFormatter $formatter */
            $formatter = $tools["formatter"];

            if ($archiveBuilder !== null) {
                /**
                 * If the file is an archive, load it and try to find the file.
                 */
                $archiveBuilder = $archiveBuilder->loadArchive($file->getPathname());

                $contentAndFormat = $this->getFileContentInArchive(
                    $archiveBuilder,
                    $formatterManager,
                    $tools["types"]
                );

                $formatter = $contentAndFormat["formatter"];
                $content = $contentAndFormat["content"];
            } elseif ($formatter !== null) {
                /**
                 * If the file isn't an archive
                 */
                $content = file_get_contents($file->getPathname());
            } else {
                throw new \ErrorException(
                    $this->getTranslator()->trans(
                        "There's a problem, the extension \"%ext\" has been found, but has no formatters nor archive builder",
                        [
                            "%ext" => $tools["extension"],
                        ]
                    )
                );
            }

            /**
             * Process the import: dispatch events, format the file content and let the handler do it's job.
             */
            $successMessage = $this->processImport(
                $content,
                $handler,
                $formatter,
                $archiveBuilder,
                $lang
            );
        } catch (FormValidationException $e) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
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

    public function getFileContentInArchive(
        AbstractArchiveBuilder $archiveBuilder,
        FormatterManager $formatterManager,
        array $types
    ) {
        $content = null;

        /**
         * Check expected file names for each formatter
         */

        $fileNames = [];
        /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
        foreach ($formatterManager->getFormattersByTypes($types) as $formatter) {
            $fileName = $formatter::FILENAME . "." . $formatter->getExtension();
            $fileNames[] = $fileName;

            if ($archiveBuilder->hasFile($fileName)) {
                $content = $archiveBuilder->getFileContent($fileName);
                break;
            }
        }

        if ($content === null) {
            throw new FileNotFoundException(
                $this->getTranslator()->trans(
                    "Your archive must contain one of these file and doesn't: %files",
                    [
                        "%files" => implode(", ", $fileNames),
                    ]
                )
            );
        }

        return array(
            "formatter" => $formatter,
            "content" => $content,
        );
    }

    public function retrieveFormatTools(
        $fileName,
        ImportHandler $handler,
        FormatterManager $formatterManager,
        ArchiveBuilderManager $archiveBuilderManager
    ) {
        $nameLength = strlen($fileName);

        $types = $handler->getHandledTypes();

        $formats =
            $formatterManager->getExtensionsByTypes($types, true) +
            $archiveBuilderManager->getExtensions(true)
        ;

        $uploadFormat = null;

        /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
        $formatter = null;

        /** @var \Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder $archiveBuilder */
        $archiveBuilder = null;

        foreach ($formats as $objectName => $format) {
            $formatLength = strlen($format);
            $formatExtension = substr($fileName, -$formatLength);

            if ($nameLength >= $formatLength  && $formatExtension === $format) {
                $uploadFormat = $format;


                try {
                    $formatter = $formatterManager->get($objectName);
                } catch (\OutOfBoundsException $e) {
                }

                try {
                    $archiveBuilder = $archiveBuilderManager->get($objectName);
                } catch (\OutOfBoundsException $e) {
                }

                break;
            }
        }

        $this->checkFileExtension($fileName, $uploadFormat);

        return array(
            "formatter" => $formatter,
            "archive_builder" => $archiveBuilder,
            "extension" => $uploadFormat,
            "types" => $types,
        );
    }

    public function checkFileExtension($fileName, $uploadFormat)
    {
        if ($uploadFormat === null) {
            $splitName = explode(".", $fileName);
            $ext = "";

            if (1 < $limit = count($splitName)) {
                $ext = "." . $splitName[$limit-1];
            }

            throw new FormValidationException(
                $this->getTranslator()->trans(
                    "The extension \"%ext\" is not allowed",
                    [
                        "%ext" => $ext
                    ]
                )
            );
        }
    }

    public function processImport(
        $content,
        ImportHandler $handler,
        AbstractFormatter $formatter = null,
        AbstractArchiveBuilder $archiveBuilder = null,
        Lang $lang = null
    ) {
        $event = new ImportExportEvent($formatter, $handler, null, $archiveBuilder);
        $event->setContent($content);

        $this->dispatch(TheliaEvents::IMPORT_BEFORE_DECODE, $event);

        $data = $formatter
            ->decode($event->getContent())
            ->setLang($lang)
        ;

        $event->setContent(null)->setData($data);
        $this->dispatch(TheliaEvents::IMPORT_AFTER_DECODE, $event);

        $errors = $handler->retrieveFromFormatterData($data);

        if (!empty($errors)) {
            throw new \Exception(
                $this->getTranslator()->trans(
                    "Errors occurred while importing the file: %errors",
                    [
                        "%errors" => implode(", ", $errors),
                    ]
                )
            );
        }

        return $this->getTranslator()->trans(
            "Import successfully done, %numb row(s) have been changed",
            [
                "%numb" => $handler->getImportedRows(),
            ]
        );
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
            return $this->pageNotFound();
        }

        /**
         * Use the loop to inject the same vars in the template engine
         */
        $loop = new ImportLoop($this->container);

        $loop->initializeArgs([
            "id" => $id
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
         * Get allowed formats
         */
        /** @var \Thelia\ImportExport\AbstractHandler $handler */
        $handler = $import->getHandleClassInstance($this->container);
        $types = $handler->getHandledTypes();

        $formatterManager = $this->getFormatterManager($this->container);
        $archiveBuilderManager = $this->getArchiveBuilderManager($this->container);

        $formats =
            $formatterManager->getExtensionsByTypes($types, true) +
            $archiveBuilderManager->getExtensions(true)
        ;

        /**
         * Get allowed mime types (used for the "Search a file" window
         */
        $mimeTypes =
            $formatterManager->getMimeTypesByTypes($types) +
            $archiveBuilderManager->getMimeTypes()
        ;

        /**
         * Inject them in template engine
         */
        $parserContext
            ->set("ALLOWED_MIME_TYPES", implode(",", $mimeTypes))
            ->set("ALLOWED_EXTENSIONS", implode(", ", $formats))
            ->set("CURRENT_LANG_ID", $this->getSession()->getLang()->getId())
        ;

        /** Then render the form */
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->render("ajax/import-modal");
        } else {
            return $this->render("import-page");
        }
    }

    protected function setOrders($category = null, $import = null)
    {
        if ($category === null) {
            $category = $this->getRequest()->query->get("category_order", "manual");
        }

        if ($import === null) {
            $import = $this->getRequest()->query->get("import_order", "manual");
        }

        $this->getParserContext()
            ->set("category_order", $category)
        ;

        $this->getParserContext()
            ->set("import_order", $import)
        ;
    }

    public function changePosition()
    {
        if (null !== $response = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $query = $this->getRequest()->query;

        $mode = $query->get("mode");
        $id = $query->get("id");
        $value = $query->get("value");

        $this->getImport($id);

        $event = new UpdatePositionEvent($id, $this->getMode($mode), $value);
        $this->dispatch(TheliaEvents::IMPORT_CHANGE_POSITION, $event);

        return $this->render('import');
    }

    public function changeCategoryPosition()
    {
        if (null !== $response = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $query = $this->getRequest()->query;

        $mode = $query->get("mode");
        $id = $query->get("id");
        $value = $query->get("value");

        $this->getCategory($id);

        $event = new UpdatePositionEvent($id, $this->getMode($mode), $value);
        $this->dispatch(TheliaEvents::IMPORT_CATEGORY_CHANGE_POSITION, $event);

        $this->setOrders("manual");

        return $this->render('import');
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

    protected function getImport($id)
    {
        $import = ImportQuery::create()->findPk($id);

        if (null === $import) {
            throw new \ErrorException(
                $this->getTranslator()->trans(
                    "There is no id \"%id\" in the imports",
                    [
                        "%id" => $id
                    ]
                )
            );
        }

        return $import;
    }

    protected function getCategory($id)
    {
        $category = ImportCategoryQuery::create()->findPk($id);

        if (null === $category) {
            throw new \ErrorException(
                $this->getTranslator()->trans(
                    "There is no id \"%id\" in the import categories",
                    [
                        "%id" => $id
                    ]
                )
            );
        }

        return $category;
    }
}

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

use Thelia\Core\DependencyInjection\Compiler\RegisterArchiverPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;

/**
 * Class ImportController
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ImportController extends BaseAdminController
{
    /**
     * Handle default action, that is, list available imports
     *
     * @param string $_view View to render
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function indexAction($_view = 'import')
    {
        $authResponse  = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::VIEW]);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $this->getParserContext()
            ->set('category_order', $this->getRequest()->query->get('category_order', 'manual'))
            ->set('import_order', $this->getRequest()->query->get('import_order', 'manual'))
        ;

        return $this->render($_view);
    }

    /**
     * Handle import position change action
     *
     * @return \Thelia\Core\HttpFoundation\Response|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function changeImportPositionAction()
    {
        $authResponse  = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE]);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $query = $this->getRequest()->query;

        $this->dispatch(
            TheliaEvents::IMPORT_CHANGE_POSITION,
            new UpdatePositionEvent(
                $query->get('id'),
                $this->matchPositionMode($query->get('mode')),
                $query->get('value')
            )
        );

        return $this->generateRedirectFromRoute('import.list');
    }

    /**
     * Handle import category position change action
     *
     * @return \Thelia\Core\HttpFoundation\Response|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function changeCategoryPositionAction()
    {
        $authResponse  = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE]);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $query = $this->getRequest()->query;

        $this->dispatch(
            TheliaEvents::IMPORT_CATEGORY_CHANGE_POSITION,
            new UpdatePositionEvent(
                $query->get('id'),
                $this->matchPositionMode($query->get('mode')),
                $query->get('value')
            )
        );

        return $this->generateRedirectFromRoute('import.list');
    }

    /**
     * Match position mode string against position mode constant value
     *
     * @param null|string $mode Position mode string
     *
     * @return integer Position mode constant value
     */
    protected function matchPositionMode($mode)
    {
        if ($mode === 'up') {
            return UpdatePositionEvent::POSITION_UP;
        }

        if ($mode === 'down') {
            return UpdatePositionEvent::POSITION_DOWN;
        }

        return UpdatePositionEvent::POSITION_ABSOLUTE;
    }

    /**
     * Display import configuration view
     *
     * @param integer $id An import identifier
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function configureAction($id)
    {
        /** @var \Thelia\Handler\ImportHandler $importHandler */
        $importHandler = $this->container->get('thelia.import.handler');

        $import = $importHandler->getImport($id);
        if ($import === null) {
            return $this->pageNotFound();
        }

        $extensions = [];
        $mimeTypes = [];

        /** @var \Thelia\Core\Serializer\AbstractSerializer $serializer */
        foreach ($this->container->get(RegisterSerializerPass::MANAGER_SERVICE_ID)->getSerializers() as $serializer) {
            $extensions[] = $serializer->getExtension();
            $mimeTypes[] = $serializer->getMimeType();
        }

        /** @var \Thelia\Core\Archiver\AbstractArchiver $archiver */
        foreach ($this->container->get(RegisterArchiverPass::MANAGER_SERVICE_ID)->getArchivers(true) as $archiver) {
            $extensions[] = $archiver->getExtension();
            $mimeTypes[] = $archiver->getMimeType();
        }

        // Render standard view or ajax one
        $templateName = 'import-page';
        if ($this->getRequest()->isXmlHttpRequest()) {
            $templateName = 'ajax/import-modal';
        }

        return $this->render(
            $templateName,
            [
                'importId' => $id,
                'ALLOWED_MIME_TYPES' => implode(', ', $mimeTypes),
                'ALLOWED_EXTENSIONS' => implode(', ', $extensions),
            ]
        );
    }

    /**
     * Handle import action
     *
     * @param integer $id An import identifier
     *
     * @return Response
     */
    public function importAction($id)
    {
        /** @var \Thelia\Handler\Importhandler $importHandler */
        $importHandler = $this->container->get('thelia.import.handler');

        $import = $importHandler->getImport($id);
        if ($import === null) {
            return $this->pageNotFound();
        }

        $form = $this->createForm(AdminForm::IMPORT);

        try {
            $validatedForm = $this->validateForm($form);

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $file = $validatedForm->get('file_upload')->getData();
            $file = $file->move(
                THELIA_CACHE_DIR . 'import' . DS . (new\DateTime)->format('Ymd'),
                uniqid() . '-' . $file->getClientOriginalName()
            );

            $lang = (new LangQuery)->findPk($validatedForm->get('language')->getData());

            $importEvent = $importHandler->import($import, $file, $lang);

            if (count($importEvent->getErrors()) > 0) {
                $this->getSession()->getFlashBag()->add(
                    'thelia.import.error',
                    $this->getTranslator()->trans(
                        'Error(s) in import&nbsp;:<br />%errors',
                        [
                            '%errors' => implode('<br />', $importEvent->getErrors())
                        ]
                    )
                );
            }

            $this->getSession()->getFlashBag()->add(
                'thelia.import.success',
                $this->getTranslator()->trans(
                    'Import successfully done, %count row(s) have been changed',
                    [
                        '%count' => $importEvent->getImport()->getImportedRows()
                    ]
                )
            );

            return $this->generateRedirectFromRoute('import.view', [], ['id' => $id]);
        } catch (FormValidationException $e) {
            $form->setErrorMessage($this->createStandardFormValidationErrorMessage($e));
        } catch (\Exception $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

        $this->getParserContext()
            ->addForm($form)
        ;

        return $this->configureAction($id);
    }
}

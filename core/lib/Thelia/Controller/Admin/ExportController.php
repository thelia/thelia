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

use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;

/**
 * Class ExportController
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ExportController extends BaseAdminController
{
    /**
     * Handle default action, that is, list available exports
     *
     * @param string $_view
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function indexAction($_view = 'export')
    {
        $authResponse  = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::VIEW]);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $this->getParserContext()
            ->set('category_order', $this->getRequest()->query->get('category_order', 'manual'))
            ->set('export_order', $this->getRequest()->query->get('export_order', 'manual'))
        ;

        return $this->render($_view);
    }

    /**
     * Handle export position change action
     *
     * @return \Thelia\Core\HttpFoundation\Response|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function changeExportPositionAction()
    {
        $authResponse  = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE]);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $query = $this->getRequest()->query;

        $this->dispatch(
            TheliaEvents::EXPORT_CHANGE_POSITION,
            new UpdatePositionEvent(
                $query->get('id'),
                $this->matchPositionMode($query->get('mode')),
                $query->get('value')
            )
        );

        return $this->generateRedirectFromRoute('export.list');
    }

    /**
     * Handle export category position change action
     *
     * @return \Thelia\Core\HttpFoundation\Response|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function changeCategoryPositionAction()
    {
        $authResponse  = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE]);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $query = $this->getRequest()->query;

        $this->dispatch(
            TheliaEvents::EXPORT_CATEGORY_CHANGE_POSITION,
            new UpdatePositionEvent(
                $query->get('id'),
                $this->matchPositionMode($query->get('mode')),
                $query->get('value')
            )
        );

        return $this->generateRedirectFromRoute('export.list');
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
     * Display export configuration view
     *
     * @param integer $id An export ID
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function configureAction($id)
    {
        /** @var \Thelia\Handler\Exporthandler $exportHandler */
        $exportHandler = $this->container->get('thelia.export.handler');

        $export = $exportHandler->getExport($id);
        if ($export === null) {
            return $this->pageNotFound();
        }

        // Render standard view or ajax one
        $templateName = 'export-page';
        if ($this->getRequest()->isXmlHttpRequest()) {
            $templateName = 'ajax/export-modal';
        }

        return $this->render(
            $templateName,
            [
                'exportId' => $id,
                'HAS_IMAGES' => /*$export->hasImages($this->container)*/false,
                'HAS_DOCUMENTS' => /*$export->hasDocuments($this->container)*/false
            ]
        );
    }

    /**
     * Handle export action
     *
     * @param integer $id An export ID
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function exportAction($id)
    {
        /** @var \Thelia\Handler\Exporthandler $exportHandler */
        $exportHandler = $this->container->get('thelia.export.handler');

        $export = $exportHandler->getExport($id);
        if ($export === null) {
            return $this->pageNotFound();
        }

        $form = $this->createForm(AdminForm::EXPORT);

        try {
            $validatedForm = $this->validateForm($form);

            $lang = (new LangQuery)->findPk($validatedForm->get('language')->getData());

            /** @var \Thelia\Core\Serializer\SerializerManager $serializerManager */
            $serializerManager = $this->container->get(RegisterSerializerPass::MANAGER_SERVICE_ID);

            /** @var \Thelia\Core\Serializer\SerializerInterface $serializer */
            $serializer = $serializerManager->get($validatedForm->get('serializer')->getData());

            $archiveBuilder = null;
//            if ($validatedForm->get('do_compress')->getData()) {
//                $archiveBuilderManager = $this->container->get("thelia.manager.archive_builder_manager");
//                /** @var \Thelia\Core\FileFormat\Archive\ArchiveBuilderInterface $archiveBuilder */
//                $archiveBuilder = $archiveBuilderManager->get($validatedForm->get("archive_builder")->getData());
//            }

            $rangeDate = null;
            if ($validatedForm->get('range_date_start')->getData()
                && $validatedForm->get('range_date_end')->getData()
            ) {
                $rangeDate = [
                    'start' => $validatedForm->get('range_date_start')->getData(),
                    'end' =>$validatedForm->get('range_date_end')->getData()
                ];
            }

            $class = $export->getHandleClass();

            return $exportHandler->export(
//                $export->getHandleClassInstance($this->container),
                new $class,
                $serializer,
                $archiveBuilder,
                $lang,
                $validatedForm->get('images')->getData(),
                $validatedForm->get('documents')->getData(),
                $rangeDate
            );
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

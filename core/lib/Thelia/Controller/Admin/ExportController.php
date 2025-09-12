<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\DependencyInjection\Compiler\RegisterArchiverPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\Exporthandler;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;

/**
 * Class ExportController.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ExportController extends BaseAdminController
{
    /**
     * Handle default action, that is, list available exports.
     *
     * @param string $_view View to render
     */
    public function indexAction(string $_view = 'export'): Response
    {
        $authResponse = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::VIEW]);

        if ($authResponse instanceof Response) {
            return $authResponse;
        }

        $this->getParserContext()
            ->set('category_order', $this->getRequest()->query->get('category_order', 'manual'))
            ->set('export_order', $this->getRequest()->query->get('export_order', 'manual'));

        return $this->render($_view);
    }

    /**
     * Handle export position change action.
     */
    public function changeExportPositionAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        $authResponse = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE]);

        if ($authResponse instanceof Response) {
            return $authResponse;
        }

        $query = $this->getRequest()->query;

        $eventDispatcher->dispatch(
            new UpdatePositionEvent(
                (int) $query->get('id'),
                $this->matchPositionMode($query->get('mode')),
                (int) $query->get('value'),
            ),
            TheliaEvents::EXPORT_CHANGE_POSITION,
        );

        return $this->generateRedirectFromRoute('export.list');
    }

    /**
     * Handle export category position change action.
     */
    public function changeCategoryPositionAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        $authResponse = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE]);

        if ($authResponse instanceof Response) {
            return $authResponse;
        }

        $query = $this->getRequest()->query;

        $eventDispatcher->dispatch(
            new UpdatePositionEvent(
                (int) $query->get('id'),
                $this->matchPositionMode($query->get('mode')),
                (int) $query->get('value'),
            ),
            TheliaEvents::EXPORT_CATEGORY_CHANGE_POSITION,
        );

        return $this->generateRedirectFromRoute('export.list');
    }

    /**
     * Match position mode string against position mode constant value.
     *
     * @param string|null $mode Position mode string
     *
     * @return int Position mode constant value
     */
    protected function matchPositionMode(?string $mode): int
    {
        if ('up' === $mode) {
            return UpdatePositionEvent::POSITION_UP;
        }

        if ('down' === $mode) {
            return UpdatePositionEvent::POSITION_DOWN;
        }

        return UpdatePositionEvent::POSITION_ABSOLUTE;
    }

    /**
     * Display export configuration view.
     *
     * @param int $id An export identifier
     */
    public function configureAction(int $id): Response
    {
        /** @var Exporthandler $exportHandler */
        $exportHandler = $this->container->get('thelia.export.handler');

        $export = $exportHandler->getExport($id);

        if (null === $export) {
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
                'hasImages' => $export->hasImages(),
                'hasDocuments' => $export->hasDocuments(),
                'useRange' => $export->useRangeDate(),
            ],
        );
    }

    /**
     * Handle export action.
     *
     * @param int $id An export identifier
     */
    public function exportAction(int $id): Response|BinaryFileResponse
    {
        /** @var Exporthandler $exportHandler */
        $exportHandler = $this->container->get('thelia.export.handler');

        $export = $exportHandler->getExport($id);

        if (null === $export) {
            return $this->pageNotFound();
        }

        $form = $this->createForm(AdminForm::EXPORT);

        try {
            $validatedForm = $this->validateForm($form);

            set_time_limit(0);

            $lang = (new LangQuery())->findPk($validatedForm->get('language')->getData());

            /** @var SerializerManager $serializerManager */
            $serializerManager = $this->container->get(RegisterSerializerPass::MANAGER_SERVICE_ID);
            $serializer = $serializerManager->get($validatedForm->get('serializer')->getData());

            $archiver = null;

            if ($validatedForm->get('do_compress')->getData()) {
                /** @var ArchiverManager $archiverManager */
                $archiverManager = $this->container->get(RegisterArchiverPass::MANAGER_SERVICE_ID);
                $archiver = $archiverManager->get($validatedForm->get('archiver')->getData());
            }

            $rangeDate = null;

            if ($validatedForm->get('range_date_start')->getData()
                && $validatedForm->get('range_date_end')->getData()
            ) {
                $rangeDate = [
                    'start' => $validatedForm->get('range_date_start')->getData(),
                    'end' => $validatedForm->get('range_date_end')->getData(),
                ];
            }

            $exportEvent = $exportHandler->export(
                $export,
                $serializer,
                $archiver,
                $lang,
                $validatedForm->get('images')->getData(),
                $validatedForm->get('documents')->getData(),
                $rangeDate,
            );

            $contentType = $exportEvent->getSerializer()->getMimeType();
            $fileExt = $exportEvent->getSerializer()->getExtension();

            if (null !== $exportEvent->getArchiver()) {
                $contentType = $exportEvent->getArchiver()->getMimeType();
                $fileExt = $exportEvent->getArchiver()->getExtension();
            }

            $header = [
                'Content-Type' => $contentType,
                'Content-Disposition' => \sprintf(
                    '%s; filename="%s.%s"',
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $exportEvent->getExport()->getFileName(),
                    $fileExt,
                ),
            ];

            return new BinaryFileResponse($exportEvent->getFilePath(), Response::HTTP_OK, $header, false);
        } catch (FormValidationException $e) {
            $form->setErrorMessage($this->createStandardFormValidationErrorMessage($e));
        } catch (\Exception $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

        $this->getParserContext()
            ->addForm($form);

        return $this->configureAction($id);
    }
}

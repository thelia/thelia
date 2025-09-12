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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Archiver\AbstractArchiver;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Serializer\AbstractSerializer;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;
use Thelia\Service\DataTransfer\Importhandler;

/**
 * Class ImportController.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ImportController extends BaseAdminController
{
    /**
     * Handle default action, that is, list available imports.
     *
     * @param string $_view View to render
     */
    public function indexAction(string $_view = 'import'): Response
    {
        $authResponse = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::VIEW]);

        if ($authResponse instanceof Response) {
            return $authResponse;
        }

        $this->getParserContext()
            ->set('category_order', $this->getRequest()->query->get('category_order', 'manual'))
            ->set('import_order', $this->getRequest()->query->get('import_order', 'manual'));

        return $this->render($_view);
    }

    /**
     * Handle import position change action.
     */
    public function changeImportPositionAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        $authResponse = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE]);

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
            TheliaEvents::IMPORT_CHANGE_POSITION,
        );

        return $this->generateRedirectFromRoute('import.list');
    }

    /**
     * Handle import category position change action.
     */
    public function changeCategoryPositionAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        $authResponse = $this->checkAuth([AdminResources::IMPORT], [], [AccessManager::UPDATE]);

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
            TheliaEvents::IMPORT_CATEGORY_CHANGE_POSITION,
        );

        return $this->generateRedirectFromRoute('import.list');
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
     * Display import configuration view.
     *
     * @param int $id An import identifier
     */
    public function configureAction(
        int $id,
        SerializerManager $serializerManager,
        ArchiverManager $archiverManager,
    ): Response {
        /** @var Importhandler $importHandler */
        $importHandler = $this->container->get('thelia.import.handler');

        $import = $importHandler->getImport($id);

        if (null === $import) {
            return $this->pageNotFound();
        }

        $extensions = [];
        $mimeTypes = [];

        /** @var AbstractSerializer $serializer */
        foreach ($serializerManager->getSerializers() as $serializer) {
            $extensions[] = $serializer->getExtension();
            $mimeTypes[] = $serializer->getMimeType();
        }

        /** @var AbstractArchiver $archiver */
        foreach ($archiverManager->getArchivers(true) as $archiver) {
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
            ],
        );
    }

    /**
     * Handle import action.
     *
     * @param int $id An import identifier
     */
    public function importAction(
        int $id,
        SerializerManager $serializerManager,
        ArchiverManager $archiverManager,
    ): Response|RedirectResponse {
        /** @var Importhandler $importHandler */
        $importHandler = $this->container->get('thelia.import.handler');

        $import = $importHandler->getImport($id);

        if (null === $import) {
            return $this->pageNotFound();
        }

        $form = $this->createForm(AdminForm::IMPORT);

        try {
            $validatedForm = $this->validateForm($form);

            /** @var UploadedFile $file */
            $file = $validatedForm->get('file_upload')->getData();
            $file = $file->move(
                THELIA_CACHE_DIR.'import'.DS.(new \DateTime())->format('Ymd'),
                uniqid().'-'.$file->getClientOriginalName(),
            );

            $lang = (new LangQuery())->findPk($validatedForm->get('language')->getData());

            $importEvent = $importHandler->import($import, $file, $lang);

            if (\count($importEvent->getErrors()) > 0) {
                $this->getSession()->getFlashBag()->add(
                    'thelia.import.error',
                    $this->getTranslator()->trans(
                        'Error(s) in import&nbsp;:<br />%errors',
                        [
                            '%errors' => implode('<br />', $importEvent->getErrors()),
                        ],
                    ),
                );
            }

            $this->getSession()->getFlashBag()->add(
                'thelia.import.success',
                $this->getTranslator()->trans(
                    'Import successfully done, %count row(s) have been changed',
                    [
                        '%count' => $importEvent->getImport()->getImportedRows(),
                    ],
                ),
            );

            return $this->generateRedirectFromRoute('import.view', [], ['id' => $id]);
        } catch (FormValidationException $e) {
            $form->setErrorMessage($this->createStandardFormValidationErrorMessage($e));
        } catch (\Exception $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

        $this->getParserContext()
            ->addForm($form);

        return $this->configureAction($id, $serializerManager, $archiverManager);
    }
}

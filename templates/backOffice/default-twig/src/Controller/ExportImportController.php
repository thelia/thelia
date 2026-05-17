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

namespace BackOfficeDefaultTwigBundle\Controller;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;
use Thelia\Model\LangQuery;
use Twig\Environment;

final class ExportImportController
{
    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly ExportHandler $exportHandler,
        private readonly ImportHandler $importHandler,
        private readonly SerializerManager $serializerManager,
        private readonly ArchiverManager $archiverManager,
        private readonly EventDispatcherInterface $events,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/admin/export', name: 'export.list', methods: ['GET'])]
    public function exportIndex(): Response
    {
        if ($denied = $this->access->check(AdminResources::EXPORT, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $categories = [];
        foreach (ExportCategoryQuery::create()->orderByPosition()->find() as $category) {
            $category->setLocale($locale);
            $exports = [];
            foreach (ExportQuery::create()->filterByExportCategoryId($category->getId())->orderByPosition()->find() as $export) {
                $export->setLocale($locale);
                $exports[] = [
                    'id' => (int) $export->getId(),
                    'ref' => (string) $export->getRef(),
                    'title' => (string) $export->getTitle(),
                    'description' => (string) $export->getDescription(),
                ];
            }
            $categories[] = [
                'id' => (int) $category->getId(),
                'title' => (string) $category->getTitle(),
                'exports' => $exports,
            ];
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/export/list.html.twig', [
            'categories' => $categories,
        ]));
    }

    #[Route('/admin/import', name: 'import.list', methods: ['GET'])]
    public function importIndex(): Response
    {
        if ($denied = $this->access->check(AdminResources::IMPORT, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $categories = [];
        foreach (ImportCategoryQuery::create()->orderByPosition()->find() as $category) {
            $category->setLocale($locale);
            $imports = [];
            foreach (ImportQuery::create()->filterByImportCategoryId($category->getId())->orderByPosition()->find() as $import) {
                $import->setLocale($locale);
                $imports[] = [
                    'id' => (int) $import->getId(),
                    'ref' => (string) $import->getRef(),
                    'title' => (string) $import->getTitle(),
                    'description' => (string) $import->getDescription(),
                ];
            }
            $categories[] = [
                'id' => (int) $category->getId(),
                'title' => (string) $category->getTitle(),
                'imports' => $imports,
            ];
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/import/list.html.twig', [
            'categories' => $categories,
        ]));
    }

    #[Route('/admin/export/position', name: 'export.position', methods: ['GET'])]
    public function exportPosition(Request $request): Response
    {
        if ($denied = $this->access->check(AdminResources::EXPORT, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $this->events->dispatch(
            new UpdatePositionEvent(
                (int) $request->query->get('id', 0),
                $this->matchPositionMode($request->query->get('mode')),
                (int) $request->query->get('value', 0),
            ),
            TheliaEvents::EXPORT_CHANGE_POSITION,
        );

        return new RedirectResponse($this->urls->generate('export.list'));
    }

    #[Route('/admin/export/position/category', name: 'export.category.position', methods: ['GET'])]
    public function exportCategoryPosition(Request $request): Response
    {
        if ($denied = $this->access->check(AdminResources::EXPORT, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $this->events->dispatch(
            new UpdatePositionEvent(
                (int) $request->query->get('id', 0),
                $this->matchPositionMode($request->query->get('mode')),
                (int) $request->query->get('value', 0),
            ),
            TheliaEvents::EXPORT_CATEGORY_CHANGE_POSITION,
        );

        return new RedirectResponse($this->urls->generate('export.list'));
    }

    #[Route('/admin/import/position', name: 'import.position', methods: ['GET'])]
    public function importPosition(Request $request): Response
    {
        if ($denied = $this->access->check(AdminResources::IMPORT, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $this->events->dispatch(
            new UpdatePositionEvent(
                (int) $request->query->get('id', 0),
                $this->matchPositionMode($request->query->get('mode')),
                (int) $request->query->get('value', 0),
            ),
            TheliaEvents::IMPORT_CHANGE_POSITION,
        );

        return new RedirectResponse($this->urls->generate('import.list'));
    }

    #[Route('/admin/import/position/category', name: 'import.category.position', methods: ['GET'])]
    public function importCategoryPosition(Request $request): Response
    {
        if ($denied = $this->access->check(AdminResources::IMPORT, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $this->events->dispatch(
            new UpdatePositionEvent(
                (int) $request->query->get('id', 0),
                $this->matchPositionMode($request->query->get('mode')),
                (int) $request->query->get('value', 0),
            ),
            TheliaEvents::IMPORT_CATEGORY_CHANGE_POSITION,
        );

        return new RedirectResponse($this->urls->generate('import.list'));
    }

    #[Route('/admin/export/{id}', name: 'export.view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function exportView(int $id): Response
    {
        if ($denied = $this->access->check(AdminResources::EXPORT, [], AccessManager::VIEW)) {
            return $denied;
        }

        $export = $this->exportHandler->getExport($id);
        if ($export === null) {
            return new RedirectResponse($this->urls->generate('export.list'));
        }

        $locale = $this->defaultLocale();
        $export->setLocale($locale);

        return new Response($this->twig->render('@BackOfficeDefaultTwig/export/edit.html.twig', [
            'export' => $export,
            'export_id' => $id,
            'serializers' => $this->serializerOptions(),
            'archivers' => $this->archiverOptions(),
            'languages' => $this->languageOptions(),
            'use_range' => $this->exportUseRangeDate($export),
            'has_images' => (bool) $export->hasImages(),
            'has_documents' => (bool) $export->hasDocuments(),
            'years' => range((int) date('Y'), (int) date('Y') - 5),
            'months' => range(1, 12),
        ]));
    }

    #[Route('/admin/export/{id}', name: 'export.process', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function exportProcess(int $id, Request $request): Response|BinaryFileResponse
    {
        if ($denied = $this->access->check(AdminResources::EXPORT, [], AccessManager::VIEW)) {
            return $denied;
        }

        $export = $this->exportHandler->getExport($id);
        if ($export === null) {
            return new RedirectResponse($this->urls->generate('export.list'));
        }

        @set_time_limit(0);

        $lang = LangQuery::create()->findPk((int) $request->request->get('language', 0));
        if ($lang === null) {
            $this->addFlash('error', $this->translator->trans('Invalid language selected.'));

            return new RedirectResponse($this->urls->generate('export.view', ['id' => $id]));
        }

        $serializerId = (string) $request->request->get('serializer', '');
        if (!$this->serializerManager->has($serializerId)) {
            $this->addFlash('error', $this->translator->trans('Unknown serializer.'));

            return new RedirectResponse($this->urls->generate('export.view', ['id' => $id]));
        }
        $serializer = $this->serializerManager->get($serializerId);

        $archiver = null;
        if ($request->request->getBoolean('do_compress')) {
            $archiverId = (string) $request->request->get('archiver', '');
            if ($this->archiverManager->has($archiverId)) {
                $archiver = $this->archiverManager->get($archiverId, true);
            }
        }

        $rangeDate = null;
        if ((array) $request->request->all('range_date_start') !== [] && (array) $request->request->all('range_date_end') !== []) {
            $rangeDate = [
                'start' => $request->request->all('range_date_start'),
                'end' => $request->request->all('range_date_end'),
            ];
        }

        try {
            $exportEvent = $this->exportHandler->export(
                $export,
                $serializer,
                $archiver,
                $lang,
                $request->request->getBoolean('images'),
                $request->request->getBoolean('documents'),
                $rangeDate,
            );

            $contentType = $exportEvent->getSerializer()->getMimeType();
            $fileExt = $exportEvent->getSerializer()->getExtension();
            if ($exportEvent->getArchiver() !== null) {
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
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());

            return new RedirectResponse($this->urls->generate('export.view', ['id' => $id]));
        }
    }

    #[Route('/admin/import/{id}', name: 'import.view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function importView(int $id): Response
    {
        if ($denied = $this->access->check(AdminResources::IMPORT, [], AccessManager::VIEW)) {
            return $denied;
        }

        $import = $this->importHandler->getImport($id);
        if ($import === null) {
            return new RedirectResponse($this->urls->generate('import.list'));
        }

        $locale = $this->defaultLocale();
        $import->setLocale($locale);

        $extensions = [];
        $mimeTypes = [];
        foreach ($this->serializerManager->getSerializers() as $serializer) {
            $extensions[] = $serializer->getExtension();
            $mimeTypes[] = $serializer->getMimeType();
        }
        foreach ($this->archiverManager->getArchivers(true) as $archiver) {
            $extensions[] = $archiver->getExtension();
            $mimeTypes[] = $archiver->getMimeType();
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/import/edit.html.twig', [
            'import' => $import,
            'import_id' => $id,
            'languages' => $this->languageOptions(),
            'allowed_extensions' => implode(', ', $extensions),
            'allowed_mime_types' => implode(', ', $mimeTypes),
        ]));
    }

    #[Route('/admin/import/{id}', name: 'import.process', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function importProcess(int $id, Request $request): Response
    {
        if ($denied = $this->access->check(AdminResources::IMPORT, [], AccessManager::VIEW)) {
            return $denied;
        }

        $import = $this->importHandler->getImport($id);
        if ($import === null) {
            return new RedirectResponse($this->urls->generate('import.list'));
        }

        $uploaded = $request->files->get('file_upload');
        if (!$uploaded instanceof UploadedFile) {
            $this->addFlash('error', $this->translator->trans('Please select a file to import.'));

            return new RedirectResponse($this->urls->generate('import.view', ['id' => $id]));
        }

        $lang = LangQuery::create()->findPk((int) $request->request->get('language', 0));
        if ($lang === null) {
            $this->addFlash('error', $this->translator->trans('Invalid language selected.'));

            return new RedirectResponse($this->urls->generate('import.view', ['id' => $id]));
        }

        $targetDir = THELIA_CACHE_DIR.'import'.\DIRECTORY_SEPARATOR.(new \DateTime())->format('Ymd');
        $movedFile = $uploaded->move(
            $targetDir,
            uniqid('', true).'-'.$uploaded->getClientOriginalName(),
        );

        try {
            $importEvent = $this->importHandler->import($import, $movedFile, $lang);
            $errors = $importEvent->getErrors();
            if (\count($errors) > 0) {
                $this->addFlash('error', $this->translator->trans(
                    'Error(s) in import : %errors',
                    ['%errors' => implode(' | ', $errors)],
                ));
            }
            $this->addFlash('success', $this->translator->trans(
                'Import successfully done, %count row(s) have been changed',
                ['%count' => (int) $importEvent->getImport()->getImportedRows()],
            ));
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return new RedirectResponse($this->urls->generate('import.view', ['id' => $id]));
    }

    /** @return list<array{id: string, name: string, extension: string}> */
    private function serializerOptions(): array
    {
        $options = [];
        foreach ($this->serializerManager->getSerializers() as $serializer) {
            $options[] = [
                'id' => (string) $serializer->getId(),
                'name' => (string) $serializer->getName(),
                'extension' => (string) $serializer->getExtension(),
            ];
        }

        return $options;
    }

    /** @return list<array{id: string, name: string, extension: string}> */
    private function archiverOptions(): array
    {
        $options = [];
        foreach ($this->archiverManager->getArchivers(true) as $archiver) {
            $options[] = [
                'id' => (string) $archiver->getId(),
                'name' => (string) $archiver->getName(),
                'extension' => (string) $archiver->getExtension(),
            ];
        }

        return $options;
    }

    /** @return list<array{id: int, title: string, is_default: bool}> */
    private function languageOptions(): array
    {
        $options = [];
        foreach (LangQuery::create()->orderByPosition()->find() as $lang) {
            $options[] = [
                'id' => (int) $lang->getId(),
                'title' => (string) $lang->getTitle(),
                'is_default' => (bool) $lang->getByDefault(),
            ];
        }

        return $options;
    }

    private function exportUseRangeDate(\Thelia\Model\Export $export): bool
    {
        $handleClass = (string) $export->getHandleClass();
        if ($handleClass === '' || !class_exists($handleClass)) {
            return false;
        }

        $instance = new $handleClass();

        return method_exists($instance, 'useRangeDate') && $instance->useRangeDate();
    }

    private function matchPositionMode(?string $mode): int
    {
        return match ($mode) {
            'up' => UpdatePositionEvent::POSITION_UP,
            'down' => UpdatePositionEvent::POSITION_DOWN,
            default => UpdatePositionEvent::POSITION_ABSOLUTE,
        };
    }

    private function addFlash(string $type, string $message): void
    {
        $session = $this->requestStack->getSession();
        if (method_exists($session, 'getFlashBag')) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}

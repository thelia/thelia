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

namespace BackOfficeDefaultTwigBundle\Controller\File;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\File\FileManager;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\LangQuery;
use Twig\Environment;

final class FileController
{
    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
        private readonly FileManager $fileManager,
        private readonly AdminResources $resources,
    ) {
    }

    #[Route('/admin/image/type/{parentType}/{parentId}/list-ajax', name: 'admin.image.list-ajax', requirements: ['parentId' => '\d+', 'parentType' => '.+'])]
    public function imageList(string $parentType, int $parentId): Response
    {
        return $this->renderList('image', $parentType, $parentId);
    }

    #[Route('/admin/image/type/{parentType}/{parentId}/form-ajax', name: 'admin.image.form-ajax', requirements: ['parentId' => '\d+', 'parentType' => '.+'])]
    public function imageForm(string $parentType, int $parentId): Response
    {
        return $this->renderForm('image', $parentType, $parentId);
    }

    #[Route('/admin/document/type/{parentType}/{parentId}/list-ajax', name: 'admin.document.list-ajax', requirements: ['parentId' => '\d+', 'parentType' => '.+'])]
    public function documentList(string $parentType, int $parentId): Response
    {
        return $this->renderList('document', $parentType, $parentId);
    }

    #[Route('/admin/document/type/{parentType}/{parentId}/form-ajax', name: 'admin.document.form-ajax', requirements: ['parentId' => '\d+', 'parentType' => '.+'])]
    public function documentForm(string $parentType, int $parentId): Response
    {
        return $this->renderForm('document', $parentType, $parentId);
    }

    private function renderList(string $kind, string $parentType, int $parentId): Response
    {
        $resource = $this->resources->getResource($parentType);
        if ($denied = $this->access->check($resource, [], AccessManager::VIEW)) {
            return $denied;
        }

        $items = $this->fetchItems($kind, $parentType, $parentId);
        $canUpdate = $this->access->check($resource, [], AccessManager::UPDATE) === null;
        $canDelete = $this->access->check($resource, [], AccessManager::DELETE) === null;

        return new Response($this->twig->render('@BackOfficeDefaultTwig/file/_list.html.twig', [
            'kind' => $kind,
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'items' => $items,
            'can_update' => $canUpdate,
            'can_delete' => $canDelete,
            'urls' => $this->urlsFor($kind, $parentType),
        ]));
    }

    private function renderForm(string $kind, string $parentType, int $parentId): Response
    {
        $resource = $this->resources->getResource($parentType);
        if ($denied = $this->access->check($resource, [], AccessManager::UPDATE)) {
            return $denied;
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/file/_form.html.twig', [
            'kind' => $kind,
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'urls' => $this->urlsFor($kind, $parentType),
        ]));
    }

    /**
     * @return list<array{id: int, title: string, file: string, visible: bool, position: int, url: string}>
     */
    private function fetchItems(string $kind, string $parentType, int $parentId): array
    {
        $model = $this->fileManager->getModelInstance($kind, $parentType);
        $locale = $this->defaultLocale();

        $query = $model->getQueryInstance();
        $filterMethod = 'filterBy'.ucfirst($parentType).'Id';
        if (method_exists($query, $filterMethod)) {
            $query->{$filterMethod}($parentId);
        }
        $records = $query
            ->orderByPosition()
            ->find();

        $items = [];
        foreach ($records as $record) {
            if (!method_exists($record, 'setLocale')) {
                continue;
            }
            $record->setLocale($locale);
            $file = (string) $record->getFile();
            $items[] = [
                'id' => (int) $record->getId(),
                'title' => (string) $record->getTitle(),
                'file' => $file,
                'visible' => (bool) (method_exists($record, 'getVisible') ? $record->getVisible() : true),
                'position' => (int) (method_exists($record, 'getPosition') ? $record->getPosition() : 0),
                'url' => $this->fileUrl($kind, $parentType, $file),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function urlsFor(string $kind, string $parentType): array
    {
        $names = $kind === 'image'
            ? [
                'save' => 'admin.image.save-ajax',
                'position' => 'admin.image.update-position',
                'toggle' => 'admin.image.toggle.process',
                'delete' => 'admin.image.delete',
                'update' => 'admin.image.update.view',
                'update_title' => 'admin.image.update-title',
                'list' => 'admin.image.list-ajax',
            ]
            : [
                'save' => 'admin.document.save-ajax',
                'position' => 'admin.document.update-position',
                'toggle' => 'admin.document.toggle.process',
                'delete' => 'admin.document.delete',
                'update' => 'admin.document.update.view',
                'update_title' => 'admin.document.update-title',
                'list' => 'admin.document.list-ajax',
            ];

        return $names;
    }

    private function fileUrl(string $kind, string $parentType, string $file): string
    {
        if ($file === '') {
            return '';
        }

        return '/'.$kind.'/'.$parentType.'/'.$file;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}

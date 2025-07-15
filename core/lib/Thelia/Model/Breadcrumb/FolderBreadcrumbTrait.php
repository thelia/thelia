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

namespace Thelia\Model\Breadcrumb;

use Symfony\Component\Routing\Router;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Content;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Tools\URL;

trait FolderBreadcrumbTrait
{
    /**
     * @return mixed[]
     */
    public function getBaseBreadcrumb(Router $router, $folderId, $locale): array
    {
        $translator = Translator::getInstance();
        $foldersUrl = $router->generate('admin.folders.default', [], Router::ABSOLUTE_URL);
        $breadcrumb = [
            $translator->trans('Home') => URL::getInstance()->absoluteUrl('/admin'),
            $translator->trans('Folder') => $foldersUrl,
        ];

        $depth = 20;
        $ids = [];
        $results = [];

        // Todo refactor this ugly code
        $currentId = $folderId;

        do {
            $folder = FolderQuery::create()
                ->filterById($currentId)
                ->findOne();

            if (null !== $folder) {
                $results[] = [
                    'ID' => $folder->getId(),
                    'TITLE' => $folder->setLocale($locale)->getTitle(),
                    'URL' => $folder->getUrl(),
                ];

                $currentId = $folder->getParent();

                if ($currentId > 0) {
                    // Prevent circular refererences
                    if (\in_array($currentId, $ids, true)) {
                        throw new \LogicException(\sprintf('Circular reference detected in folder ID=%d hierarchy (folder ID=%d appears more than one times in path)', $folderId, $currentId));
                    }

                    $ids[] = $currentId;
                }
            }
        } while (null !== $folder && $currentId > 0 && --$depth > 0);

        foreach ($results as $result) {
            $breadcrumb[$result['TITLE']] = \sprintf(
                '%s?parent=%d',
                $router->generate(
                    'admin.folders.default',
                    [],
                    Router::ABSOLUTE_URL,
                ),
                $result['ID'],
            );
        }

        return $breadcrumb;
    }

    public function getFolderBreadcrumb(Router $router, $tab, $locale)
    {
        if (!method_exists($this, 'getFolder')) {
            return;
        }

        /** @var Folder $folder */
        $folder = $this->getFolder();
        $breadcrumb = $this->getBaseBreadcrumb($router, $this->getParentId(), $locale);

        $folder->setLocale($locale);

        $breadcrumb[$folder->getTitle()] = \sprintf(
            '%s?current_tab=%s',
            $router->generate(
                'admin.folders.update',
                ['folder_id' => $folder->getId()],
                Router::ABSOLUTE_URL,
            ),
            $tab,
        );

        return $breadcrumb;
    }

    public function getContentBreadcrumb(Router $router, $tab, $locale): array
    {
        if (!method_exists($this, 'getContent')) {
            return [];
        }

        /** @var Content $content */
        $content = $this->getContent();

        $breadcrumb = $this->getBaseBreadcrumb($router, $content->getDefaultFolderId(), $locale);

        $content->setLocale($locale);

        $breadcrumb[$content->getTitle()] = \sprintf(
            '%s?current_tab=%s',
            $router->generate(
                'admin.content.update',
                ['content_id' => $content->getId()],
                Router::ABSOLUTE_URL,
            ),
            $tab,
        );

        return $breadcrumb;
    }
}

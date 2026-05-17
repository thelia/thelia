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

namespace BackOfficeDefaultTwigBundle\Controller\Catalog;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Event\Category\CategoryAddContentEvent;
use Thelia\Core\Event\Category\CategoryDeleteContentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CategoryAssociatedContentQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\LangQuery;

final class CategoryRelationsController
{
    private const RESOURCE = AdminResources::CATEGORY;
    private const EDIT_ROUTE = 'admin.categories.update';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/admin/categories/related-content/add', name: 'admin.categories.related-content.add', methods: ['POST', 'GET'])]
    public function addRelatedContent(Request $request): Response
    {
        $categoryId = (int) $request->get('category_id', 0);
        $category = CategoryQuery::create()->findPk($categoryId);
        if ($category === null) {
            return new RedirectResponse($this->urls->generate('admin.categories.default'));
        }

        $contentId = (int) $request->get('content_id', 0);
        if ($contentId <= 0) {
            return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['category_id' => $categoryId, 'current_tab' => 'associations']));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new CategoryAddContentEvent($category, $contentId),
            eventName: TheliaEvents::CATEGORY_ADD_CONTENT,
            actionLabel: 'Category related content added',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['category_id' => $categoryId, 'current_tab' => 'associations'],
        );
    }

    #[Route('/admin/categories/related-content/delete', name: 'admin.categories.related-content.delete', methods: ['POST', 'GET'])]
    public function deleteRelatedContent(Request $request): Response
    {
        $categoryId = (int) $request->get('category_id', 0);
        $category = CategoryQuery::create()->findPk($categoryId);
        if ($category === null) {
            return new RedirectResponse($this->urls->generate('admin.categories.default'));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new CategoryDeleteContentEvent($category, (int) $request->get('content_id', 0)),
            eventName: TheliaEvents::CATEGORY_REMOVE_CONTENT,
            actionLabel: 'Category related content removed',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['category_id' => $categoryId, 'current_tab' => 'associations'],
        );
    }

    #[Route('/admin/categories/related-picture/add', name: 'admin.categories.related-picture.add', methods: ['POST', 'GET'])]
    public function addRelatedPicture(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $categoryId = (int) $request->get('category_id', 0);

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['category_id' => $categoryId, 'current_tab' => 'images']));
    }

    #[Route(
        '/admin/category/{categoryId}/available-related-content/{folderId}.{_format}',
        name: 'admin.category.available-related-content',
        methods: ['GET'],
        requirements: ['categoryId' => '\d+', 'folderId' => '\d+', '_format' => 'json|xml'],
    )]
    public function availableRelatedContent(int $categoryId, int $folderId): JsonResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $locale = $this->defaultLocale();
        $folder = FolderQuery::create()->findPk($folderId);
        if ($folder === null) {
            return new JsonResponse([]);
        }

        $alreadyAssigned = CategoryAssociatedContentQuery::create()
            ->select('content_id')
            ->findByCategoryId($categoryId)
            ->toArray();

        $contents = ContentQuery::create()
            ->joinWithI18n($locale)
            ->filterByFolder($folder, Criteria::IN)
            ->filterById($alreadyAssigned, Criteria::NOT_IN)
            ->orderByPosition()
            ->find();

        $items = [];
        foreach ($contents as $content) {
            $items[] = ['id' => (int) $content->getId(), 'title' => (string) $content->getTitle()];
        }

        return new JsonResponse($items);
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}

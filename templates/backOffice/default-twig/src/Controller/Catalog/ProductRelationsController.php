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
use BackOfficeDefaultTwigBundle\Service\Catalog\ProductRelationsContext;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductAddCategoryEvent;
use Thelia\Core\Event\Product\ProductAddContentEvent;
use Thelia\Core\Event\Product\ProductDeleteAccessoryEvent;
use Thelia\Core\Event\Product\ProductDeleteCategoryEvent;
use Thelia\Core\Event\Product\ProductDeleteContentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;
use Twig\Environment;

#[Route('/admin/products', name: 'admin.products.')]
final class ProductRelationsController
{
    private const RESOURCE = AdminResources::PRODUCT;
    private const EDIT_ROUTE = 'admin.products.update';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
        private readonly ProductRelationsContext $relations,
    ) {
    }

    #[Route('/related/tab', name: 'related.tab', methods: ['GET'])]
    public function relatedTab(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $product = ProductQuery::create()->findPk((int) $request->query->get('product_id', 0));
        if ($product === null) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $locale = $this->defaultLocale();
        $product->setLocale($locale);

        return new Response($this->twig->render('@BackOfficeDefaultTwig/catalog/product/_related_tab.html.twig', $this->relations->build($product, $locale)));
    }

    #[Route('/related/tab/categories/search', name: 'related.tab.categories.search', methods: ['GET'])]
    public function relatedCategoriesSearch(Request $request): JsonResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $locale = $this->defaultLocale();
        $term = trim((string) $request->query->get('q', ''));
        $items = [];

        $query = CategoryQuery::create()->orderById();
        if ($term !== '') {
            $query->useCategoryI18nQuery()
                ->filterByLocale($locale)
                ->filterByTitle('%'.$term.'%', Criteria::LIKE)
                ->endUse();
        }

        foreach ($query->limit(20)->find() as $category) {
            $category->setLocale($locale);
            $items[] = ['id' => (int) $category->getId(), 'title' => (string) $category->getTitle()];
        }

        return new JsonResponse($items);
    }

    #[Route('/related/tab/products/search', name: 'related.tab.products.search', methods: ['GET'])]
    public function relatedProductsSearch(Request $request): JsonResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $locale = $this->defaultLocale();
        $term = trim((string) $request->query->get('q', ''));

        $titleIds = [];
        if ($term !== '') {
            $titleIds = \Thelia\Model\ProductI18nQuery::create()
                ->filterByLocale($locale)
                ->filterByTitle('%'.$term.'%', Criteria::LIKE)
                ->select(['Id'])
                ->find()
                ->toArray();
        }

        $query = ProductQuery::create()->orderByPosition();
        if ($term !== '') {
            $query->filterById($titleIds, Criteria::IN)
                ->_or()
                ->filterByRef('%'.$term.'%', Criteria::LIKE);
        }

        $items = [];
        foreach ($query->limit(20)->find() as $product) {
            $product->setLocale($locale);
            $items[] = ['id' => (int) $product->getId(), 'title' => (string) $product->getTitle(), 'ref' => (string) $product->getRef()];
        }

        return new JsonResponse($items);
    }

    #[Route('/category/add', name: 'additional-category.add', methods: ['POST', 'GET'])]
    public function addAdditionalCategory(Request $request): Response
    {
        $product = ProductQuery::create()->findPk((int) $request->get('product_id', 0));
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ProductAddCategoryEvent($product, (int) $request->get('category_id', 0)),
            eventName: TheliaEvents::PRODUCT_ADD_CATEGORY,
            actionLabel: 'Product additional category added',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $product->getId(), 'current_tab' => 'related'],
        );
    }

    #[Route('/category/delete', name: 'additional-category.delete', methods: ['POST', 'GET'])]
    public function deleteAdditionalCategory(Request $request): Response
    {
        $product = ProductQuery::create()->findPk((int) $request->get('product_id', 0));
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ProductDeleteCategoryEvent($product, (int) $request->get('category_id', 0)),
            eventName: TheliaEvents::PRODUCT_REMOVE_CATEGORY,
            actionLabel: 'Product additional category removed',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $product->getId(), 'current_tab' => 'related'],
        );
    }

    #[Route('/content/add', name: 'related-content.add', methods: ['POST', 'GET'])]
    public function addRelatedContent(Request $request): Response
    {
        $product = ProductQuery::create()->findPk((int) $request->get('product_id', 0));
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ProductAddContentEvent($product, (int) $request->get('content_id', 0)),
            eventName: TheliaEvents::PRODUCT_ADD_CONTENT,
            actionLabel: 'Product related content added',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $product->getId(), 'current_tab' => 'related'],
        );
    }

    #[Route('/content/delete', name: 'related-content.delete', methods: ['POST', 'GET'])]
    public function deleteRelatedContent(Request $request): Response
    {
        $product = ProductQuery::create()->findPk((int) $request->get('product_id', 0));
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ProductDeleteContentEvent($product, (int) $request->get('content_id', 0)),
            eventName: TheliaEvents::PRODUCT_REMOVE_CONTENT,
            actionLabel: 'Product related content removed',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $product->getId(), 'current_tab' => 'related'],
        );
    }

    #[Route('/accessory/add', name: 'accessories.add', methods: ['POST', 'GET'])]
    public function addAccessory(Request $request): Response
    {
        $product = ProductQuery::create()->findPk((int) $request->get('product_id', 0));
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ProductAddAccessoryEvent($product, (int) $request->get('accessory_id', 0)),
            eventName: TheliaEvents::PRODUCT_ADD_ACCESSORY,
            actionLabel: 'Product accessory added',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $product->getId(), 'current_tab' => 'related'],
        );
    }

    #[Route('/accessory/delete', name: 'accessories.delete', methods: ['POST', 'GET'])]
    public function deleteAccessory(Request $request): Response
    {
        $product = ProductQuery::create()->findPk((int) $request->get('product_id', 0));
        if ($product === null) {
            return new RedirectResponse($this->urls->generate('admin.products.default'));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ProductDeleteAccessoryEvent($product, (int) $request->get('accessory_id', 0)),
            eventName: TheliaEvents::PRODUCT_REMOVE_ACCESSORY,
            actionLabel: 'Product accessory removed',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => (int) $product->getId(), 'current_tab' => 'related'],
        );
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}

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
use Thelia\Model\CategoryQuery;
use Thelia\Model\Product;
use Thelia\Tools\URL;

trait CatalogBreadcrumbTrait
{
    /**
     * @return mixed[]
     */
    public function getBaseBreadcrumb(Router $router, $categoryId, $locale): array
    {
        $translator = Translator::getInstance();
        $catalogUrl = $router->generate('admin.catalog', [], Router::ABSOLUTE_URL);
        $breadcrumb = [
            $translator->trans('Home') => URL::getInstance()->absoluteUrl('/admin'),
            $translator->trans('Catalog') => $catalogUrl,
        ];

        $depth = 20;
        $ids = [];
        $results = [];

        // Todo refactor this ugly code
        $currentId = $categoryId;

        do {
            $category = CategoryQuery::create()
                ->filterById($currentId)
                ->findOne();

            if (null !== $category) {
                $results[] = [
                    'ID' => $category->getId(),
                    'TITLE' => $category->setLocale($locale)->getTitle(),
                    'URL' => $category->getUrl(),
                ];

                $currentId = $category->getParent();

                if ($currentId > 0) {
                    // Prevent circular refererences
                    if (\in_array($currentId, $ids, true)) {
                        throw new \LogicException(\sprintf('Circular reference detected in category ID=%d hierarchy (category ID=%d appears more than one times in path)', $categoryId, $currentId));
                    }

                    $ids[] = $currentId;
                }
            }
        } while (null !== $category && $currentId > 0 && --$depth > 0);

        foreach ($results as $result) {
            $breadcrumb[$result['TITLE']] = \sprintf('%s?category_id=%d', $catalogUrl, $result['ID']);
        }

        return $breadcrumb;
    }

    public function getProductBreadcrumb(Router $router, $tab, $locale)
    {
        if (!method_exists($this, 'getProduct')) {
            return;
        }

        /** @var Product $product */
        $product = $this->getProduct();

        $breadcrumb = $this->getBaseBreadcrumb($router, $product->getDefaultCategoryId(), $locale);

        $product->setLocale($locale);

        $breadcrumb[$product->getTitle()] = \sprintf(
            '%s?product_id=%d&current_tab=%s',
            $router->generate('admin.products.update', [], Router::ABSOLUTE_URL),
            $product->getId(),
            $tab,
        );

        return $breadcrumb;
    }

    public function getCategoryBreadcrumb(Router $router, $tab, $locale): array
    {
        if (!method_exists($this, 'getCategory')) {
            return [];
        }

        $category = $this->getCategory();
        $breadcrumb = $this->getBaseBreadcrumb($router, $this->getParentId(), $locale);

        $category->setLocale($locale);

        $breadcrumb[$category->getTitle()] = \sprintf(
            '%s?category_id=%d&current_tab=%s',
            $router->generate(
                'admin.categories.update',
                [],
                Router::ABSOLUTE_URL,
            ),
            $category->getId(),
            $tab,
        );

        return $breadcrumb;
    }
}

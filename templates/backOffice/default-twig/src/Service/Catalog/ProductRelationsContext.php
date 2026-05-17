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

namespace BackOfficeDefaultTwigBundle\Service\Catalog;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Tools\TokenProvider;

final readonly class ProductRelationsContext
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private TokenProvider $tokens,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Product $product, string $locale): array
    {
        $defaultCategoryId = (int) $product->getDefaultCategoryId();
        $additionalCategories = $this->additionalCategories($product, $locale, $defaultCategoryId);
        $additionalCategoryIds = array_map(static fn (array $row): int => $row['id'], $additionalCategories);
        $excludedFromTree = array_merge([$defaultCategoryId], $additionalCategoryIds);

        return [
            'product' => $product,
            'default_category_id' => $defaultCategoryId,
            'folder_tree' => $this->folderTree($locale),
            'category_tree_for_accessory' => $this->categoryTree($locale),
            'category_tree_for_additional' => $this->categoryTree($locale, excluded: $excludedFromTree),
            'assigned_contents' => $this->assignedContents($product, $locale),
            'assigned_accessories' => $this->assignedAccessories($product, $locale),
            'additional_categories' => $additionalCategories,
            'available_related_content_url' => $this->urls->generate('admin.product.available-related-content', ['productId' => (int) $product->getId(), 'folderId' => 0, '_format' => 'json']),
            'available_accessories_url' => $this->urls->generate('admin.product.accessories-content', ['productId' => (int) $product->getId(), 'categoryId' => 0, '_format' => 'json']),
            'update_content_position_url' => $this->urls->generate('admin.product.update-content-position'),
            'update_accessory_position_url' => $this->urls->generate('admin.product.update-accessory-position'),
            'token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return list<array{id: int, title: string, level: int}>
     */
    private function folderTree(string $locale, int $parentId = 0, int $level = 0): array
    {
        $items = [];
        $folders = FolderQuery::create()
            ->filterByParent($parentId)
            ->orderByPosition()
            ->find();

        foreach ($folders as $folder) {
            \assert($folder instanceof Folder);
            $folder->setLocale($locale);
            $items[] = ['id' => (int) $folder->getId(), 'title' => (string) $folder->getTitle(), 'level' => $level];
            foreach ($this->folderTree($locale, (int) $folder->getId(), $level + 1) as $child) {
                $items[] = $child;
            }
        }

        return $items;
    }

    /**
     * @param list<int> $excluded
     *
     * @return list<array{id: int, title: string, level: int, disabled: bool}>
     */
    private function categoryTree(string $locale, int $parentId = 0, int $level = 0, array $excluded = []): array
    {
        $items = [];
        $categories = CategoryQuery::create()
            ->filterByParent($parentId)
            ->orderByPosition()
            ->find();

        foreach ($categories as $category) {
            \assert($category instanceof Category);
            $category->setLocale($locale);
            $id = (int) $category->getId();
            $items[] = ['id' => $id, 'title' => (string) $category->getTitle(), 'level' => $level, 'disabled' => \in_array($id, $excluded, true)];
            foreach ($this->categoryTree($locale, $id, $level + 1, $excluded) as $child) {
                $items[] = $child;
            }
        }

        return $items;
    }

    /**
     * @return list<array{id: int, title: string, position: int, url: string}>
     */
    private function assignedContents(Product $product, string $locale): array
    {
        $assignments = ProductAssociatedContentQuery::create()
            ->filterByProductId((int) $product->getId())
            ->orderByPosition()
            ->find();

        $items = [];
        foreach ($assignments as $assignment) {
            $content = ContentQuery::create()->findPk((int) $assignment->getContentId());
            if ($content === null) {
                continue;
            }
            $content->setLocale($locale);
            $items[] = [
                'id' => (int) $content->getId(),
                'title' => (string) $content->getTitle(),
                'position' => (int) $assignment->getPosition(),
                'url' => $this->urls->generate('admin.content.update', ['content_id' => (int) $content->getId()]),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{id: int, accessory_id: int, title: string, position: int}>
     */
    private function assignedAccessories(Product $product, string $locale): array
    {
        $assignments = AccessoryQuery::create()
            ->filterByProductId((int) $product->getId())
            ->orderByPosition()
            ->find();

        $items = [];
        foreach ($assignments as $assignment) {
            $accessory = ProductQuery::create()->findPk((int) $assignment->getAccessory());
            if ($accessory === null) {
                continue;
            }
            $accessory->setLocale($locale);
            $items[] = [
                'id' => (int) $assignment->getId(),
                'accessory_id' => (int) $accessory->getId(),
                'title' => (string) $accessory->getTitle(),
                'position' => (int) $assignment->getPosition(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function additionalCategories(Product $product, string $locale, int $defaultCategoryId): array
    {
        $assignments = ProductCategoryQuery::create()
            ->filterByProductId((int) $product->getId())
            ->filterByDefaultCategory(false)
            ->find();

        $items = [];
        foreach ($assignments as $assignment) {
            $categoryId = (int) $assignment->getCategoryId();
            if ($categoryId === $defaultCategoryId) {
                continue;
            }
            $category = CategoryQuery::create()->findPk($categoryId);
            if ($category === null) {
                continue;
            }
            $category->setLocale($locale);
            $items[] = ['id' => $categoryId, 'title' => (string) $category->getTitle()];
        }

        return $items;
    }
}

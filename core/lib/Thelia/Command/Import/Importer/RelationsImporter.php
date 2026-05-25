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

namespace Thelia\Command\Import\Importer;

use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\Accessory;
use Thelia\Model\CategoryAssociatedContent;

/**
 * Cross-links catalog entities once products, categories and contents exist:
 * product accessories and category-associated advice contents.
 */
final class RelationsImporter extends AbstractDemoImporter
{
    /**
     * Advice contents surfaced on the categories they are relevant to.
     *
     * @var array<string, list<string>>
     */
    private const CATEGORY_CONTENTS = [
        'Cleaning Tissue' => ['Chairs', 'Armchairs', 'Sofas'],
        'Maintenance of wood' => ['Chairs', 'Stools'],
        'Maintenance of leather' => ['Armchairs', 'Sofas'],
    ];

    public function priority(): int
    {
        return 85;
    }

    public function description(): string
    {
        return 'Accessories and category contents';
    }

    public function import(DemoImportContext $context): void
    {
        $this->importAccessories($context);
        $this->importCategoryContents($context);
    }

    private function importAccessories(DemoImportContext $context): void
    {
        $products = $context->products;
        $count = \count($products);
        if ($count < 2) {
            return;
        }

        foreach ($products as $index => $product) {
            for ($offset = 1; $offset <= 2; ++$offset) {
                $accessory = $products[($index + $offset) % $count];
                if ($accessory->getId() === $product->getId()) {
                    continue;
                }

                (new Accessory())
                    ->setProductId((int) $product->getId())
                    ->setAccessory((int) $accessory->getId())
                    ->setPosition($offset)
                    ->save($context->connection);
            }
        }
    }

    private function importCategoryContents(DemoImportContext $context): void
    {
        foreach (self::CATEGORY_CONTENTS as $contentTitle => $categoryTitles) {
            $content = $context->contentsByTitle[$contentTitle] ?? null;
            if (null === $content) {
                continue;
            }

            foreach ($categoryTitles as $position => $categoryTitle) {
                $category = $context->categoriesByTitle[$categoryTitle] ?? null;
                if (null === $category) {
                    continue;
                }

                (new CategoryAssociatedContent())
                    ->setCategoryId((int) $category->getId())
                    ->setContentId((int) $content->getId())
                    ->setPosition($position + 1)
                    ->save($context->connection);
            }
        }
    }
}

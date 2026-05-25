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
use Thelia\Model\Category;
use Thelia\Model\CategoryImage;

final class CategoriesImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 70;
    }

    public function description(): string
    {
        return 'Categories';
    }

    public function import(DemoImportContext $context): void
    {
        $templateId = (int) $context->template()->getId();

        $positions = [];
        foreach ($this->readCsv($context->dataDir.'categories.csv') as $data) {
            $titleUk = trim($data[1]);
            $parentTitle = trim($data[7] ?? '');
            $parentId = '' !== $parentTitle && isset($context->categoriesByTitle[$parentTitle])
                ? (int) $context->categoriesByTitle[$parentTitle]->getId()
                : 0;
            $positions[$parentId] = ($positions[$parentId] ?? 0) + 1;

            $category = (new Category())
                ->setDefaultTemplateId($templateId)
                ->setVisible(1)
                ->setPosition($positions[$parentId])
                ->setParent($parentId)
                ->setLocale('fr_FR')->setTitle(trim($data[0]))->setChapo($data[2])->setDescription($data[4])
                ->setLocale('en_US')->setTitle($titleUk)->setChapo($data[3])->setDescription($data[5]);
            $category->save($context->connection);

            $context->categoriesByTitle[$titleUk] = $category;

            if ($context->withImages) {
                foreach (explode(';', $data[6]) as $imageName) {
                    $imageName = trim($imageName);
                    if ('' === $imageName) {
                        continue;
                    }

                    (new CategoryImage())->setCategory($category)->setFile($imageName)->save($context->connection);
                    $this->copyImage($context, $imageName, 'category');
                }
            }
        }
    }
}

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

        $position = 0;
        foreach ($this->readCsv($context->dataDir.'categories.csv') as $data) {
            $title = trim($data[1]);

            $category = (new Category())
                ->setDefaultTemplateId($templateId)
                ->setVisible(1)
                ->setPosition(++$position)
                ->setParent(0)
                ->setLocale('fr_FR')->setTitle($title)->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle($title)->setChapo('Eos perspiciatis.')->setDescription('Eos velit enim autem eum nihil sunt ut. Porro ipsa deleniti dolore molestiae aut omnis autem.');
            $category->save($context->connection);

            $context->categoriesByTitle[$title] = $category;

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

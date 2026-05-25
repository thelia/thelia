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
use Thelia\Model\Folder;
use Thelia\Model\FolderImage;

final class FoldersImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 40;
    }

    public function description(): string
    {
        return 'Content folders';
    }

    public function import(DemoImportContext $context): void
    {
        $position = 0;
        foreach ($this->readCsv($context->dataDir.'folders.csv') as $data) {
            $folder = (new Folder())
                ->setVisible(1)
                ->setPosition(++$position)
                ->setLocale('fr_FR')->setTitle(trim($data[0]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle(trim($data[1]))->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.');
            $folder->save($context->connection);

            $context->foldersByTitle[trim($data[1])] = $folder;

            if ($context->withImages) {
                foreach (explode(';', $data[6]) as $imageName) {
                    $imageName = trim($imageName);
                    if ('' === $imageName) {
                        continue;
                    }

                    (new FolderImage())->setFolderId($folder->getId())->setFile($imageName)->save($context->connection);
                    $this->copyImage($context, $imageName, 'folder');
                }
            }
        }
    }
}

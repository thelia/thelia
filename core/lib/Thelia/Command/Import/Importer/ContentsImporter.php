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
use Thelia\Model\Content;
use Thelia\Model\ContentImage;

final class ContentsImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 50;
    }

    public function description(): string
    {
        return 'CMS contents';
    }

    public function import(DemoImportContext $context): void
    {
        foreach ($this->readCsv($context->dataDir.'contents.csv') as $data) {
            $content = (new Content())
                ->setVisible(1)
                ->setLocale('fr_FR')->setTitle(trim($data[0]))->setChapo($data[2])->setDescription($data[4])
                ->setLocale('en_US')->setTitle(trim($data[1]))->setChapo($data[3])->setDescription($data[5]);

            foreach (explode(';', $data[7]) as $folderTitle) {
                $folderTitle = trim($folderTitle);
                if (\array_key_exists($folderTitle, $context->foldersByTitle)) {
                    $content->addFolder($context->foldersByTitle[$folderTitle]);
                }
            }

            $content->getContentFolders()->getFirst()?->setDefaultFolder(1)->save($context->connection);
            $content->save($context->connection);

            if ($context->withImages) {
                foreach (explode(';', $data[6]) as $imageName) {
                    $imageName = trim($imageName);
                    if ('' === $imageName) {
                        continue;
                    }

                    (new ContentImage())->setContentId($content->getId())->setFile($imageName)->save($context->connection);
                    $this->copyImage($context, $imageName, 'content');
                }
            }

            $context->contentsByTitle[trim($data[1])] = $content;
        }
    }
}

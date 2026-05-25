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
use Thelia\Model\Brand;
use Thelia\Model\BrandImage;

final class BrandsImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 30;
    }

    public function description(): string
    {
        return 'Brands';
    }

    public function import(DemoImportContext $context): void
    {
        $position = 0;
        foreach ($this->readCsv($context->dataDir.'brand.csv') as $data) {
            $brandTitle = trim($data[0]);
            $brand = (new Brand())
                ->setVisible(1)
                ->setPosition(++$position)
                ->setLocale('fr_FR')->setTitle($brandTitle)->setChapo('Aut voluptas.')->setDescription('Et in ea corrupti sequi enim et. Et nobis similique velit occaecati.')
                ->setLocale('en_US')->setTitle($brandTitle)->setChapo('Eos perspiciatis.')->setDescription('Eos velit enim autem eum nihil sunt ut. Porro ipsa deleniti dolore molestiae aut omnis autem.');
            $brand->save($context->connection);

            $context->brandsByTitle[$brandTitle] = $brand;

            if ($context->withImages) {
                $this->importLogo($context, $brand, $data[1]);
            }
        }
    }

    private function importLogo(DemoImportContext $context, Brand $brand, string $imageList): void
    {
        $logoId = null;
        foreach (explode(';', $imageList) as $imageName) {
            $imageName = trim($imageName);
            if ('' === $imageName) {
                continue;
            }

            $brandImage = new BrandImage();
            $brandImage->setBrandId($brand->getId())->setFile($imageName)->save($context->connection);
            $logoId ??= $brandImage->getId();

            $this->copyImage($context, $imageName, 'brand');
        }

        if (null !== $logoId) {
            $brand->setLogoImageId($logoId)->save($context->connection);
        }
    }
}

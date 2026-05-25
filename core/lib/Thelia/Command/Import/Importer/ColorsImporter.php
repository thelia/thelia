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
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;

final class ColorsImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 20;
    }

    public function description(): string
    {
        return 'Color attributes';
    }

    public function import(DemoImportContext $context): void
    {
        $attribute = (new Attribute())
            ->setPosition(1)
            ->setLocale('fr_FR')->setTitle('Couleur')
            ->setLocale('en_US')->setTitle('Colors');

        $position = 0;
        foreach ($this->readCsv($context->dataDir.'colors.csv', skipHeader: false) as $data) {
            $attribute->addAttributeAv(
                (new AttributeAv())
                    ->setPosition(++$position)
                    ->setLocale('fr_FR')->setTitle($data[0])
                    ->setLocale('en_US')->setTitle($data[1]),
            );
        }

        $attribute->save($context->connection);
        $context->setColorsAttribute($attribute);
    }
}

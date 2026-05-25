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
use Thelia\Model\AttributeTemplate;
use Thelia\Model\FeatureTemplate;
use Thelia\Model\Template;

final class TemplateImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 60;
    }

    public function description(): string
    {
        return 'Product template';
    }

    public function import(DemoImportContext $context): void
    {
        $template = (new Template())
            ->setLocale('fr_FR')->setName('template de démo')
            ->setLocale('en_US')->setName('demo template');
        $template->save($context->connection);
        $context->setTemplate($template);

        (new AttributeTemplate())
            ->setTemplate($template)
            ->setAttribute($context->colorsAttribute())
            ->save($context->connection);

        (new FeatureTemplate())
            ->setTemplate($template)
            ->setFeature($context->materialsFeature())
            ->save($context->connection);
    }
}

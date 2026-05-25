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
use Thelia\Model\ConfigQuery;

final class ConfigImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 140;
    }

    public function description(): string
    {
        return 'Store configuration';
    }

    public function import(DemoImportContext $context): void
    {
        ConfigQuery::write('store_name', 'Thelia');
        ConfigQuery::write('store_description', 'E-commerce solution based on Symfony');
        ConfigQuery::write('store_email', 'Thelia');
        ConfigQuery::write('store_address1', '5 rue Rochon');
        ConfigQuery::write('store_city', 'Clermont-Ferrrand');
        ConfigQuery::write('store_phone', '+(33)444053102');
        ConfigQuery::write('store_email', 'contact@thelia.net');
        ConfigQuery::write('information_folder_id', $context->foldersByTitle['Information']->getId());
        ConfigQuery::write('terms_conditions_content_id', $context->contentsByTitle['Terms and Conditions']->getId());
    }
}

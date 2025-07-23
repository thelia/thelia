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

namespace Thelia\Core\Template\Loop;

use Thelia\Model\ImportQuery;

/**
 * Class Import.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class Import extends ImportExportType
{
    protected function getBaseUrl(): string
    {
        return $this->container->getParameter('import.base_url');
    }

    protected function getQueryModel(): ImportQuery
    {
        return ImportQuery::create();
    }

    protected function getCategoryName(): string
    {
        return 'ImportCategoryId';
    }
}

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

use Thelia\Model\ExportQuery;

/**
 * Class Export.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class Export extends ImportExportType
{
    protected function getBaseUrl(): string
    {
        return $this->container->getParameter('export.base_url');
    }

    protected function getQueryModel(): ExportQuery
    {
        return ExportQuery::create();
    }

    protected function getCategoryName(): string
    {
        return 'ExportCategoryId';
    }
}

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
    protected function getBaseUrl()
    {
        return $this->container->getParameter('export.base_url');
    }

    /**
     * @return ExportQUery
     */
    protected function getQueryModel()
    {
        return ExportQuery::create();
    }

    protected function getCategoryName(): string
    {
        return 'ExportCategoryId';
    }
}

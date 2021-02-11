<?php

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
 * Class Import
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class Import extends ImportExportType
{
    protected function getBaseUrl()
    {
        return $this->container->getParameter("import.base_url");
    }

    /**
     * @return ImportQuery
     */
    protected function getQueryModel()
    {
        return ImportQuery::create();
    }

    protected function getCategoryName()
    {
        return "ImportCategoryId";
    }
}

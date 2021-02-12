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

use Thelia\Model\ExportCategoryQuery;

/**
 * Class ExportCategory.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportCategory extends ImportExportCategory
{
    /**
     * @return ExportCategoryQuery
     */
    protected function getQueryModel()
    {
        return ExportCategoryQuery::create();
    }
}

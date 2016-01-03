<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Thelia\Model\ExportQuery;

/**
 * Class Export
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class Export extends ImportExportType
{
    protected function getBaseUrl()
    {
        return $this->container->getParameter("export.base_url");
    }

    protected function getQueryModel()
    {
        return ExportQuery::create();
    }

    protected function getCategoryName()
    {
        return "ExportCategoryId";
    }
}

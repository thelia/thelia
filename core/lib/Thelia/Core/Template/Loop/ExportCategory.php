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

use Thelia\Model\ExportCategoryQuery;

/**
 * Class ExportCategory
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportCategory extends ImportExportCategory
{
    protected function getQueryModel()
    {
        return ExportCategoryQuery::create();
    }
}

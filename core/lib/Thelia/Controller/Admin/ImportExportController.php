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

namespace Thelia\Controller\Admin;
use Thelia\Core\HttpFoundation\Response;

/**
 * Class ImportExportController
 * @package Thelia\Controller\Admin
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportExportController extends BaseAdminController
{
    public function import()
    {

    }

    public function export()
    {

    }

    public function importView()
    {
        return $this->render("import-page");
    }

    public function exportView()
    {
        return $this->render("export-page");
    }
} 
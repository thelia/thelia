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
use Thelia\Model\ExportQuery;

/**
 * Class ImportExportController
 * @package Thelia\Controller\Admin
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportExportController extends BaseAdminController
{
    public function import($id)
    {

    }

    public function export($id)
    {

    }

    public function importView($id)
    {
        if (null === $export = $this->getExport($id))  {
            return $this->render("404");
        }

        return $this->render("import-page");
    }

    public function exportView($id)
    {
        if (null === $export = $this->getExport($id))  {
            return $this->render("404");
        }

        $this->getParserContext()
            ->set("ID", $export->getId())
            ->set("TITLE", $export->getTitle())
        ;

        return $this->render("export-page");
    }

    protected function getExport($id)
    {
        $export = ExportQuery::create()
            ->findPk($id)
        ;

        return $export;
    }
} 
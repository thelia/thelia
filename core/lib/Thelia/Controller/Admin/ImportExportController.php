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
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Export as ExportLoop;
use Thelia\Core\Template\Loop\Import as ImportLoop;
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

        /**
         * Use the loop to inject the same vars in Smarty
         */
        $loop = new ImportLoop($this->container);

        $loop->initializeArgs([
            "export" => $export->getId()
        ]);

        $query = $loop->buildModelCriteria();
        $result= $query->find();

        $results = $loop->parseResults(
            new LoopResult($result)
        );

        $parserContext = $this->getParserContext();

        /** @var \Thelia\Core\Template\Element\LoopResultRow $row */
        foreach ($results as $row) {
            foreach ($row->getVarVal() as $name=>$value) {
                $parserContext->set($name, $value);
            }
        }

        /** Then render the form */
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->render("ajax/import-modal");
        } else {
            return $this->render("import-page");
        }
    }

    public function exportView($id)
    {
        if (null === $export = $this->getExport($id))  {
            return $this->render("404");
        }

        /**
         * Use the loop to inject the same vars in Smarty
         */
        $loop = new ExportLoop($this->container);

        $loop->initializeArgs([
            "export" => $export->getId()
        ]);

        $query = $loop->buildModelCriteria();
        $result= $query->find();

        $results = $loop->parseResults(
            new LoopResult($result)
        );

        $parserContext = $this->getParserContext();

        /** @var \Thelia\Core\Template\Element\LoopResultRow $row */
        foreach ($results as $row) {
            foreach ($row->getVarVal() as $name=>$value) {
                $parserContext->set($name, $value);
            }
        }

        /** Then render the form */
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->render("ajax/export-modal");
        } else {
            return $this->render("export-page");
        }
    }

    protected function getExport($id)
    {
        $export = ExportQuery::create()
            ->findPk($id)
        ;

        return $export;
    }
} 
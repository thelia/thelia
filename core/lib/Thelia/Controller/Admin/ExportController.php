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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\ImportExportType;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ExportQuery;

/**
 * Class ExportController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ExportController extends BaseAdminController
{
    public function indexAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::VIEW])) {
            return $response;
        }

        $export_order = $this->getRequest()->query->get("export_order");

        if (!in_array($export_order, ImportExportType::getAllowedOrders())) {
            $export_order = ImportExportType::DEFAULT_ORDER;
        }

        $this->getParserContext()
            ->set("export_order", $export_order)
        ;

        return $this->render('export');
    }

    public function changePosition($action, $id)
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $export = $this->getExport($id);

        if ($action === "up") {
            $export->upPosition();
        } elseif ($action === "down") {
            $export->downPosition();
        }

        $this->getParserContext()
            ->set("export_order", "manual")
        ;

        return $this->render('export');
    }

    public function updatePosition($id, $value)
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $export = $this->getExport($id);

        $export->updatePosition($value);

        $this->getParserContext()
            ->set("export_order", "manual")
        ;

        return $this->render('export');
    }


    protected function getExport($id)
    {
        $export = ExportQuery::create()->findPk($id);

        if (null === $export) {
            throw new \ErrorException(
                Translator::getInstance()->trans(
                    "There is no id \"%id\" in the exports",
                    [
                        "%id" => $id
                    ]
                )
            );
        }
        return $export;
    }
}

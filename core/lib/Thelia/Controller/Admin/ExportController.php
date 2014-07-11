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
use Thelia\Model\ExportCategoryQuery;
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

        $this->setOrders();

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

        $this->setOrders(null, "manual");

        return $this->render('export');
    }

    public function updatePosition($id, $value)
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $export = $this->getExport($id);

        $export->updatePosition($value);

        $this->setOrders(null, "manual");

        return $this->render('export');
    }

    public function changeCategoryPosition($action, $id)
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $category = $this->getCategory($id);

        if ($action === "up") {
            $category->upPosition();
        } elseif ($action === "down") {
            $category->downPosition();
        }

        $this->setOrders("manual");

        return $this->render('export');
    }

    public function updateCategoryPosition($id, $value)
    {
        if (null !== $response = $this->checkAuth([AdminResources::EXPORT], [], [AccessManager::UPDATE])) {
            return $response;
        }

        $category = $this->getCategory($id);

        $category->updatePosition($value);

        $this->setOrders("manual");

        return $this->render('export');
    }

    protected function setOrders($category = null, $export = null)
    {
        if ($category === null) {
            $category = $this->getRequest()->query->get("category_order", "manual");
        }

        if ($export === null) {
            $export = $this->getRequest()->query->get("export_order", "manual");
        }

        $this->getParserContext()
            ->set("category_order", $category)
        ;

        $this->getParserContext()
            ->set("export_order", $export)
        ;
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

    protected function getCategory($id)
    {
        $category = ExportCategoryQuery::create()->findPk($id);

        if (null === $category) {
            throw new \ErrorException(
                Translator::getInstance()->trans(
                    "There is no id \"%id\" in the export categories",
                    [
                        "%id" => $id
                    ]
                )
            );
        }
        return $category;
    }
}

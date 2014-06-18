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

namespace Thelia\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Category;


/**
 * Class CategoryController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CategoryController extends BaseApiController
{
    public function listAction()
    {
        $this->checkAuth(AdminResources::CATEGORY, [], AccessManager::VIEW);
        $request = $this->getRequest();

        if ($request->query->has('id')) {
            $request->query->remove('id');
        }

        $params = array_merge(
            $request->query->all(),
            [
                'limit' => $request->query->get('limit', 10),
                'offset' => $request->query->get('offset', 0)
            ]
        );

        return JsonResponse::create($this->baseCategorySearch($params));
    }

    public function getAction($category_id)
    {
        $this->checkAuth(AdminResources::CATEGORY, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $params = array_merge(
            $request->query->all(),
            [
                'id' => $category_id,
                'limit' => 1
            ]
        );

        $category = $this->baseCategorySearch($params);

        if ($category->isEmpty()) {
            throw new HttpException(404, sprintf('{"error": "category with id %d not found"}', $category_id));
        }

        return JsonResponse::create($category);
    }

    private function baseCategorySearch($params)
    {
        $categoryLoop = new Category($this->getContainer());
        $categoryLoop->initializeArgs($params);
        $paginate = 0;
        return $categoryLoop->exec($paginate);
    }
}
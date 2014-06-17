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
use Thelia\Core\Template\Loop\Product;


/**
 * Class ProductController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ProductController extends BaseApiController
{
    public function listAction()
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);
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

        return JsonResponse::create($this->baseProductSearch($params));
    }

    public function getProductAction($product_id)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $params = array_merge(
            $request->query->all(),
            ['id' => $product_id]
        );

        $results = $this->baseProductSearch($params);

        if ($results->isEmpty()) {
            throw new HttpException(404, sprintf('{"error": "product with id %d not found"}', $product_id));
        }

        return JsonResponse::create($results);
    }

    private function baseProductSearch($params)
    {
        $productLoop = new Product($this->getContainer());
        $productLoop->initializeArgs($params);

        $paginate = 0;
        return $productLoop->exec($paginate);
    }
}
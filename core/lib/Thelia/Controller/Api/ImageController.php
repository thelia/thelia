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
use Thelia\Core\Template\Loop\Image;
use Thelia\Model\ProductQuery;

/**
 * Class ImageController
 * @package Thelia\Controller\Api
 * @author manuel raynaud <manu@thelia.net>
 */
class ImageController extends BaseApiController
{
    /**
     * @param $entityId source's primary key (product's pk, category's pk, etc)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction($entityId)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);

        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkEntityExists($entity, $entityId);

        if ($request->query->has('id')) {
            $request->query->remove('id');
        }

        $params = array_merge(
            $request->query->all(),
            [
                'limit' => $request->query->get('limit', 10),
                'offset' => $request->query->get('offset', 0),
                'source' => strtolower($entity),
                'source_id' => $entityId
            ]
        );

        $imageLoop = new Image($this->getContainer());
        $imageLoop->initializeArgs($params);

        $paginate = 0;

        return JsonResponse::create($imageLoop->exec($paginate));
    }

    public function getImageAction($product_id, $image_id)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);
    }

    /**
     * @param string $entity image's source (eg : Product, Category, etc)
     * @param int $entityId source's primary key
     */
    protected function checkEntityExists($entity, $entityId)
    {
        $entity = ucfirst($entity);

        $class = sprintf("Thelia\\Model\\%sQuery", $entity);

        $method = new \ReflectionMethod($class, 'create');
        $search = $method->invoke(null);

        $entityModel = $search->findPk($entityId);

        if (null === $entityModel) {
            throw new HttpException(404, sprintf('{"error": "%s with id %d not found"}', $entity, $entityId));
        }
    }
}

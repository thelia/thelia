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
use Thelia\Controller\Admin\FileController;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Image;
use Thelia\Files\FileConfiguration;
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

        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkAuth(AdminResources::retrieve($entity), [], AccessManager::VIEW);

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

    public function getImageAction($entityId, $imageId)
    {
        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkAuth(AdminResources::retrieve($entity), [], AccessManager::VIEW);

        $this->checkEntityExists($entity, $entityId);

        $params = array_merge(
            $request->query->all(),
            [
                'source' => strtolower($entity),
                'source_id' => $entityId,
                'id' => $imageId
            ]
        );

        $imageLoop = new Image($this->getContainer());
        $imageLoop->initializeArgs($params);

        $paginate = 0;

        $results = $imageLoop->exec($paginate);

        if ($results->isEmpty()) {
            throw new HttpException(404, sprintf('{"error": "Image with id %d not found"}', $imageId));
        }

        return JsonResponse::create($results);
    }

    public function createImageAction($entityId)
    {
        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkAuth(AdminResources::retrieve($entity), [], AccessManager::UPDATE);

        $fileController = new FileController();
        $fileController->setContainer($this->getContainer());

        $config = FileConfiguration::getImageConfig();

        $files = $request->files->all();

        $errors = [];

        foreach ($files as $file) {
            try {
                $fileController->processImage(
                    $file,
                    $entityId,
                    $entity,
                    $config['objectType'],
                    $config['validMimeTypes'],
                    $config['extBlackList']
                );
            } catch (\Exception $e) {
                $errors = [
                    'file' => $file->getClientOriginalName(),
                    'message' => $e->getMessage(),
                ];
            }

        }

        if (!empty($errors)) {
            $response = JsonResponse::create($errors, 500);
        } else {
            $response = $this->listAction($entityId);
            $response->setStatusCode(201);
        }

        return $response;
    }

    public function deleteImageAction($entityId, $imageId)
    {
        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkAuth(AdminResources::retrieve($entity), [], AccessManager::UPDATE);

        $this->checkEntityExists($entity, $entityId);

        $class = sprintf("Thelia\\Model\\%sImageQuery", ucfirst($entity));

        $method = new \ReflectionMethod($class, 'create');
        $search = $method->invoke(null);

        $entityModel = $search->findPk($imageId);

        if (null === $entityModel) {
            throw new HttpException(404, sprintf('{"error": "image with id %d not found"}', $imageId));
        }

        try {
            $fileDeleteEvent =  new FileDeleteEvent($entityModel);
            $this->dispatch(TheliaEvents::IMAGE_DELETE, $fileDeleteEvent);
            return Response::create('', 204);
        } catch (\Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
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

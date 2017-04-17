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

use Propel\Runtime\Propel;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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

/**
 * Class ImageController
 * @package Thelia\Controller\Api
 * @author manuel raynaud <manu@raynaud.io>
 */
class ImageController extends BaseApiController
{
    /**
     * @param integer $entityId source's primary key (product's pk, category's pk, etc)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction($entityId)
    {
        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkAuth($this->getAdminResources()->getResource($entity), [], AccessManager::VIEW);

        $this->checkEntityExists($entity, $entityId);

        if ($request->query->has('id')) {
            $request->query->remove('id');
        }

        $params = array_merge(
            [
                'limit' => 10,
                'offset' => 0,
            ],
            $request->query->all(),
            [
                'source' => strtolower($entity),
                'source_id' => $entityId
            ]
        );

        $imageLoop = new Image($this->getContainer());
        $imageLoop->initializeArgs($params);

        return JsonResponse::create($imageLoop->exec($paginate));
    }

    public function getImageAction($entityId, $imageId)
    {
        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkAuth($this->getAdminResources()->getResource($entity), [], AccessManager::VIEW);

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

        $results = $imageLoop->exec($paginate);

        if ($results->isEmpty()) {
            return JsonResponse::create(
                array(
                    'error' => sprintf('image with id %d not found', $imageId)
                ),
                404
            );
        }

        return JsonResponse::create($results);
    }

    public function createImageAction($entityId)
    {
        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkAuth($this->getAdminResources()->getResource($entity), [], AccessManager::UPDATE);

        $fileController = new FileController();
        $fileController->setContainer($this->getContainer());

        $config = FileConfiguration::getImageConfig();
        $files = $request->files->all();

        $errors = null;

        foreach ($files as $file) {
            $errors = $this->processImage($fileController, $file, $entityId, $entity, $config);
        }

        if (!empty($errors)) {
            $response = JsonResponse::create($errors, 500);
        } else {
            $response = $this->listAction($entityId);
            $response->setStatusCode(201);
        }

        return $response;
    }

    public function updateImageAction($entityId, $imageId)
    {
        $request = $this->getRequest();
        $entity = $request->attributes->get('entity');

        $this->checkAuth($this->getAdminResources()->getResource($entity), [], AccessManager::UPDATE);

        $this->checkEntityExists($entity, $entityId);
        $this->checkImage($entity, $imageId);

        /**
         * If there is a file, treat it
         */
        $hasImage = $request->files->count() == 1;

        if ($hasImage) {
            $this->processImageUpdate($entityId, $entity);
        }

        /**
         * Then treat i18n form
         */

        $baseForm = $this->createForm(null, "image", [], array(
            "csrf_protection" => false,
            "method" => "PUT",
        ));

        $baseForm->getFormBuilder()
            ->addEventListener(
                FormEvents::PRE_SUBMIT,
                function (FormEvent $event) use ($entity, $imageId) {
                    $this->onFormPreSubmit($event, $imageId, $entity);
                }
            )
        ;

        $con = Propel::getConnection();
        $con->beginTransaction();

        try {
            $form = $this->validateForm($baseForm);
            $image = $this->checkImage($entity, $imageId);

            foreach ($form->getData()["i18n"] as $locale => $i18nRow) {
                $image->getTranslation($locale)
                    ->setTitle($i18nRow["title"])
                    ->setChapo($i18nRow["chapo"])
                    ->setDescription($i18nRow["description"])
                    ->setPostscriptum($i18nRow["postscriptum"])
                ;
            }

            $image->save();
            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            if (!$hasImage) {
                throw new HttpException(500, $e->getMessage());
            }
        }

        return $this->getImageAction($entityId, $imageId)->setStatusCode(201);
    }

    protected function onFormPreSubmit(FormEvent $event, $entityId, $entity)
    {
        $data = $event->getData();
        $temporaryRegister = array();

        foreach ($data["i18n"] as $key => &$value) {
            $temporaryRegister["i18n"][$value["locale"]] = $value;

            unset($data["i18n"][$key]);
        }

        $data = $temporaryRegister;

        $i18ns = $this->getImageI18ns($entity, $entityId);

        foreach ($i18ns as $i18n) {
            $row = array(
                "locale" => $i18n->getLocale(),
                "title" => $i18n->getTitle(),
                "chapo" => $i18n->getChapo(),
                "description" => $i18n->getDescription(),
                "postscriptum" => $i18n->getPostscriptum(),
            );

            if (!isset($data["i18n"][$i18n->getLocale()])) {
                $data["i18n"][$i18n->getLocale()] = array();
            }

            $data["i18n"][$i18n->getLocale()] = array_merge(
                $row,
                $data["i18n"][$i18n->getLocale()]
            );
        }

        $event->setData($data);
    }

    protected function processImageUpdate($entityId, $entity)
    {
        $file = $this->getRequest()->files->all();
        reset($file);
        $file = current($file);

        $fileController = new FileController();
        $fileController->setContainer($this->getContainer());

        $config = FileConfiguration::getImageConfig();

        $errors = $this->processImage($fileController, $file, $entityId, $entity, $config);

        if (!empty($errors)) {
            throw new HttpException(500, json_encode($errors), null, ["Content-Type" => "application/json"]);
        }
    }

    protected function processImage(FileController $fileController, $file, $entityId, $entity, array $config)
    {
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
            return [
                'file' => $file->getClientOriginalName(),
                'message' => $e->getMessage(),
            ];
        }

        return null;
    }

    public function deleteImageAction($entityId, $imageId)
    {
        $request = $this->getRequest();

        $entity = $request->attributes->get('entity');

        $this->checkAuth($this->getAdminResources()->getResource($entity), [], AccessManager::UPDATE);

        $this->checkEntityExists($entity, $entityId);

        $entityModel = $this->checkImage($entity, $imageId);

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
            throw new HttpException(
                404,
                sprintf('{"error": "%s with id %d not found"}', $entity, $entityId),
                null,
                ["Content-Type" => "application/json"]
            );
        }

        return $entityModel;
    }

    /**
     * @param $entity
     * @param $imageId
     * @return \Thelia\Files\FileModelInterface
     */
    protected function checkImage($entity, $imageId)
    {
        $class = sprintf("Thelia\\Model\\%sImageQuery", ucfirst($entity));

        $method = new \ReflectionMethod($class, 'create');
        $search = $method->invoke(null);

        $imageModel = $search->findPk($imageId);

        if (null === $imageModel) {
            throw new HttpException(
                404,
                sprintf('{"error": "image with id %d not found"}', $imageId),
                null,
                ["Content-Type" => "application/json"]
            );
        }

        return $imageModel;
    }

    /**
     * @param $entity
     * @param $imageId
     * @return \Propel\Runtime\Collection\ObjectCollection
     */
    protected function getImageI18ns($entity, $imageId)
    {
        $class = sprintf("Thelia\\Model\\%sImageI18nQuery", ucfirst($entity));

        $method = new \ReflectionMethod($class, 'create');
        $search = $method->invoke(null);

        $search->orderByLocale();

        return $search->findById($imageId);
    }
}

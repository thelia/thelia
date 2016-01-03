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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Product;
use Thelia\Form\Api\Product\ProductCreationForm;
use Thelia\Form\Api\Product\ProductModificationForm;
use Thelia\Model\ProductQuery;
use Thelia\Form\Definition\ApiForm;

/**
 * Class ProductController
 * @package Thelia\Controller\Api
 * @author Manuel Raynaud <manu@raynaud.io>
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

    public function getProductAction($productId)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $params = array_merge(
            $request->query->all(),
            ['id' => $productId]
        );

        $results = $this->baseProductSearch($params);

        if ($results->isEmpty()) {
            return JsonResponse::create(
                array(
                    'error' => sprintf('product with id %d not found', $productId)
                ),
                404
            );
        }

        return JsonResponse::create($results);
    }

    private function baseProductSearch($params)
    {
        $productLoop = new Product($this->getContainer());
        $productLoop->initializeArgs($params);

        return $productLoop->exec($paginate);
    }

    public function createAction()
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::CREATE);

        $form = $this->createForm(ApiForm::PRODUCT_CREATION, 'form', [], ['csrf_protection' => false]);

        try {
            $creationForm = $this->validateForm($form);

            $event = new ProductCreateEvent();
            $event->bindForm($creationForm);

            $this->dispatch(TheliaEvents::PRODUCT_CREATE, $event);

            $product = $event->getProduct();

            $updateEvent = new ProductUpdateEvent($product->getId());

            $updateEvent->bindForm($creationForm);

            $this->dispatch(TheliaEvents::PRODUCT_UPDATE, $updateEvent);

            $this->getRequest()->query->set('lang', $creationForm->get('locale')->getData());
            $response = $this->getProductAction($product->getId());
            $response->setStatusCode(201);

            return $response;
        } catch (\Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    public function updateAction($productId)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::UPDATE);

        $this->checkProductExists($productId);

        $form = $this->createForm(
            ApiForm::PRODUCT_MODIFICATION,
            'form',
            ['id' => $productId],
            [
                'csrf_protection' => false,
                'method' => 'PUT'
            ]
        );

        $request = $this->getRequest();

        $data = $request->request->all();
        $data['id'] = $productId;
        $request->request->add($data);

        try {
            $updateForm = $this->validateForm($form);

            $event = new ProductUpdateEvent($productId);
            $event->bindForm($updateForm);

            $this->dispatch(TheliaEvents::PRODUCT_UPDATE, $event);

            return JsonResponse::create(null, 204);
        } catch (\Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteAction($productId)
    {
        $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::DELETE);

        $this->checkProductExists($productId);

        try {
            $event = new ProductDeleteEvent($productId);

            $this->dispatch(TheliaEvents::PRODUCT_DELETE, $event);
            return Response::create('', 204);
        } catch (\Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param $productId
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function checkProductExists($productId)
    {
        $product = ProductQuery::create()
            ->findPk($productId);

        if (null === $product) {
            throw new HttpException(404, sprintf('{"error": "product with id %d not found"}', $productId), null, [
                "Content-Type" => "application/json"
            ]);
        }
    }
}

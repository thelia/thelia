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
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Category;
use Thelia\Form\CategoryCreationForm;
use Thelia\Form\CategoryModificationForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\CategoryQuery;


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

    public function createAction()
    {
        $this->checkAuth(AdminResources::CATEGORY, [], AccessManager::CREATE);
        $request = $this->getRequest();

        $form = new CategoryCreationForm($request, "form",[], ['csrf_protection' => false]);

        try {
            $categoryForm = $this->validateForm($form);

            $event = (new CategoryCreateEvent())
                ->setTitle($categoryForm->get('title')->getData())
                ->setLocale($categoryForm->get('locale')->getData())
                ->setVisible($categoryForm->get('visible')->getData())
                ->setParent($categoryForm->get('parent')->getData())
            ;

            $this->dispatch(TheliaEvents::CATEGORY_CREATE, $event);
            $category = $event->getCategory();

            $request->query->set('lang', $categoryForm->get('locale')->getData());
            $response = $this->getAction($category->getId());
            $response->setStatusCode(201);

            return $response;
        } catch(\Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 400);
        }
    }

    public function updateAction($category_id)
    {
        $this->checkAuth(AdminResources::CATEGORY, [], AccessManager::UPDATE);
        $request = $this->getRequest();

        $category = CategoryQuery::create()
            ->findPk($category_id);

        if (null === $category) {
            throw new HttpException(404, sprintf('{"error": "category with id %d not found"}', $category_id));
        }

        $form = new CategoryModificationForm($request, 'form', ['id' => $category_id], ['csrf_protection' => false]);

        try {
            $categoryForm = $this->validateForm($form);

            $event = (new CategoryUpdateEvent($category_id))
                ->setLocale($categoryForm->get('locale')->getData())
                ->setParent($categoryForm->get('parent')->getData())
                ->setTitle($categoryForm->get('title')->getData())
                ->setChapo($categoryForm->get('chapo')->getData())
                ->setDescription($categoryForm->get('description')->getData())
                ->setPostscriptum($categoryForm->get('postscriptum')->getData());

            $this->dispatch(TheliaEvents::CATEGORY_UPDATE, $event);

            return JsonResponse::create(null, 204);

        } catch(\Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 400);
        }
    }

    public function deleteAction($category_id)
    {
        $this->checkAuth(AdminResources::CATEGORY, [], AccessManager::DELETE);

        $category = CategoryQuery::create()
            ->findPk($category_id);

        if (null === $category) {
            throw new HttpException(404, sprintf('{"error": "category with id %d not found"}', $category_id));
        }

        try {
            $event = new CategoryDeleteEvent($category_id);
            $this->dispatch(TheliaEvents::CATEGORY_DELETE, $event);

            return Response::create('', 204);

        } catch(\Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 400);
        }
    }
}

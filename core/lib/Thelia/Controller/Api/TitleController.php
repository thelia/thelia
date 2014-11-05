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
use Thelia\Core\Event\CustomerTitle\CustomerTitleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\Title;
use Thelia\Model\CustomerTitleI18nQuery;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Map\CustomerTitleTableMap;

/**
 * Class TitleController
 * @package Thelia\Controller\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class TitleController extends BaseApiController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * Lists customer titles
     * @Route("/api/title")
     * @Method("GET")
     */
    public function listAction()
    {
        $this->checkAuth(AdminResources::TITLE, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $params = array_merge(
            [
                "limit" => 10,
            ],
            $request->query->all()
        );

        return JsonResponse::create($this->getTitle($params));
    }

    /**
     * @param $titleId
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * Get a title details
     * @Route("/api/title/{titleId}", requirements={"titleId" = "\d+"})
     * @Method("GET")
     */
    public function getTitleAction($titleId)
    {
        $this->checkAuth(AdminResources::TITLE, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $params = $request->query->all();
        $params['id'] = $titleId;

        $result = $this->getTitle($params);

        if ($result->isEmpty()) {
            throw new HttpException(404, sprintf('{"error": "title with id %d not found"}', $titleId));
        }

        return JsonResponse::create($result);
    }

    /**
     * @return JsonResponse
     *
     * Create a customer title
     * @Route("/api/title")
     * @Method("POST")
     */
    public function createAction()
    {
        $this->checkAuth(AdminResources::TITLE, [], AccessManager::CREATE);

        $baseForm = $this->createForm(null, "customer_title", [], array(
            "csrf_protection" => false,
            "cascade_validation" => true,
        ));

        $con = Propel::getConnection(CustomerTitleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            $i18n = $data["i18n"];
            $event = $this->createEvent($data);

            /**
             * The first row is the creation, after it's update
             */
            $this->hydrateEvent(array_shift($i18n), $event);

            $this->dispatch(TheliaEvents::CUSTOMER_TITLE_BEFORE_CREATE, $event);
            $this->dispatch(TheliaEvents::CUSTOMER_TITLE_CREATE, $event);
            $this->dispatch(TheliaEvents::CUSTOMER_TITLE_AFTER_CREATE, $event);

            $this->processUpdate($event, $i18n);

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            return new JsonResponse(["error" => $e->getMessage()], 500);
        }

        return new JsonResponse(
            $this->getTitle(
                array_merge(
                    $this->getRequest()->query->all(),
                    [
                        "id" => $event->getCustomerTitle()->getId(),
                    ]
                )
            ),
            201
        );
    }

    /**
     * @return JsonResponse
     *
     * Update a customer title
     * @Route("/api/title")
     * @Method("PUT")
     */
    public function updateAction()
    {
        $this->checkAuth(AdminResources::TITLE, [], AccessManager::UPDATE);

        $baseForm = $this->createForm(null, "customer_title", [], array(
            "csrf_protection" => false,
            "cascade_validation" => true,
            "validation_groups" => ["Default", "update"],
            "method" => "PUT",
        ));

        $baseForm->getFormBuilder()
            ->addEventListener(
                FormEvents::PRE_SUBMIT,
                [$this, "hydrateUpdateForm"]
            );

        $con = Propel::getConnection(CustomerTitleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            $i18n = $data["i18n"];
            $event = $this->createEvent($data);

            $this->processUpdate($event, $i18n);

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            return new JsonResponse(["error" => $e->getMessage()], 500);
        }

        return new JsonResponse(
            $this->getTitle(
                array_merge(
                    $this->getRequest()->query->all(),
                    ["id" => $event->getCustomerTitle()->getId()]
                )
            ),
            201
        );
    }

    /**
     * @param $titleId
     * @return JsonResponse
     *
     * Delete a customer title
     * @Route("/api/title/{titleId}", requirements={"titleId" = "\d+"})
     * @Method("DELETE")
     */
    public function deleteAction($titleId)
    {
        $this->checkAuth(AdminResources::TITLE, [], AccessManager::DELETE);

        $con = Propel::getConnection(CustomerTitleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $event = $this->createEvent(["title_id" => $titleId]);

            if (null === $event->getCustomerTitle()) {
                return new JsonResponse(
                    [
                        "error" => "The title id '%d' doesn't exist"
                    ],
                    404
                );
            }

            $this->dispatch(TheliaEvents::CUSTOMER_TITLE_DELETE, $event);

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            return new JsonResponse(["error" => $e->getMessage()], 500);
        }

        return new JsonResponse([], 201);
    }

    /**
     * @param $params
     * @return \Thelia\Core\Template\Element\LoopResult
     *
     * Get the results of customer-title loop
     */
    protected function getTitle($params)
    {
        $titleLoop = new Title($this->getContainer());
        $titleLoop->initializeArgs($params);

        return $titleLoop->exec($pagination);
    }

    /**
     * @param array $data
     * @return CustomerTitleEvent
     *
     * Handler to create the customer title event
     */
    protected function createEvent(array $data)
    {
        $event = new CustomerTitleEvent();

        if (isset($data["default"])) {
            $event->setDefault($data["default"]);
        }

        if (isset($data["title_id"])) {
            $event->setCustomerTitle(CustomerTitleQuery::create()->findPk($data["title_id"]));
        }

        return $event;
    }

    /**
     * @param array $i18nRow
     * @param CustomerTitleEvent $event
     *
     * Handler to hydrate the event with i18n data
     */
    protected function hydrateEvent(array $i18nRow, CustomerTitleEvent $event)
    {
        $event
            ->setShort($i18nRow["short"])
            ->setLong($i18nRow["long"])
            ->setLocale($i18nRow["locale"])
        ;
    }

    /**
     * @param CustomerTitleEvent $event
     * @param array $i18nRows
     *
     * Handler to process update for each i18n row
     */
    protected function processUpdate(CustomerTitleEvent $event, array $i18nRows)
    {
        while (null !== $i18n = array_shift($i18nRows)) {
            $this->hydrateEvent($i18n, $event);

            $this->dispatch(TheliaEvents::CUSTOMER_TITLE_BEFORE_UPDATE, $event);
            $this->dispatch(TheliaEvents::CUSTOMER_TITLE_UPDATE, $event);
            $this->dispatch(TheliaEvents::CUSTOMER_TITLE_AFTER_UPDATE, $event);
        }
    }

    /**
     * @param FormEvent $event
     *
     * This methods loads current title data into the update form.
     * It uses an event to load only needed ids.
     */
    public function hydrateUpdateForm(FormEvent $event)
    {
        $data = $event->getData();

        $title = CustomerTitleQuery::create()->findPk($data["title_id"]);
        $data["default"] = (bool) $title->getByDefault();

        $titleI18ns = CustomerTitleI18nQuery::create()
            ->filterById($data["title_id"])
            ->find()
            ->toKeyIndex('Locale')
        ;

        $i18n = &$data["i18n"];

        foreach ($data["i18n"] as $key => $value) {
            $i18n[$value["locale"]] = $value;

            unset($i18n[$key]);
        }


        /** @var \Thelia\Model\CustomerTitleI18n $titleI18n */
        foreach ($titleI18ns as $titleI18n) {
            $row = array();

            $row["locale"] = $locale = $titleI18n->getLocale();
            $row["short"] = $titleI18n->getShort();
            $row["long"] = $titleI18n->getLong();

            if (!isset($i18n[$locale])) {
                $i18n[$locale] = array();
            }

            $i18n[$locale] = array_merge($row, $i18n[$locale]);
        }

        $event->setData($data);
    }
}

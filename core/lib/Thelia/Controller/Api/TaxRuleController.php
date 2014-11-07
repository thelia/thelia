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

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\TaxRule;

/**
 * Class TaxRuleController
 * @package Thelia\Controller\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class TaxRuleController extends BaseApiController
{
    public function listAction()
    {
        $this->checkAuth(AdminResources::TAX, [], AccessManager::VIEW);
        $request = $this->getRequest();

        $params = array_merge(
            [
                "limit" => 10,
            ],
            $request->query->all()
        );

        return JsonResponse::create($this->getTaxRule($params));
    }

    public function getAction($taxRuleId)
    {
        $this->checkAuth(AdminResources::TAX, [], AccessManager::VIEW);

        $request = $this->getRequest();

        $params = array_merge(
            [
                "id" => $taxRuleId,
            ],
            $request->query->all()
        );

        $result = $this->getTaxRule($params);

        if ($result->isEmpty()) {
            throw new HttpException(404, sprintf('{"error": "tax rule with id %d not found"}', $tax_rule_id));
        }

        return JsonResponse::create($result);
    }

    public function createAction()
    {
        $this->checkAuth(AdminResources::TAX, [], AccessManager::CREATE);

        $form = $this->createForm(null, "tax_rule", [], array(
            "csrf_protection" => false,
        ));

        $event = new TaxRuleEvent();

        $taxRule = $this->getTaxRule(array_merge(
            [
                "id" => $event->getTaxRule()->getId(),
            ],
            $this->getRequest()->query->all()
        ));

        return new JsonResponse($taxRule, 201);
    }

    public function updateAction()
    {
        $this->checkAuth(AdminResources::TAX, [], AccessManager::UPDATE);

        $form = $this->createForm(null, "tax_rule", [], array(
            "csrf_protection" => false,
        ));

        $form->getFormBuilder()->addEventListener(
            FormEvents::PRE_SUBMIT,
            [$this, "hydrateUpdateForm"]
        );

        $event = new TaxRuleEvent();

        $taxRule = $this->getTaxRule(array_merge(
            [
                "id" => $event->getTaxRule()->getId(),
            ],
            $this->getRequest()->query->all()
        ));

        return new JsonResponse($taxRule, 201);
    }

    public function deleteAction()
    {
        $this->checkAuth(AdminResources::TAX, [], AccessManager::DELETE);


        return $this->nullResponse(204);
    }

    protected function hydrateUpdateForm(FormEvent $event)
    {

    }

    protected function getTaxRule($params)
    {
        $taxRuleLoop = new TaxRule($this->getContainer());
        $taxRuleLoop->initializeArgs($params);

        return $taxRuleLoop->exec($pagination);
    }
}

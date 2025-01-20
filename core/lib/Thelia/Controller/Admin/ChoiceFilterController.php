<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\ChoiceFilterQuery;
use Thelia\Tools\URL;

/**
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class ChoiceFilterController extends BaseAdminController
{
    /**
     * @throws PropelException
     */
    #[Route(
        '/admin/choicefilter/save',
        name: 'choicefilter.save',
        methods: ['POST']
    )]
    public function saveAction(Request $request): Response
    {
        $data = $request->get('ChoiceFilter');

        if (!empty($data['template_id'])) {
            $templateId = (int) $data['template_id'];
            ChoiceFilterQuery::create()
                ->filterByTemplateId($templateId)
                ->delete();

            $choiceFilterBase = (new ChoiceFilter())
                ->setTemplateId($templateId);

            $parameters = [
                'template_id' => $templateId,
            ];
            $redirectUrl = '/admin/configuration/templates/update';
        } elseif (!empty($data['category_id'])) {
            $categoryId = (int) $data['category_id'];
            ChoiceFilterQuery::create()
                ->filterByCategoryId($categoryId)
                ->delete();

            $choiceFilterBase = (new ChoiceFilter())
                ->setCategoryId($categoryId);

            $parameters = [
                'category_id' => $categoryId,
            ];
            $redirectUrl = '/admin/categories/update?category_id='.$categoryId.'&current_tab=associations#choice-filter';
        } else {
            throw new \RuntimeException('Missing parameter');
        }

        foreach ($data['filter'] as $filter) {
            $choiceFilter = clone $choiceFilterBase;

            $choiceFilter
                ->setVisible((int) $filter['visible'])
                ->setPosition((int) $filter['position']);

            if ($filter['type'] === 'attribute') {
                $choiceFilter
                    ->setAttributeId($filter['id']);
            } elseif ($filter['type'] === 'feature') {
                $choiceFilter
                    ->setFeatureId((int) $filter['id']);
            } else {
                $choiceFilter
                    ->setOtherId((int) $filter['id']);
            }

            $choiceFilter->save();
        }

        $this->getSession()->getFlashBag()->add('choice-filter-success', Translator::getInstance()->trans('Configuration saved successfully'));

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl($redirectUrl, $parameters)
        );
    }

    /**
     * @throws PropelException
     */
    #[Route(
        '/admin/choicefilter/clear',
        name: 'choicefilter.clear',
        methods: ['POST']
    )]
    public function clearAction(Request $request): Response
    {
        $data = $request->get('ChoiceFilter');

        if (!empty($data['template_id'])) {
            $templateId = (int) $data['template_id'];
            ChoiceFilterQuery::create()
                ->filterByTemplateId($templateId)
                ->delete();

            $parameters = [
                'template_id' => $templateId,
            ];
            $redirectUrl = '/admin/configuration/templates/update';
        } elseif (!empty($data['category_id'])) {
            $categoryId = (int) $data['category_id'];
            ChoiceFilterQuery::create()
                ->filterByCategoryId($categoryId)
                ->delete();

            $parameters = [
                'category_id' => $categoryId,
            ];
            $redirectUrl = '/admin/categories/update?category_id='.$categoryId.'&current_tab=associations#choice-filter';
        } else {
            throw new \RuntimeException('Missing parameter');
        }

        $this->getSession()->getFlashBag()->add('choice-filter-success', Translator::getInstance()->trans('Configuration saved successfully'));

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl($redirectUrl, $parameters)
        );
    }
}

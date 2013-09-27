<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\CategoryQuery;
use Thelia\Model\FeatureI18nQuery;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Model\Map\FeatureTemplateTableMap;

/**
 *
 * Feature loop
 *
 *
 * Class Feature
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Feature extends BaseI18nLoop
{
    public $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('product'),
            Argument::createIntListTypeArgument('template'),
            Argument::createIntListTypeArgument('exclude_template'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'alpha', 'alpha-reverse', 'manual', 'manual_reverse'))
                ),
                'manual'
            ),
            Argument::createAnyTypeArgument('title')
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = FeatureQuery::create();

        /* manage translations */
        $locale = $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible != BooleanOrBothType::ANY) $search->filterByVisible($visible);

        $product = $this->getProduct();
        $template = $this->getTemplate();
        $exclude_template = $this->getExcludeTemplate();

        $use_feature_pos = true;

        if (null !== $product) {
            // Find all template assigned to the products.
            $products = ProductQuery::create()->findById($product);

            // Ignore if the product cannot be found.
            if ($products !== null) {

                // Create template array
                if ($template == null) $template = array();

                foreach($products as $product) {
                    $tpl_id = $product->getTemplateId();

                    if (! is_null($tpl_id)) $template[] = $tpl_id;
                }
            }
        }

        if (! empty($template)) {

            // Join with feature_template table to get position
            $search
                ->withColumn(FeatureTemplateTableMap::POSITION, 'position')
                ->filterByTemplate(TemplateQuery::create()->findById($template), Criteria::IN)
            ;

            $use_feature_pos = false;
        }

        if (null !== $exclude_template) {
            $exclude_features = FeatureTemplateQuery::create()->filterByTemplateId($exclude_template)->select('feature_id')->find();

            $search
                ->joinFeatureTemplate(null, Criteria::LEFT_JOIN)
                ->withColumn(FeatureTemplateTableMap::POSITION, 'position')
                ->filterById($exclude_features, Criteria::NOT_IN)
            ;

            $use_feature_pos = false;
        }

        $title = $this->getTitle();

        if (null !== $title) {
            //find all feature that match exactly this title and find with all locales.
            $features = FeatureI18nQuery::create()
                ->filterByTitle($title, Criteria::LIKE)
                ->select('id')
                ->find();

            if($features) {
                $search->filterById(
                    $features,
                    Criteria::IN
                );
            }
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "id":
                    $search->orderById(Criteria::ASC);
                    break;
                case "id_reverse":
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha-reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual":
                    if ($use_feature_pos)
                        $search->orderByPosition(Criteria::ASC);
                     else
                        $search->addAscendingOrderByColumn(FeatureTemplateTableMap::POSITION);
                    break;
                case "manual_reverse":
                    if ($use_feature_pos)
                        $search->orderByPosition(Criteria::DESC);
                     else
                        $search->addDescendingOrderByColumn(FeatureTemplateTableMap::POSITION);
                    break;
            }

        }


        /* perform search */
        $features = $this->search($search, $pagination);

        $loopResult = new LoopResult($features);

        foreach ($features as $feature) {
            $loopResultRow = new LoopResultRow($loopResult, $feature, $this->versionable, $this->timestampable, $this->countable);
            $loopResultRow->set("ID", $feature->getId())
                ->set("IS_TRANSLATED",$feature->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$locale)
                ->set("TITLE",$feature->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $feature->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $feature->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $feature->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("POSITION", $use_feature_pos ? $feature->getPosition() : $feature->getVirtualColumn('position'))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

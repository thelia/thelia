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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\FeatureI18nQuery;
use Thelia\Model\FeatureQuery;
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
class Feature extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $useFeaturePosition;

    protected $timestampable = true;

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

    public function buildModelCriteria()
    {
        $search = FeatureQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible != BooleanOrBothType::ANY) {
            $search->filterByVisible($visible);
        }

        $product = $this->getProduct();
        $template = $this->getTemplate();
        $exclude_template = $this->getExcludeTemplate();

        $this->useFeaturePosition = true;

        if (null !== $product) {
            // Find all template assigned to the products.
            $products = ProductQuery::create()->findById($product);

            // Ignore if the product cannot be found.
            if ($products !== null) {
                // Create template array
                if ($template == null) {
                    $template = array();
                }

                foreach ($products as $product) {
                    $tpl_id = $product->getTemplateId();

                    if (! is_null($tpl_id)) {
                        $template[] = $tpl_id;
                    }
                }
            }

            // franck@cqfdev.fr - 05/12/2013 : if the given product has no template
            // or if the product cannot be found, do not return anything.
            if (empty($template)) {
                return null;
            }
        }

        if (! empty($template)) {
            // Join with feature_template table to get position
            $search
                ->withColumn(FeatureTemplateTableMap::POSITION, 'position')
                ->filterByTemplate(TemplateQuery::create()->findById($template), Criteria::IN)
            ;

            $this->useFeaturePosition = false;
        }

        if (null !== $exclude_template) {
            $exclude_features = FeatureTemplateQuery::create()->filterByTemplateId($exclude_template)->select('feature_id')->find();

            $search
                ->joinFeatureTemplate(null, Criteria::LEFT_JOIN)
                ->withColumn(FeatureTemplateTableMap::POSITION, 'position')
                ->filterById($exclude_features, Criteria::NOT_IN)
            ;

            $this->useFeaturePosition = false;
        }

        $title = $this->getTitle();

        if (null !== $title) {
            //find all feature that match exactly this title and find with all locales.
            $features = FeatureI18nQuery::create()
                ->filterByTitle($title, Criteria::LIKE)
                ->select('id')
                ->find();

            if ($features) {
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
                    if ($this->useFeaturePosition) {
                        $search->orderByPosition(Criteria::ASC);
                    } else {
                        $search->addAscendingOrderByColumn(FeatureTemplateTableMap::POSITION);
                    }
                    break;
                case "manual_reverse":
                    if ($this->useFeaturePosition) {
                        $search->orderByPosition(Criteria::DESC);
                    } else {
                        $search->addDescendingOrderByColumn(FeatureTemplateTableMap::POSITION);
                    }
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $feature) {
            $loopResultRow = new LoopResultRow($feature);
            $loopResultRow->set("ID", $feature->getId())
                ->set("IS_TRANSLATED",$feature->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$this->locale)
                ->set("TITLE",$feature->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $feature->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $feature->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $feature->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("POSITION", $this->useFeaturePosition ? $feature->getPosition() : $feature->getVirtualColumn('position'))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

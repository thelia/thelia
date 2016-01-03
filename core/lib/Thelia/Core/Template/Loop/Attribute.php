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
use Thelia\Model\AttributeQuery;
use Thelia\Model\Attribute as AttributeModel;
use Thelia\Model\Product as ProductModel;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Model\ProductQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\Map\AttributeTemplateTableMap;

/**
 *
 * Attribute loop
 *
 *
 * Class Attribute
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getProduct()
 * @method int[] getTemplate()
 * @method int[] getExcludeTemplate()
 * @method int[] getExclude()
 * @method string[] getOrder()
 */
class Attribute extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $useAttributePosistion;

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
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'alpha', 'alpha_reverse', 'manual', 'manual_reverse'))
                ),
                'manual'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = AttributeQuery::create();

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

        $product = $this->getProduct();
        $template = $this->getTemplate();
        $excludeTemplate = $this->getExcludeTemplate();

        $this->useAttributePosistion = true;

        if (null !== $product) {
            // Find all template assigned to the products.
            $products = ProductQuery::create()->findById($product);

            // Ignore if the product cannot be found.
            if ($products !== null) {
                // Create template array
                if ($template == null) {
                    $template = array();
                }

                /** @var ProductModel $product */
                foreach ($products as $product) {
                    $tplId = $product->getTemplateId();

                    if (! is_null($tplId)) {
                        $template[] = $tplId;
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
                ->withColumn(AttributeTemplateTableMap::POSITION, 'position')
                ->filterByTemplate(TemplateQuery::create()->findById($template), Criteria::IN)
            ;

            $this->useAttributePosistion = false;
        } elseif (null !== $excludeTemplate) {
            // Join with attribute_template table to get position
            $excludeAttributes = AttributeTemplateQuery::create()->filterByTemplateId($excludeTemplate)->select('attribute_id')->find();

            $search
                ->filterById($excludeAttributes, Criteria::NOT_IN)
            ;
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
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual":
                    if ($this->useAttributePosistion) {
                        $search->orderByPosition(Criteria::ASC);
                    } else {
                        $search->addAscendingOrderByColumn(AttributeTemplateTableMap::POSITION);
                    }
                    break;
                case "manual_reverse":
                    if ($this->useAttributePosistion) {
                        $search->orderByPosition(Criteria::DESC);
                    } else {
                        $search->addDescendingOrderByColumn(AttributeTemplateTableMap::POSITION);
                    }
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var AttributeModel $attribute */
        foreach ($loopResult->getResultDataCollection() as $attribute) {
            $loopResultRow = new LoopResultRow($attribute);
            $loopResultRow->set("ID", $attribute->getId())
                ->set("IS_TRANSLATED", $attribute->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $attribute->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $attribute->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $attribute->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $attribute->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("POSITION", $this->useAttributePosistion ? $attribute->getPosition() : $attribute->getVirtualColumn('position'))
            ;
            $this->addOutputFields($loopResultRow, $attribute);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

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

use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\Base\ProductCategoryQuery;
use Thelia\Model\Base\AttributeQuery;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Model\ProductQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Model\AttributeTemplateQuery;

/**
 *
 * Attribute loop
 *
 *
 * Class Attribute
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Attribute extends BaseI18nLoop
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

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = AttributeQuery::create();

        $backendContext = $this->getBackend_context();

        $lang = $this->getLang();

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

        $product = $this->getProduct();
        $template = $this->getTemplate();

        if (null !== $product) {
            // Find the template assigned to the product.
            $productObj = ProductQuery::create()->findPk($product);

            // Ignore if the product cannot be found.
            if ($productObj !== null)
                $template = $productObj->getTemplate();
         }


         // If we have to filter by template, find all attributes assigned to this template, and filter by found IDs
        if (null !== $template) {
            $search->filterById(
                AttributeTemplateQuery::create()->filterByTemplateId($template)->select('id')->find(),
                Criteria::IN
            );
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
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "manual_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        /* perform search */
        $attributes = $this->search($search, $pagination);

        $loopResult = new LoopResult($attributes);

        foreach ($attributes as $attribute) {
            $loopResultRow = new LoopResultRow($loopResult, $attribute, $this->versionable, $this->timestampable, $this->countable);
            $loopResultRow->set("ID", $attribute->getId())
                ->set("IS_TRANSLATED",$attribute->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$locale)
                ->set("TITLE",$attribute->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $attribute->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $attribute->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $attribute->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("POSITION", $attribute->getPosition());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

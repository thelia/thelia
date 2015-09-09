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
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\CategoryQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Model\ProductQuery;
use Thelia\Model\Category as CategoryModel;

/**
 *
 * Category loop, all params available :
 *
 * - id : can be an id (eq : 3) or a "string list" (eg: 3, 4, 5)
 * - parent : categories having this parent id
 * - current : current id is used if you are on a category page
 * - not_empty : if value is 1, category and subcategories must have at least 1 product
 * - visible : default 1, if you want category not visible put 0
 * - order : all value available :  'alpha', 'alpha_reverse', 'manual' (default), 'manual_reverse', 'random'
 * - exclude : all category id you want to exclude (as for id, an integer or a "string list" can be used)
 *
 * Class Category
 * @package Thelia\Core\Template\Loop
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int getParent()
 * @method int getProduct()
 * @method int getExcludeProduct()
 * @method bool getCurrent()
 * @method bool getNotEmpty()
 * @method bool getWithPrevNextInfo()
 * @method bool getNeedCountChild()
 * @method bool getNeedProductCount()
 * @method bool|string getVisible()
 * @method int[] getExclude()
 * @method string[] getOrder()
 */
class Category extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    protected $timestampable = true;
    protected $versionable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('parent'),
            Argument::createIntTypeArgument('product'),
            Argument::createIntTypeArgument('exclude_product'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('not_empty', 0),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            Argument::createBooleanTypeArgument('need_count_child', false),
            Argument::createBooleanTypeArgument('need_product_count', false),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'alpha', 'alpha_reverse', 'manual', 'manual_reverse', 'visible', 'visible_reverse', 'random'))
                ),
                'manual'
            ),
            Argument::createIntListTypeArgument('exclude')
        );
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn()
    {
        return [
            "title"
        ];
    }

    /**
     * @param CategoryQuery $search
     * @param string $searchTerm
     * @param string $searchIn
     * @param string $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria)
    {
        $search->_and();

        $search->where("CASE WHEN NOT ISNULL(`requested_locale_i18n`.ID) THEN `requested_locale_i18n`.`TITLE` ELSE `default_locale_i18n`.`TITLE` END ".$searchCriteria." ?", $searchTerm, \PDO::PARAM_STR);
    }

    public function buildModelCriteria()
    {
        $search = CategoryQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS'));

        $id = $this->getId();

        if (!is_null($id)) {
            $search->filterById($id, Criteria::IN);
        }

        $parent = $this->getParent();

        if (!is_null($parent)) {
            $search->filterByParent($parent);
        }

        $current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->request->get("category_id"));
        } elseif ($current === false) {
            $search->filterById($this->request->get("category_id"), Criteria::NOT_IN);
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $product = $this->getProduct();

        if ($product != null) {
            $obj = ProductQuery::create()->findPk($product);

            if ($obj != null) {
                $search->filterByProduct($obj, Criteria::IN);
            }
        }

        $excludeProduct = $this->getExcludeProduct();

        if ($excludeProduct != null) {
            $obj = ProductQuery::create()->findPk($excludeProduct);

            if ($obj != null) {
                $search->filterByProduct($obj, Criteria::NOT_IN);
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
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case "manual":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "visible":
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case "visible_reverse":
                    $search->orderByVisible(Criteria::DESC);
                    break;
                case "random":
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break(2);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var CategoryModel $category */
        foreach ($loopResult->getResultDataCollection() as $category) {
            /*
             * no cause pagination lost :
             * if ($this->getNotEmpty() && $category->countAllProducts() == 0) continue;
             */

            $loopResultRow = new LoopResultRow($category);

            $loopResultRow
                ->set("ID", $category->getId())
                ->set("IS_TRANSLATED", $category->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $category->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $category->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $category->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $category->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("PARENT", $category->getParent())
                ->set("ROOT", $category->getRoot($category->getId()))
                ->set("URL", $category->getUrl($this->locale))
                ->set("META_TITLE", $category->getVirtualColumn('i18n_META_TITLE'))
                ->set("META_DESCRIPTION", $category->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set("META_KEYWORDS", $category->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set("VISIBLE", $category->getVisible() ? "1" : "0")
                ->set("POSITION", $category->getPosition())
                ->set("TEMPLATE", $category->getDefaultTemplateId())

            ;

            if ($this->getNeedCountChild()) {
                $loopResultRow->set("CHILD_COUNT", $category->countChild());
            }

            if ($this->getNeedProductCount()) {
                $loopResultRow->set("PRODUCT_COUNT", $category->countAllProducts());
            }

            if ($this->getBackendContext() || $this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previous = CategoryQuery::create()
                    ->filterByParent($category->getParent())
                    ->filterByPosition($category->getPosition(), Criteria::LESS_THAN)
                    ->orderByPosition(Criteria::DESC)
                    ->findOne()
                ;

                $next = CategoryQuery::create()
                    ->filterByParent($category->getParent())
                    ->filterByPosition($category->getPosition(), Criteria::GREATER_THAN)
                    ->orderByPosition(Criteria::ASC)
                    ->findOne()
                ;

                $loopResultRow
                    ->set("HAS_PREVIOUS", $previous != null ? 1 : 0)
                    ->set("HAS_NEXT", $next != null ? 1 : 0)
                    ->set("PREVIOUS", $previous != null ? $previous->getId() : -1)
                    ->set("NEXT", $next != null ? $next->getId() : -1)
                ;
            }

            $this->addOutputFields($loopResultRow, $category);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

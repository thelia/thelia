<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Element\StandardI18nFieldsSearchTrait;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Category as CategoryModel;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Category loop, all params available :.
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
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int[]       getParent()
 * @method int[]       getExcludeParent()
 * @method int[]       getProduct()
 * @method int[]       getExcludeProduct()
 * @method int[]       getContent()
 * @method bool        getCurrent()
 * @method bool        getNotEmpty()
 * @method bool        getWithPrevNextInfo()
 * @method bool        getNeedCountChild()
 * @method bool        getNeedProductCount()
 * @method bool        getProductCountVisibleOnly()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 * @method string[]    getOrder()
 * @method int[]       getTemplateId()
 */
class Category extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;

    protected $timestampable = true;
    protected $versionable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('parent'),
            Argument::createIntListTypeArgument('exclude_parent'),
            Argument::createIntListTypeArgument('product'),
            Argument::createIntListTypeArgument('exclude_product'),
            Argument::createIntListTypeArgument('content'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('not_empty', 0),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            Argument::createBooleanTypeArgument('need_count_child', false),
            Argument::createBooleanTypeArgument('need_product_count', false),
            Argument::createBooleanTypeArgument('product_count_visible_only', false),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createIntListTypeArgument('template_id'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType([
                        'id', 'id_reverse',
                        'alpha', 'alpha_reverse',
                        'manual', 'manual_reverse',
                        'visible', 'visible_reverse',
                        'created', 'created_reverse',
                        'updated', 'updated_reverse',
                        'random',
                    ]),
                ),
                'manual',
            ),
            Argument::createIntListTypeArgument('exclude'),
        );
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn(): array
    {
        return $this->getStandardI18nSearchFields();
    }

    public function doSearch(ModelCriteria $search, string $searchTerm, array $searchIn, string $searchCriteria): void
    {
        $search->_and();

        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria, $searchIn);
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = CategoryQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $parent = $this->getParent();

        if (null !== $parent) {
            $search->filterByParent($parent, Criteria::IN);
            $positionOrderAllowed = true;
        } else {
            $positionOrderAllowed = false;
        }

        $excludeParent = $this->getExcludeParent();

        if (null !== $excludeParent) {
            $search->filterByParent($excludeParent, Criteria::NOT_IN);
        }

        $current = $this->getCurrent();

        if (true === $current) {
            $search->filterById($this->getCurrentRequest()->get('category_id'));
        } elseif (false === $current) {
            $search->filterById($this->getCurrentRequest()->get('category_id'), Criteria::NOT_IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if (BooleanOrBothType::ANY !== $visible) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $products = $this->getProduct();

        if (null !== $products) {
            $obj = ProductQuery::create()->findPks($products);

            if (null !== $obj) {
                $search->filterByProduct($obj, Criteria::IN);
            }
        }

        $excludeProducts = $this->getExcludeProduct();

        if (null !== $excludeProducts) {
            $obj = ProductQuery::create()->findPks($excludeProducts);

            if (null !== $obj) {
                $search->filterByProduct($obj, Criteria::NOT_IN);
            }
        }

        $contentId = $this->getContent();

        if (null !== $contentId) {
            $search->useCategoryAssociatedContentQuery()
                ->filterByContentId($contentId, Criteria::IN)
                ->endUse();
        }

        $templateIdList = $this->getTemplateId();

        if (null !== $templateIdList) {
            $search->filterByDefaultTemplateId($templateIdList, Criteria::IN);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'visible':
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case 'visible_reverse':
                    $search->orderByVisible(Criteria::DESC);
                    break;
                case 'created':
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case 'created_reverse':
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case 'updated':
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case 'updated_reverse':
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var CategoryModel $category */
        foreach ($loopResult->getResultDataCollection() as $category) {
            /*
             * no cause pagination lost :
             * if ($this->getNotEmpty() && $category->countAllProducts() == 0) continue;
             */

            $loopResultRow = new LoopResultRow($category);

            $loopResultRow
                ->set('ID', $category->getId())
                ->set('IS_TRANSLATED', $category->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $category->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $category->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $category->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $category->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('PARENT', $category->getParent())
                ->set('ROOT', $category->getRoot($category->getId()))
                ->set('URL', $this->getReturnUrl() ? $category->getUrl($this->locale) : null)
                ->set('META_TITLE', $category->getVirtualColumn('i18n_META_TITLE'))
                ->set('META_DESCRIPTION', $category->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set('META_KEYWORDS', $category->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set('VISIBLE', $category->getVisible() ? '1' : '0')
                ->set('POSITION', $category->getPosition())
                ->set('TEMPLATE', $category->getDefaultTemplateId());

            if ($this->getNeedCountChild()) {
                $loopResultRow->set('CHILD_COUNT', $category->countChild());
            }

            if ($this->getNeedProductCount()) {
                if ($this->getProductCountVisibleOnly()) {
                    $loopResultRow->set('PRODUCT_COUNT', $category->countAllProductsVisibleOnly());
                } else {
                    $loopResultRow->set('PRODUCT_COUNT', $category->countAllProducts());
                }
            }

            $isBackendContext = $this->getBackendContext();

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previousQuery = CategoryQuery::create()
                    ->filterByParent($category->getParent())
                    ->filterByPosition($category->getPosition(), Criteria::LESS_THAN);

                if (!$isBackendContext) {
                    $previousQuery->filterByVisible(true);
                }

                $previous = $previousQuery
                    ->orderByPosition(Criteria::DESC)
                    ->findOne();

                $nextQuery = CategoryQuery::create()
                    ->filterByParent($category->getParent())
                    ->filterByPosition($category->getPosition(), Criteria::GREATER_THAN);

                if (!$isBackendContext) {
                    $nextQuery->filterByVisible(true);
                }

                $next = $nextQuery
                    ->orderByPosition(Criteria::ASC)
                    ->findOne();

                $loopResultRow
                    ->set('HAS_PREVIOUS', null !== $previous ? 1 : 0)
                    ->set('HAS_NEXT', null !== $next ? 1 : 0)
                    ->set('PREVIOUS', null !== $previous ? $previous->getId() : -1)
                    ->set('NEXT', null !== $next ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $category);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

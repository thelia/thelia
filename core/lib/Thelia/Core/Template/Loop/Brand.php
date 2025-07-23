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
use Thelia\Model\BrandQuery;
use Thelia\Model\ProductQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Brand loop.
 *
 * Class Brand
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method int         getProduct()
 * @method bool|string getVisible()
 * @method string      getTitle()
 * @method bool        getCurrent()
 * @method int[]       getExclude()
 * @method string[]    getOrder()
 * @method bool        getWithPrevNextInfo()
 */
class Brand extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;

    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('product'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createAnyTypeArgument('title'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id',
                            'id-reverse',
                            'alpha',
                            'alpha-reverse',
                            'manual',
                            'manual-reverse',
                            'random',
                            'created',
                            'created-reverse',
                            'updated',
                            'updated-reverse',
                            'visible',
                            'visible-reverse',
                        ],
                    ),
                ),
                'alpha',
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
        $search = BrandQuery::create();

        /* manage translations */
        $this->configureI18nProcessing(
            $search,
            [
                'TITLE',
                'CHAPO',
                'DESCRIPTION',
                'POSTSCRIPTUM',
                'META_TITLE',
                'META_DESCRIPTION',
                'META_KEYWORDS',
            ],
        );

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $product = $this->getProduct();

        if (null !== $product && null !== $productObj = ProductQuery::create()->findPk($product)) {
            $search->filterByProduct($productObj);
        }

        $visible = $this->getVisible();

        if (BooleanOrBothType::ANY !== $visible) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $title = $this->getTitle();

        if (null !== $title) {
            $this->addSearchInI18nColumn($search, 'TITLE', Criteria::LIKE, '%'.$title.'%');
        }

        $current = $this->getCurrent();

        if (true === $current) {
            $search->filterById($this->getCurrentRequest()->get('brand_id'));
        } elseif (false === $current) {
            $search->filterById($this->getCurrentRequest()->get('brand_id'), Criteria::NOT_IN);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id-reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual-reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
                case 'created':
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case 'created-reverse':
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case 'updated':
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case 'updated-reverse':
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
                case 'visible':
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case 'visible-reverse':
                    $search->orderByVisible(Criteria::DESC);
                    break;
            }
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \Thelia\Model\Brand $brand */
        foreach ($loopResult->getResultDataCollection() as $brand) {
            $loopResultRow = new LoopResultRow($brand);

            $loopResultRow->set('ID', $brand->getId())
                ->set('IS_TRANSLATED', $brand->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $brand->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $brand->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $brand->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $brand->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('URL', $this->getReturnUrl() ? $brand->getUrl($this->locale) : null)
                ->set('META_TITLE', $brand->getVirtualColumn('i18n_META_TITLE'))
                ->set('META_DESCRIPTION', $brand->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set('META_KEYWORDS', $brand->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set('POSITION', $brand->getPosition())
                ->set('VISIBLE', $brand->getVisible())
                ->set('LOGO_IMAGE_ID', $brand->getLogoImageId() ?: 0);

            $isBackendContext = $this->getBackendContext();

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previousQuery = BrandQuery::create()
                    ->filterByPosition($brand->getPosition(), Criteria::LESS_THAN);

                if (!$isBackendContext) {
                    $previousQuery->filterByVisible(true);
                }

                $previous = $previousQuery
                    ->orderByPosition(Criteria::DESC)
                    ->findOne();

                $nextQuery = BrandQuery::create()
                    ->filterByPosition($brand->getPosition(), Criteria::GREATER_THAN);

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

            $this->addOutputFields($loopResultRow, $brand);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

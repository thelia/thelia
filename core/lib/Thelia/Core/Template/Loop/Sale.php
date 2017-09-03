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
use Thelia\Core\Template\Element\StandardI18nFieldsSearchTrait;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\SaleQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 * Sale loop
 *
 * Class Sale
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <thelia@cqfdev.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getExclude()
 * @method bool|string getActive()
 * @method int[] getProduct()
 * @method int getCurrency()
 * @method string[] getOrder()
 */
class Sale extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;
    
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('active', 1),
            Argument::createIntListTypeArgument('product'),
            Argument::createIntTypeArgument('currency', $this->getCurrentRequest()->getSession()->getCurrency()->getId()),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        array(
                            'id',
                            'id-reverse',
                            'alpha',
                            'alpha-reverse',
                            'label',
                            'label-reverse',
                            'active',
                            'active-reverse',
                            'start-date',
                            'start-date-reverse',
                            'end-date',
                            'end-date-reverse',
                            'created',
                            'created-reverse',
                            'updated',
                            'updated-reverse'
                        )
                    )
                ),
                'start-date'
            )
        );
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn()
    {
        return array_merge(
            [ "sale_label" ],
            $this->getStandardI18nSearchFields()
        );
    }

    /**
     * @param SaleQuery $search
     * @param string $searchTerm
     * @param array $searchIn
     * @param string $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria)
    {
        $search->_and();
        
        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }
            switch ($searchInElement) {
                case "sale_label":
                    $this->addSearchInI18nColumn($search, 'SALE_LABEL', $searchCriteria, $searchTerm);
                    break;
            }
        }
    
        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria);
    }

    public function buildModelCriteria()
    {
        $search = SaleQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, array('TITLE', 'SALE_LABEL', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'));

        $id = $this->getId();

        if (!is_null($id)) {
            $search->filterById($id, Criteria::IN);
        }

        $active = $this->getActive();

        if ($active !== BooleanOrBothType::ANY) {
            $search->filterByActive($active ? 1 : 0);
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $productIdList = $this->getProduct();

        if (! is_null($productIdList)) {
            $search
                ->useSaleProductQuery()
                    ->filterByProductId($productIdList, Criteria::IN)
                    ->groupByProductId()
                ->endUse()
            ;
        }

        $search
            ->leftJoinSaleOffsetCurrency('SaleOffsetCurrency')
            ->addJoinCondition('SaleOffsetCurrency', '`SaleOffsetCurrency`.`currency_id` = ?', $this->getCurrency(), null, \PDO::PARAM_INT)
        ;

        $search->withColumn('`SaleOffsetCurrency`.PRICE_OFFSET_VALUE', 'price_offset_value');

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id-reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha-reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "label":
                    $search->addAscendingOrderByColumn('i18n_SALE_LABEL');
                    break;
                case "label-reverse":
                    $search->addDescendingOrderByColumn('i18n_SALE_LABEL');
                    break;
                case "active":
                    $search->orderByActive(Criteria::ASC);
                    break;
                case "active-reverse":
                    $search->orderByActive(Criteria::DESC);
                    break;
                case "start-date":
                    $search->orderByStartDate(Criteria::ASC);
                    break;
                case "start-date-reverse":
                    $search->orderByStartDate(Criteria::DESC);
                    break;
                case "end-date":
                    $search->orderByEndDate(Criteria::ASC);
                    break;
                case "end-date-reverse":
                    $search->orderByEndDate(Criteria::DESC);
                    break;
                case "created":
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case "created-reverse":
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case "updated":
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case "updated-reverse":
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Sale $sale */
        foreach ($loopResult->getResultDataCollection() as $sale) {
            $loopResultRow = new LoopResultRow($sale);

            switch ($sale->getPriceOffsetType()) {
                case \Thelia\Model\Sale::OFFSET_TYPE_AMOUNT:
                    $priceOffsetType = 'A';
                    $priceOffsetSymbol = $this->getCurrentRequest()->getSession()->getCurrency()->getSymbol();
                    break;

                case \Thelia\Model\Sale::OFFSET_TYPE_PERCENTAGE:
                    $priceOffsetType = 'P';
                    $priceOffsetSymbol = '%';
                    break;

                default:
                    $priceOffsetType = $priceOffsetSymbol = '?';
            }

            $loopResultRow->set("ID", $sale->getId())
                ->set("IS_TRANSLATED", $sale->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $sale->getVirtualColumn('i18n_TITLE'))
                ->set("SALE_LABEL", $sale->getVirtualColumn('i18n_SALE_LABEL'))
                ->set("DESCRIPTION", $sale->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("CHAPO", $sale->getVirtualColumn('i18n_CHAPO'))
                ->set("POSTSCRIPTUM", $sale->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("ACTIVE", $sale->getActive())
                ->set("DISPLAY_INITIAL_PRICE", $sale->getDisplayInitialPrice())
                ->set("START_DATE", $sale->getStartDate())
                ->set("HAS_START_DATE", $sale->hasStartDate() ? 1 : 0)
                ->set("END_DATE", $sale->getEndDate())
                ->set("HAS_END_DATE", $sale->hasEndDate() ? 1 : 0)
                ->set("PRICE_OFFSET_TYPE", $priceOffsetType)
                ->set("PRICE_OFFSET_SYMBOL", $priceOffsetSymbol)
                ->set("PRICE_OFFSET_VALUE", $sale->getVirtualColumn('price_offset_value'))
            ;

            $this->addOutputFields($loopResultRow, $sale);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

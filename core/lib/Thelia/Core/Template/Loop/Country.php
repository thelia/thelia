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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\CountryQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 *
 * Country loop
 *
 *
 * Class Country
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getArea()
 * @method int[] getExcludeArea()
 * @method int[] getExclude()
 * @method int[] getWithArea()
 * @method bool|string getHasStates()
 * @method bool|string getVisible()
 * @method string[] getOrder()
 */
class Country extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('area'),
            Argument::createIntListTypeArgument('exclude_area'),
            Argument::createBooleanTypeArgument('with_area'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('has_states', BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id', 'id_reverse',
                            'alpha', 'alpha_reverse',
                            'visible', 'visible_reverse',
                            'random'
                        ]
                    )
                ),
                'id'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = CountryQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $area = $this->getArea();

        if (null !== $area) {
            $search
                ->useCountryAreaQuery('with_area')
                ->filterByAreaId($area, Criteria::IN)
                ->endUse();
        }

        $excludeArea = $this->getExcludeArea();

        if (null !== $excludeArea) {
            // FIXME : did not find a way to do this in a single request :(
            // select * from country where id not in (select country_id from country_area where area in (...))
            $countries = CountryAreaQuery::create()
                ->filterByAreaId($excludeArea, Criteria::IN)
                ->select([ 'country_id' ])
                ->find()
            ;

            $search->filterById($countries->toArray(), Criteria::NOT_IN);
        }

        $withArea = $this->getWithArea();

        if (true === $withArea) {
            $search
                ->joinCountryArea('with_area', Criteria::LEFT_JOIN)
                ->where('`with_area`.country_id ' . Criteria::ISNOTNULL);
        } elseif (false === $withArea) {
            $search
                ->joinCountryArea('with_area', Criteria::LEFT_JOIN)
                ->where('`with_area`.country_id ' . Criteria::ISNULL);
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $hasStates = $this->getHasStates();
        if ($hasStates !== BooleanOrBothType::ANY) {
            $search->filterByHasStates($hasStates ? 1 : 0);
        }

        $visible = $this->getVisible();
        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
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
        /** @var \Thelia\Model\Country $country */
        foreach ($loopResult->getResultDataCollection() as $country) {
            $loopResultRow = new LoopResultRow($country);
            $loopResultRow
                ->set("ID", $country->getId())
                ->set("VISIBLE", $country->getVisible())
                ->set("IS_TRANSLATED", $country->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $country->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $country->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $country->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $country->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("ISOCODE", sprintf("%03d", $country->getIsocode()))
                ->set("ISOALPHA2", $country->getIsoalpha2())
                ->set("ISOALPHA3", $country->getIsoalpha3())
                ->set("IS_DEFAULT", $country->getByDefault() ? "1" : "0")
                ->set("IS_SHOP_COUNTRY", $country->getShopCountry() ? "1" : "0")
                ->set("HAS_STATES", $country->getHasStates() ? "1" : "0")
                ->set("NEED_ZIP_CODE", $country->getNeedZipCode() ? "1" : "0")
                ->set("ZIP_CODE_FORMAT", $country->getZipCodeFormat())
            ;

            $this->addOutputFields($loopResultRow, $country);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

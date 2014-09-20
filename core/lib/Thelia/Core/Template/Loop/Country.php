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

use Thelia\Model\CountryQuery;

/**
 *
 * Country loop
 *
 *
 * Class Country
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
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
            Argument::createBooleanTypeArgument('with_area'),
            Argument::createIntListTypeArgument('exclude')
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
            $search->filterByAreaId($area, Criteria::IN);
        }

        $withArea = $this->getWith_area();

        if (true === $withArea) {
            $search->filterByAreaId(null, Criteria::ISNOTNULL);
        } elseif (false === $withArea) {
            $search->filterByAreaId(null, Criteria::ISNULL);
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $search->addAscendingOrderByColumn('i18n_TITLE');

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $country) {
            $loopResultRow = new LoopResultRow($country);
            $loopResultRow->set("ID", $country->getId())
                ->set("IS_TRANSLATED",$country->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$this->locale)
                ->set("TITLE",$country->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $country->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $country->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $country->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("ISOCODE", $country->getIsocode())
                ->set("ISOALPHA2", $country->getIsoalpha2())
                ->set("ISOALPHA3", $country->getIsoalpha3())
                ->set("IS_DEFAULT", $country->getByDefault() ? "1" : "0")
                ->set("IS_SHOP_COUNTRY", $country->getShopCountry() ? "1" : "0")
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

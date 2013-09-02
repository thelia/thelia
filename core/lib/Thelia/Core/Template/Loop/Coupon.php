<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Coupon as MCoupon;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Coupon Loop
 *
 * @package Thelia\Core\Template\Loop
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class Coupon extends BaseI18nLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id')
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = CouponQuery::create();

        /* manage translations */
        $locale = $this->configureI18nProcessing($search, array());

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        // Perform search
        $coupons = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        /** @var MCoupon $coupon */
        foreach ($coupons as $coupon) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ID", $coupon->getId())
                ->set("IS_TRANSLATED", $coupon->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $locale)
                ->set("TITLE", $coupon->getVirtualColumn('i18n_TITLE'))
                ->set("CODE", $coupon->getVirtualColumn('i18n_CODE'));

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

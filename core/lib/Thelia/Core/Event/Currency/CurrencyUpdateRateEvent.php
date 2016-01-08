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

namespace Thelia\Core\Event\Currency;

use Thelia\Core\Event\ActionEvent;

class CurrencyUpdateRateEvent extends ActionEvent
{
    protected $undefinedRates = [];

    /**
     * @param int $currencyId
     */
    public function addUndefinedRate($currencyId)
    {
        $this->undefinedRates[] = $currencyId;
    }

    public function hasUndefinedRates()
    {
        return ! empty($this->undefinedRates);
    }

    /**
     * @return array of currency objects
     */
    public function getUndefinedRates()
    {
        return $this->undefinedRates;
    }
}

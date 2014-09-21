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
use Thelia\Model\Currency;

class CurrencyEvent extends ActionEvent
{
    protected $currency = null;

    public function __construct(Currency $currency = null)
    {
        $this->currency = $currency;
    }

    public function hasCurrency()
    {
        return ! is_null($this->currency);
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }
}

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

class CurrencyUpdateEvent extends CurrencyCreateEvent
{
    protected $currency_id;
    protected $is_default;

    public function __construct($currency_id)
    {
        $this->setCurrencyId($currency_id);
    }

    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    public function setCurrencyId($currency_id)
    {
        $this->currency_id = $currency_id;

        return $this;
    }

    public function getIsDefault()
    {
        return $this->is_default;
    }

    public function setIsDefault($is_default)
    {
        $this->is_default = $is_default;

        return $this;
    }
}

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
    /** @var int */
    protected $is_default;

    /**
     * @param int $currencyId
     */
    public function __construct($currencyId)
    {
        $this->setCurrencyId($currencyId);
    }

    /**
     * @return int
     */
    public function getIsDefault()
    {
        return $this->is_default;
    }

    /**
     * @param $is_default
     * @return $this
     */
    public function setIsDefault($is_default)
    {
        $this->is_default = $is_default;

        return $this;
    }
}

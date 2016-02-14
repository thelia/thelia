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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Currency;

/**
 * Class CurrencyChangeEvent
 * @package Thelia\Core\Event\Currency
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CurrencyChangeEvent extends CurrencyEvent
{
    /** @var Request $request */
    protected $request;

    public function __construct(Currency $currency = null, Request $request = null)
    {
        parent::__construct($currency);
        $this->setRequest($request);
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}

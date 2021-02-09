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

namespace Thelia\Core\Event\Payment;

use Thelia\Core\Event\ActionEvent;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\PaymentModuleInterface;

/**
 * Class BasePaymentEvent
 * @package Thelia\Core\Event\Payment
 * @author Julien Chanséaume <julien@thelia.net>
 */
class BasePaymentEvent extends ActionEvent
{
    /** @var AbstractPaymentModule */
    protected $module;

    /**
     * BasePaymentEvent constructor.
     */
    public function __construct(AbstractPaymentModule $module)
    {
        $this->module = $module;
    }

    /**
     * @return AbstractPaymentModule
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param AbstractPaymentModule $module
     */
    public function setModule($module)
    {
        $this->module = $module;
        return $this;
    }
}

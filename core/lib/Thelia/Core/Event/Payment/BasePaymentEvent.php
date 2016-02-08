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
use Thelia\Module\PaymentModuleInterface;

/**
 * Class BasePaymentEvent
 * @package Thelia\Core\Event\Payment
 * @author Julien Chanséaume <julien@thelia.net>
 */
class BasePaymentEvent extends ActionEvent
{
    /** @var PaymentModuleInterface */
    protected $module = null;

    /**
     * BasePaymentEvent constructor.
     * @param PaymentModuleInterface $module
     */
    public function __construct(PaymentModuleInterface $module)
    {
        $this->module = $module;
    }

    /**
     * @return PaymentModuleInterface
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param PaymentModuleInterface $module
     */
    public function setModule($module)
    {
        $this->module = $module;
        return $this;
    }
}

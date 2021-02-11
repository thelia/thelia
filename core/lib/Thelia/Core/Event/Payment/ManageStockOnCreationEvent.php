<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Payment;

use Thelia\Module\PaymentModuleInterface;

/**
 * Class ManageStockOnCreationEvent
 * @package Thelia\Core\Event\Payment
 * @author Julien Chanséaume <julien@thelia.net>
 */
class ManageStockOnCreationEvent extends BasePaymentEvent
{
    /** @var bool|null */
    protected $manageStock;

    /**
     * ManageStockOnCreationEvent constructor.
     */
    public function __construct(PaymentModuleInterface $module)
    {
        parent::__construct($module);
    }

    /**
     * @return bool|null
     */
    public function getManageStock()
    {
        return $this->manageStock;
    }

    /**
     * @param bool|null $manageStock
     */
    public function setManageStock($manageStock)
    {
        $this->manageStock = $manageStock;
        return $this;
    }
}

<?php

declare(strict_types=1);

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
 * Class ManageStockOnCreationEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class ManageStockOnCreationEvent extends BasePaymentEvent
{
    protected ?bool $manageStock = null;

    /**
     * ManageStockOnCreationEvent constructor.
     */
    public function __construct(PaymentModuleInterface $module)
    {
        parent::__construct($module);
    }

    public function getManageStock(): ?bool
    {
        return $this->manageStock;
    }

    public function setManageStock(?bool $manageStock): static
    {
        $this->manageStock = $manageStock;

        return $this;
    }
}

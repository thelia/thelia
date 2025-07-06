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

use Thelia\Core\Event\ActionEvent;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\PaymentModuleInterface;

/**
 * Class BasePaymentEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class BasePaymentEvent extends ActionEvent
{
    /**
     * BasePaymentEvent constructor.
     */
    public function __construct(protected PaymentModuleInterface $module)
    {
    }

    /**
     * @return AbstractPaymentModule
     */
    public function getModule(): PaymentModuleInterface
    {
        return $this->module;
    }

    /**
     * @param AbstractPaymentModule $module
     */
    public function setModule(PaymentModuleInterface $module): static
    {
        $this->module = $module;

        return $this;
    }
}

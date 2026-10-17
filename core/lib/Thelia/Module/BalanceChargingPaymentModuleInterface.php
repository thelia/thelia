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

namespace Thelia\Module;

/**
 * Optional marker: a payment module whose pay() charges only the balance left by the
 * instant parts (it reads AbstractPaymentModule::getAmountToCharge(), never
 * Order::getTotalAmount()). Without it, the core refuses to combine instant parts with
 * this module.
 *
 * A module that confirms a payment must do so with the source set: through confirmPayment()
 * of BasePaymentModuleController, or with OrderEvent::setSourceModuleCode(). Otherwise its
 * confirmation of a cancelled order whose card was given back is refused without a trace.
 */
interface BalanceChargingPaymentModuleInterface
{
}

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

namespace Thelia\Domain\Company\Checkout\Exception;

use Thelia\Domain\Checkout\Exception\CheckoutException;

/**
 * The company of the buyer turns this order away, before it is placed or while it is written.
 *
 * Every refusal of the company checkout is one of these, so that the placement can tell them
 * from the other refusals of the checkout and answer them the way it answers those.
 */
abstract class CompanyOrderRefusedException extends CheckoutException
{
}

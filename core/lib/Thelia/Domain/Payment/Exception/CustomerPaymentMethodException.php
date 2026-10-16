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

namespace Thelia\Domain\Payment\Exception;

/**
 * Parent of every refusal about a stored payment method, so a caller can answer all of
 * them at once. The messages carry no token and no label.
 */
abstract class CustomerPaymentMethodException extends \RuntimeException
{
}

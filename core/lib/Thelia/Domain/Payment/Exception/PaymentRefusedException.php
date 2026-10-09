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
 * The provider refused the movement and took nothing: the one exception a payment module
 * throws to have the line settled as failed, with this message. Anything else it throws
 * leaves the line pending, since the call may have reached the provider.
 */
class PaymentRefusedException extends PaymentException
{
}

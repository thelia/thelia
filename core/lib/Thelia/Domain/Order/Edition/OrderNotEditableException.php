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

namespace Thelia\Domain\Order\Edition;

use Thelia\Domain\Order\Exception\OrderException;

/**
 * The order cannot be edited as it stands: invoiced, past the warehouse, or exempt from VAT.
 */
final class OrderNotEditableException extends OrderException
{
}

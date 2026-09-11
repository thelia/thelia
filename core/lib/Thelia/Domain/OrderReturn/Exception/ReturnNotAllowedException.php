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

namespace Thelia\Domain\OrderReturn\Exception;

/**
 * Thrown when a return, or one of its lines, breaks an eligibility rule: the
 * order does not belong to the customer, the product is not returnable, or the
 * requested quantity exceeds what is still returnable.
 */
class ReturnNotAllowedException extends \RuntimeException
{
}

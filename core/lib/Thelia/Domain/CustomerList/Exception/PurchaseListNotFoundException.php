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

namespace Thelia\Domain\CustomerList\Exception;

/**
 * The list does not exist, or the customer is not allowed to see it: both give
 * the same answer, so that guessing an id tells nothing about other customers.
 */
class PurchaseListNotFoundException extends \RuntimeException
{
}

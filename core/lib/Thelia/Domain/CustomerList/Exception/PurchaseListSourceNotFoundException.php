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
 * The cart or the order a list is built from does not exist, or belongs to
 * another customer: both give the same answer.
 */
class PurchaseListSourceNotFoundException extends \RuntimeException
{
}

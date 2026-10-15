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

namespace Thelia\Domain\Shipping\Exception;

/**
 * A shipment that cannot be opened, shipped or cancelled as asked: a line that is not
 * the order's, a quantity beyond what is left to ship, a gesture on a shipment that
 * already left. The message is written for the person who asked.
 */
class ShipmentNotAllowedException extends \RuntimeException
{
}

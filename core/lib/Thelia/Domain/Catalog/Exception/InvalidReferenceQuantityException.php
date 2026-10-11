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

namespace Thelia\Domain\Catalog\Exception;

/**
 * A line of references and quantities that cannot be taken: an empty or too long
 * reference, a quantity below one, or more lines than one request may carry.
 */
class InvalidReferenceQuantityException extends \InvalidArgumentException
{
}

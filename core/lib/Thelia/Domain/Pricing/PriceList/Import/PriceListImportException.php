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

namespace Thelia\Domain\Pricing\PriceList\Import;

/**
 * The file as a whole is refused (too large, unreadable, not a CSV, columns that are not the
 * expected ones): nothing is read, nothing is written.
 */
final class PriceListImportException extends \RuntimeException
{
}

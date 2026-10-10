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

namespace Thelia\Domain\Accounting;

/**
 * An invoiced order the sales journal cannot book as it is: the export leaves it out and
 * says why.
 */
final class OrderNotExportableException extends \RuntimeException
{
}

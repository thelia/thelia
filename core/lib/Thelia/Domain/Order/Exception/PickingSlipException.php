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

namespace Thelia\Domain\Order\Exception;

/**
 * The picking slip of a batch of orders cannot be produced; the message says why, in words a back-office
 * user can read.
 */
final class PickingSlipException extends \DomainException
{
    public static function noOrder(): self
    {
        return new self('No order to print: the list is empty or none of its orders exists.');
    }

    public static function tooManyOrders(int $requested, int $limit): self
    {
        return new self(\sprintf('A picking slip batch holds %d orders at most, %d were requested.', $limit, $requested));
    }

    public static function missingTemplate(string $templateName): self
    {
        return new self(\sprintf('The active PDF theme has no %s document.', $templateName));
    }
}

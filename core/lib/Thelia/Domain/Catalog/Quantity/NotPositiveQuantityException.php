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

namespace Thelia\Domain\Catalog\Quantity;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A quantity of zero or less was asked for. Never adjusted: there is nothing to sell.
 */
#[Exclude]
final class NotPositiveQuantityException extends \InvalidArgumentException
{
    public function __construct(public readonly float $requested)
    {
        parent::__construct(QuantityTranslation::trans('The quantity must be greater than zero.'));
    }
}

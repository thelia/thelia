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
 * A typed quantity that is not a number, or that could be read two ways: "1.250" is a
 * thousand two hundred and fifty in one language and one and a quarter in another.
 */
#[Exclude]
final class UnreadableQuantityException extends \InvalidArgumentException
{
    public function __construct(public readonly string $input)
    {
        parent::__construct(QuantityTranslation::trans(
            'The quantity "%input" cannot be read. Write it with digits and at most one decimal separator, like 1.25 or 1,25.',
            ['%input' => $input],
        ));
    }
}

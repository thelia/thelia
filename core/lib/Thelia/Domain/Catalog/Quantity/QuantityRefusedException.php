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
 * A quantity the rules of its combination do not accept, asked for where the buyer can
 * fix it (a form, a cart action, the front API, an import). The message says the
 * smallest quantity or the valid ones; the decision says what the lenient mode would
 * have kept.
 */
#[Exclude]
final class QuantityRefusedException extends \InvalidArgumentException
{
    public function __construct(public readonly QuantityDecision $decision)
    {
        parent::__construct($decision->refusalMessage());
    }
}

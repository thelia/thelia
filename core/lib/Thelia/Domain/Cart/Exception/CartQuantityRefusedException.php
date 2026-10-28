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

namespace Thelia\Domain\Cart\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Catalog\Quantity\QuantityDecision;
use Thelia\Domain\Catalog\Quantity\QuantityRefusedException;

/**
 * The cart refuses a quantity its combination is not sold in: below the minimum, off
 * the step, or with more decimals than it is sold with. The message gives the valid
 * quantities and is meant for the buyer; a theme that tells a refusal of the cart
 * (InvalidCartException) shows it as it is.
 */
#[Exclude]
final class CartQuantityRefusedException extends InvalidCartException
{
    public function __construct(
        string $message,
        public readonly ?QuantityDecision $decision = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function byTheRules(QuantityRefusedException $refusal): self
    {
        return new self($refusal->getMessage(), $refusal->decision, $refusal);
    }
}

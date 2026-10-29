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

use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\CheckoutException;

/**
 * A pack of the cart whose composition the catalogue no longer holds: a component deleted
 * or taken offline, an item taken out of the pack, a required item added since, a quantity
 * the bounds no longer allow. The buyer has to put the pack together again; nothing about
 * it can be ordered as it stands.
 *
 * A refusal of the checkout, so that the step of the cart reports it before the payment
 * and a theme answers it the way it answers every other refusal. The pack is named by its
 * reference, the one printed on the catalogue page; why the composition fails is kept as
 * the previous exception, for the log.
 */
final class BundleLineNotOrderableException extends CheckoutException
{
    public function __construct(
        public readonly string $productReference,
        public readonly int $cartItemId,
        ?InvalidBundleCompositionException $previous = null,
    ) {
        parent::__construct(
            'The pack %ref can no longer be ordered as it is in your cart. Please remove it and add it again.',
            previous: $previous,
            parameters: ['%ref' => $productReference],
        );
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::BundleLineNotOrderable->value;
    }

    /**
     * @return array{productReference: string, cartItemId: int}
     */
    public function violationDetails(): array
    {
        return ['productReference' => $this->productReference, 'cartItemId' => $this->cartItemId];
    }
}

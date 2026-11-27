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

namespace Thelia\Domain\Quote\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a quote line keeps of its product, so that it stays readable when the product is gone.
 */
#[Exclude]
final readonly class QuoteLineSnapshot
{
    public function __construct(
        public int $productId,
        public int $productSaleElementsId,
        public string $productRef,
        public string $productSaleElementsRef,
        public string $title,
        public ?int $taxRuleId,
        public ?string $taxRuleTitle,
    ) {
    }
}
